<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

namespace GlpiPlugin\Qrcodelabel;

use CommonDBTM;
use CommonGLPI;
use Dropdown;
use Html;
use Location;
use MassiveAction;
use Plugin;
use Session;

/**
 * Main class for QR Code Label generation using GLPI's native TCPDF.
 *
 * Generates rich inventory labels: QR code + asset name + type + serial number
 * + inventory date + location + company logo + owner text.
 *
 * No external vendor dependencies — TCPDF ships with GLPI 10 and 11.
 */
class Label extends CommonDBTM {

   static $rightname = 'plugin_qrcodelabel_label';

   /** Maximum number of copies per asset (PDF sheet). */
   public const MAX_COPIES = 50;

   /**
    * Label layout presets per tape size (mm).
    *
    * Left column: top_m + logo_h + 0.5 (gap) + qr_size ≈ label_h - bottom margin.
    * Text column (see layoutText()):
    *   - name_base   : baseline of the first line (asset name), in mm from the top
    *   - text_top    : highest position allowed for the top of the first line when
    *                   the text needs more room (Brother unprintable zone)
    *   - bottom_base : baseline of the last line (owner text / inventory number)
    */
   private static array $tapeSizes = [
      '24mm' => [
         'label_w' => 70, 'label_h' => 24, 'qr_size' => 13,
         'font_name' => 7, 'font_type' => 4.5, 'font_sn' => 5,
         'font_loc' => 4.5, 'font_inv' => 4,
         'logo_h' => 5, 'top_m' => 2, 'bot_m' => 3,
         'name_base' => 7, 'text_top' => 3.2, 'bottom_base' => 20.5,
      ],
      '25mm' => [
         'label_w' => 70, 'label_h' => 25, 'qr_size' => 14,
         'font_name' => 7.5, 'font_type' => 5, 'font_sn' => 5.5,
         'font_loc' => 5, 'font_inv' => 4.5,
         'logo_h' => 5, 'top_m' => 2, 'bot_m' => 3,
         'name_base' => 7, 'text_top' => 3.2, 'bottom_base' => 21.5,
      ],
      '36mm' => [
         'label_w' => 80, 'label_h' => 36, 'qr_size' => 15,
         'font_name' => 9, 'font_type' => 5.5, 'font_sn' => 6.5,
         'font_loc' => 6, 'font_inv' => 5.5,
         'logo_h' => 13, 'top_m' => 3, 'bot_m' => 3,
         'name_base' => 10, 'text_top' => 4, 'bottom_base' => 30,
      ],
      '50mm' => [
         'label_w' => 90, 'label_h' => 50, 'qr_size' => 26,
         'font_name' => 11, 'font_type' => 7, 'font_sn' => 8,
         'font_loc' => 7, 'font_inv' => 6.5,
         'logo_h' => 16, 'top_m' => 4, 'bot_m' => 3,
         'name_base' => 12, 'text_top' => 4, 'bottom_base' => 44,
      ],
   ];

   /**
    * Color themes.
    */
   private static array $colorModes = [
      'bw' => [
         'bg' => [255,255,255], 'qr_fg' => [0,0,0], 'qr_bg' => [255,255,255],
         'main' => [0,0,0], 'sub' => [0,0,0], 'sn' => [0,0,0],
         'loc' => [0,0,0], 'inv' => [85,85,85], 'border' => [204,204,204],
         'sep' => [224,224,224], 'invert_logo' => false,
      ],
      'mono' => [
         'bg' => [255,255,255], 'qr_fg' => [0,0,0], 'qr_bg' => [255,255,255],
         'main' => [0,0,0], 'sub' => [0,0,0], 'sn' => [0,0,0],
         'loc' => [0,0,0], 'inv' => [0,0,0], 'border' => [0,0,0],
         'sep' => [0,0,0], 'invert_logo' => false,
      ],
      'color' => [
         'bg' => [255,255,255], 'qr_fg' => [0,0,0], 'qr_bg' => [255,255,255],
         'main' => [0,0,0], 'sub' => [102,102,102], 'sn' => [51,51,51],
         'loc' => [27,58,92], 'inv' => [153,153,153], 'border' => [204,204,204],
         'sep' => [224,224,224], 'invert_logo' => false,
      ],
      'inverse' => [
         'bg' => [0,0,0], 'qr_fg' => [255,255,255], 'qr_bg' => [0,0,0],
         'main' => [255,255,255], 'sub' => [204,204,204], 'sn' => [255,255,255],
         'loc' => [170,170,170], 'inv' => [136,136,136], 'border' => [0,0,0],
         'sep' => [68,68,68], 'invert_logo' => true,
      ],
      'inverse_mono' => [
         'bg' => [0,0,0], 'qr_fg' => [255,255,255], 'qr_bg' => [0,0,0],
         'main' => [255,255,255], 'sub' => [255,255,255], 'sn' => [255,255,255],
         'loc' => [255,255,255], 'inv' => [255,255,255], 'border' => [0,0,0],
         'sep' => [255,255,255], 'invert_logo' => true,
      ],
   ];

   // ── GLPI framework integration ─────────────────────────────────────────────

   static function getTypeName($nb = 0): string {
      return __('QR Code Labels', 'qrcodelabel');
   }

   /**
    * Web path of the plugin (e.g. "/plugins/qrcodelabel").
    *
    * GLPI 11 serves every plugin under /plugins/ and deprecates
    * Plugin::getWebDir(); GLPI 10 needs it (plugins/ or marketplace/).
    *
    * @param bool $full Prefix with GLPI root_doc.
    */
   static function getWebPath(bool $full = true): string {
      global $CFG_GLPI;

      if (version_compare(GLPI_VERSION, '11.0.0-dev', '>=')) {
         $path = '/plugins/qrcodelabel';
      } else {
         $path = '/' . ltrim((string)Plugin::getWebDir('qrcodelabel', false), '/');
      }
      return $full ? rtrim((string)$CFG_GLPI['root_doc'], '/') . $path : $path;
   }

   static function getMenuContent(): array {
      $webDir = self::getWebPath(false);
      return [
         'title' => self::getTypeName(),
         'page'  => $webDir . '/front/config.php',
         'icon'  => 'fas fa-qrcode',
         'links' => [
            'config' => $webDir . '/front/config.php',
         ],
      ];
   }

   /**
    * GLPI 10 stores user input HTML-encoded ("sanitized"); labels must show the
    * raw text. GLPI 11 stores raw values (and deprecates the Sanitizer).
    */
   static function rawText($value): string {
      $value = (string)$value;
      if ($value !== ''
            && version_compare(GLPI_VERSION, '11.0.0-dev', '<')
            && class_exists('Glpi\\Toolbox\\Sanitizer')) {
         $value = (string)\Glpi\Toolbox\Sanitizer::unsanitize($value, false);
      }
      return $value;
   }

   /** HTML-escape for output in attributes and text nodes. */
   private static function e($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }

   /** Release a GD image (no-op since PHP 8, deprecated in PHP 8.5). */
   private static function freeImage($img): void {
      if (PHP_VERSION_ID < 80000 && is_resource($img)) {
         imagedestroy($img);
      }
   }

   /**
    * Number of copies requested in a form, bounded to [1, MAX_COPIES].
    */
   static function sanitizeCopies($value): int {
      return max(1, min(self::MAX_COPIES, (int)$value));
   }

   /**
    * Output format configured globally (pdf / png / both).
    */
   static function getOutputFormat(?array $config = null): string {
      $config = $config ?? Config::getConfig();
      $format = $config['output_format'] ?? 'pdf';
      return in_array($format, ['pdf', 'png', 'both'], true) ? $format : 'pdf';
   }

   // ── Tab on asset forms (single-item label generation) ────────────────────

   /**
    * Show a "QR Label" tab on each supported asset type.
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if (plugin_qrcodelabel_is_supported_itemtype($item->getType())
            && $item->getID() > 0
            && !$withtemplate
            && Session::haveRight(self::$rightname, CREATE)) {
         return self::createTabEntry(__('QR Label', 'qrcodelabel'));
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if (plugin_qrcodelabel_is_supported_itemtype($item->getType())
            && Session::haveRight(self::$rightname, CREATE)) {
         self::showSingleItemForm($item->getType(), $item->getID());
      }
      return true;
   }

   /**
    * Print profile selector + number of copies (shared by the tab and the
    * massive action form). Outputs table rows.
    */
   private static function showGenerationFields(string $rowClass): void {
      $profiles       = Printprofile::getProfiles();
      $defaultProfile = Printprofile::getDefault();
      $defaultId      = $defaultProfile ? (int)$defaultProfile['id'] : 0;

      echo "<tr class='" . self::e($rowClass) . "'>";
      echo "<td>" . self::e(__('Print profile', 'qrcodelabel')) . "</td><td>";
      if (empty($profiles)) {
         echo "<em>" . self::e(__('No print profile configured.', 'qrcodelabel')) . "</em>";
      } else {
         Dropdown::showFromArray('profile_id', $profiles, [
            'value' => $defaultId,
            'width' => '200',
         ]);
      }
      echo "</td></tr>";

      // Copies only make sense on a PDF sheet: PNG export produces one image
      // per asset (copies are set in the printer app).
      if (self::getOutputFormat() !== 'png') {
         echo "<tr class='" . self::e($rowClass) . "'>";
         echo "<td>" . self::e(__('Number of copies', 'qrcodelabel')) . "</td><td>";
         echo "<input type='number' name='nb_copies' value='1' min='1' max='" . self::MAX_COPIES
            . "' step='1' class='form-control' style='width: 7em'>";
         echo "</td></tr>";
      }
   }

   /**
    * Show the single-item label generation form on an asset tab.
    */
   static function showSingleItemForm(string $itemtype, int $items_id): void {
      echo "<form name='qrcodelabel_form' method='post' action='"
         . self::e(self::getWebPath() . '/front/label.form.php') . "'>";

      echo "<input type='hidden' name='itemtype' value='" . self::e($itemtype) . "'>";
      echo "<input type='hidden' name='items_id' value='" . (int)$items_id . "'>";

      echo "<div class='center'><table class='tab_cadre'>";
      echo "<tr><th colspan='2'>" . self::e(__('Generate QR label', 'qrcodelabel')) . "</th></tr>";

      self::showGenerationFields('tab_bg_1');

      // Generate button
      echo "<tr><td class='tab_bg_1' colspan='2' align='center'>";
      echo "<input type='submit' value='" . self::e(__('Generate', 'qrcodelabel')) . "' class='submit'>";
      echo "</td></tr>";

      echo "</table></div>";
      Html::closeForm();
   }

   // ── Massive Action form ────────────────────────────────────────────────────

   static function showMassiveActionsSubForm(MassiveAction $ma): bool {
      if ($ma->getAction() !== 'GenerateLabels') {
         return false;
      }
      if (!Session::haveRight(self::$rightname, CREATE)) {
         return false;
      }

      echo "<table class='mx-auto'>";
      self::showGenerationFields('');
      echo '</table><br/>';
      echo Html::submit(__('Generate', 'qrcodelabel'), ['name' => 'massiveaction']);

      return true;
   }

   // ── Asset data ─────────────────────────────────────────────────────────────

   /**
    * Collect the data printed on a label for a loaded item.
    */
   static function buildAssetData(CommonDBTM $item): array {
      global $CFG_GLPI;

      $itemtype = $item->getType();
      $id       = (int)$item->getID();

      // Same URL as GLPI's native QR code (BarcodeManager)
      $url = $CFG_GLPI['url_base'] . $itemtype::getFormURLWithID($id, false);

      $location = '';
      if ($item->isField('locations_id') && (int)$item->fields['locations_id'] > 0) {
         $loc = new Location();
         if ($loc->getFromDB((int)$item->fields['locations_id'])) {
            $location = $loc->fields['completename'] ?? $loc->fields['name'] ?? '';
         }
      }

      // Inventory date: YYYY-MM-DD → DD/MM/YYYY
      $dateInv = '';
      $dateRaw = (string)($item->fields['date_creation'] ?? '');
      if ($dateRaw !== '') {
         $ymd = explode('-', substr($dateRaw, 0, 10));
         $dateInv = (count($ymd) === 3) ? "{$ymd[2]}/{$ymd[1]}/{$ymd[0]}" : substr($dateRaw, 0, 10);
      }

      $name = self::rawText($item->fields['name'] ?? '');

      return [
         'itemtype'    => $itemtype,
         'id'          => $id,
         'name'        => $name !== '' ? $name : __('No name'),
         'serial'      => self::rawText($item->fields['serial'] ?? ''),
         'otherserial' => self::rawText($item->fields['otherserial'] ?? ''),
         'type_label'  => (string)$itemtype::getTypeName(1),
         'location'    => self::rawText($location),
         'date_inv'    => $dateInv,
         'url'         => $url,
      ];
   }

   /**
    * Rendering parameters for a print profile + global configuration.
    */
   static function buildParams(array $profile, array $config): array {
      return [
         'tape_size'     => $profile['tape_size'],
         'color_mode'    => $profile['color_mode'],
         'show_date'     => (bool)$profile['show_date'],
         'show_location' => (bool)($config['show_location'] ?? 0),
         'page_size'     => $profile['page_size'],
         'orientation'   => $profile['orientation'],
         'owner_text'    => self::rawText(trim((string)($config['owner_text'] ?? ''))),
      ];
   }

   // ── Massive Action processing ──────────────────────────────────────────────

   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids): void {
      if ($ma->getAction() !== 'GenerateLabels') {
         return;
      }
      if (!Session::haveRight(self::$rightname, CREATE)
            || !plugin_qrcodelabel_is_supported_itemtype($item->getType())) {
         $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
         return;
      }

      $input = $ma->getInput();

      $profile = Printprofile::getProfileById((int)($input['profile_id'] ?? 0))
         ?? Printprofile::getDefault();
      if (!$profile) {
         $ma->addMessage(__('No print profile found.', 'qrcodelabel'));
         $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
         return;
      }
      $copies = self::sanitizeCopies($input['nb_copies'] ?? 1);

      $assets  = [];
      $okIds   = [];
      $koIds   = [];
      $denied  = [];
      foreach ($ids as $id) {
         if (!$item->getFromDB($id)) {
            $koIds[] = $id;
            continue;
         }
         // Entity / visibility check: never print data the user cannot read.
         if (!$item->can($id, READ)) {
            $denied[] = $id;
            continue;
         }
         $data = self::buildAssetData($item);
         for ($c = 0; $c < $copies; $c++) {
            $assets[] = $data;
         }
         $okIds[] = $id;
      }

      $ok = false;
      if (!empty($assets)) {
         $config = Config::getConfig();
         $ok = self::emitDownloadLinks(
            $assets,
            self::buildParams($profile, $config),
            self::getOutputFormat($config),
            Html::getBackUrl()
         );
         if ($ok) {
            // Land on a page that keeps the download buttons on screen (GLPI
            // toasts disappear after a few seconds, so the link was easy to miss).
            $ma->setRedirect(self::getWebPath() . '/front/download.php');
         } else {
            $ma->addMessage(__('Label generation failed.', 'qrcodelabel'));
         }
      }

      if ($okIds) {
         $ma->itemDone($item->getType(), $okIds, $ok ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO);
      }
      if ($koIds) {
         $ma->itemDone($item->getType(), $koIds, MassiveAction::ACTION_KO);
      }
      if ($denied) {
         $ma->itemDone($item->getType(), $denied, MassiveAction::ACTION_NORIGHT);
      }
   }

   /**
    * Produce PDF and/or PNG outputs and register them as pending downloads
    * (shown by front/download.php).
    *
    * @param  array  $assets  Asset data (may contain nulls for blank slots — PDF only).
    * @param  array  $params  Shared params (tape_size, color_mode, etc.).
    * @param  string $format  'pdf', 'png', or 'both'.
    * @param  string $backUrl Page to go back to from the download page.
    * @return bool            true if at least one output succeeded.
    */
   static function emitDownloadLinks(array $assets, array $params, string $format, string $backUrl = ''): bool {
      $files = [];

      if ($format === 'pdf' || $format === 'both') {
         $pdfPath = self::printPDF($assets, $params);
         if ($pdfPath) {
            $files[] = [
               'token' => self::registerTmpFile($pdfPath),
               'label' => __('Download QR labels (PDF)', 'qrcodelabel'),
            ];
         }
      }

      if ($format === 'png' || $format === 'both') {
         // Dedupe by itemtype+id so N copies of the same asset produce 1 PNG
         // (Brother Cube users set the number of copies in iPrint&Label).
         $seen       = [];
         $pngAssets  = [];
         foreach ($assets as $a) {
            if ($a === null) {
               continue;
            }
            $key = ($a['itemtype'] ?? '') . '#' . ($a['id'] ?? '');
            if (isset($seen[$key])) {
               continue;
            }
            $seen[$key] = true;
            $pngAssets[] = $a;
         }
         $pngPath = self::printPngBundle($pngAssets, $params);
         if ($pngPath) {
            $files[] = [
               'token' => self::registerTmpFile($pngPath),
               'label' => (substr($pngPath, -4) === '.zip')
                  ? __('Download QR labels (ZIP of PNGs)', 'qrcodelabel')
                  : __('Download QR label (PNG)', 'qrcodelabel'),
            ];
         }
      }

      // A massive action on several item types calls this once per type within
      // the same request: accumulate the files of the request.
      if (!self::$batchOpen || !isset($_SESSION['qrcodelabel_downloads']['files'])) {
         $_SESSION['qrcodelabel_downloads'] = ['files' => [], 'back' => $backUrl];
         self::$batchOpen = true;
      }
      foreach ($files as $file) {
         $_SESSION['qrcodelabel_downloads']['files'][] = $file;
      }

      return !empty($files);
   }

   /** True once emitDownloadLinks() has started a download batch in this request. */
   private static bool $batchOpen = false;

   /**
    * Whether $url is a safe "back" link: a local absolute path, or an http(s)
    * URL on the host serving the current request.
    */
   private static function isLocalUrl(string $url): bool {
      if ($url === '' || strpos($url, '//') === 0 || strpos($url, '\\') !== false) {
         return false;
      }
      if ($url[0] === '/') {
         return true;
      }
      $parts = parse_url($url);
      if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
         return false;
      }
      $currentHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
      return $currentHost !== null && $currentHost !== false
         && strcasecmp((string)($parts['host'] ?? ''), (string)$currentHost) === 0;
   }

   /**
    * Download page content: persistent buttons for the files of the last
    * generation (each file can be downloaded once).
    */
   static function showDownloadPage(): void {
      $batch  = $_SESSION['qrcodelabel_downloads'] ?? [];
      $tokens = $_SESSION['qrcodelabel_pdf_tokens'] ?? [];
      $files  = [];
      foreach (($batch['files'] ?? []) as $file) {
         $token = (string)($file['token'] ?? '');
         if ($token !== '' && isset($tokens[$token]) && is_file((string)$tokens[$token])) {
            $files[] = $file;
         }
      }

      // Only offer a "back" link to a page of this GLPI instance.
      $back = (string)($batch['back'] ?? '');
      if (!self::isLocalUrl($back)) {
         $back = '';
      }

      echo "<div class='container-lg my-3'><div class='card'>";
      echo "<div class='card-header'><h3 class='card-title'><i class='fas fa-qrcode me-2'></i>"
         . self::e(__('QR Code Labels', 'qrcodelabel')) . "</h3></div>";
      echo "<div class='card-body'>";
      if (empty($files)) {
         echo "<p>" . self::e(__('No label file is waiting for download. Generate the labels again.', 'qrcodelabel')) . "</p>";
      } else {
         echo "<p>" . self::e(__('Your labels are ready:', 'qrcodelabel')) . "</p>";
         echo "<div class='d-flex flex-wrap gap-2 mb-3'>";
         foreach ($files as $file) {
            $href = self::getWebPath() . '/front/send.php?token=' . urlencode((string)$file['token']);
            // One-shot token: grey the button out once clicked.
            echo "<a class='btn btn-primary qrcodelabel-download' href='" . self::e($href) . "'"
               . " onclick=\"var b=this;setTimeout(function(){b.classList.add('disabled');},100);\">"
               . "<i class='fas fa-download me-2'></i>" . self::e($file['label'] ?? '') . "</a>";
         }
         echo "</div>";
         echo "<p class='text-muted small mb-0'>"
            . self::e(__('Each file can be downloaded once; generate the labels again if needed.', 'qrcodelabel'))
            . "</p>";
      }
      echo "</div>";
      if ($back !== '') {
         echo "<div class='card-footer'><a class='btn btn-outline-secondary' href='" . self::e($back) . "'>"
            . "<i class='fas fa-arrow-left me-2'></i>" . self::e(__('Back')) . "</a></div>";
      }
      echo "</div></div>";
   }

   // ── QR code generation ────────────────────────────────────────────────────

   /**
    * QR module matrix (rows of 0/1) for the given data, error correction H.
    *
    * GLPI 11 bundles tc-lib-barcode (same engine and parameters as its native
    * BarcodeManager). GLPI 10 does not: fall back to TCPDF's own QR encoder,
    * shipped with GLPI 10 and 11 (TCPDF2DBarcode).
    *
    * @return array<int, array<int, int>>
    */
   private static function getQrMatrix(string $data): array {
      if (class_exists('\Com\Tecnick\Barcode\Barcode')) {
         $barcode = new \Com\Tecnick\Barcode\Barcode();
         $qrObj   = $barcode->getBarcodeObj('QRCODE,H', $data, -1, -1, 'black', [0, 0, 0, 0]);
         $matrix  = [];
         foreach ($qrObj->getGridArray('0', '1') as $row) {
            $matrix[] = array_map(static function ($v) { return $v === '1' ? 1 : 0; }, array_values($row));
         }
         return $matrix;
      }

      if (class_exists('\TCPDF2DBarcode')) {
         $qr  = new \TCPDF2DBarcode($data, 'QRCODE,H');
         $arr = $qr->getBarcodeArray();
         if (is_array($arr) && !empty($arr['bcode'])) {
            $matrix = [];
            foreach ($arr['bcode'] as $row) {
               $matrix[] = array_map('intval', array_values($row));
            }
            return $matrix;
         }
      }

      return [];
   }

   /**
    * Generate a crisp QR code PNG at high resolution.
    *
    * @return string|false Path to temp PNG file
    */
   private static function generateQrPng(string $url, array $fgColor, array $bgColor) {
      $grid = self::getQrMatrix($url);
      $rows = count($grid);
      $cols = $rows > 0 ? count($grid[0]) : 0;
      if ($rows === 0 || $cols === 0) {
         return false;
      }

      $scale  = 10; // 10px per module → crisp at any print size
      $border = 4;  // quiet zone (same as BarcodeManager padding)
      $imgW   = ($cols + 2 * $border) * $scale;
      $imgH   = ($rows + 2 * $border) * $scale;

      $img = imagecreatetruecolor($imgW, $imgH);
      $bg  = imagecolorallocate($img, $bgColor[0], $bgColor[1], $bgColor[2]);
      $fg  = imagecolorallocate($img, $fgColor[0], $fgColor[1], $fgColor[2]);
      imagefill($img, 0, 0, $bg);

      for ($r = 0; $r < $rows; $r++) {
         for ($c = 0; $c < $cols; $c++) {
            if (!empty($grid[$r][$c])) {
               $px = ($c + $border) * $scale;
               $py = ($r + $border) * $scale;
               imagefilledrectangle($img, $px, $py, $px + $scale - 1, $py + $scale - 1, $fg);
            }
         }
      }

      $uid      = (int)(Session::getLoginUserID() ?: 0);
      $cacheKey = md5($url . implode(',', $fgColor) . implode(',', $bgColor));
      $tmpPath  = GLPI_TMP_DIR . '/qrcodelabel_qr_' . $uid . '_' . $cacheKey . '.png';
      imagepng($img, $tmpPath);
      self::freeImage($img);

      return $tmpPath;
   }

   // ── Logo color processing ────────────────────────────────────────────────

   /**
    * Process logo for different color modes using GD.
    *
    * - inverse / inverse_mono: non-white pixels → white, white bg → transparent
    * - mono: non-white pixels → black, white bg stays white
    * - bw: convert to grayscale
    *
    * Returns path to a temp PNG file.
    */
   private static function processLogo(string $srcPath, string $colorMode): string {
      $uid      = (int)(Session::getLoginUserID() ?: 0);
      $cacheKey = md5($srcPath . $colorMode . (string)@filemtime($srcPath));
      $tmpPath  = GLPI_TMP_DIR . '/qrcodelabel_logo_' . $uid . '_' . $cacheKey . '.png';
      if (file_exists($tmpPath)) {
         return $tmpPath;
      }

      $src = @imagecreatefrompng($srcPath);
      if (!$src) {
         $data = @file_get_contents($srcPath);
         $src  = $data !== false ? @imagecreatefromstring($data) : false;
      }
      if (!$src) {
         return $srcPath; // fallback to original
      }

      $w = imagesx($src);
      $h = imagesy($src);

      $dst = imagecreatetruecolor($w, $h);
      imagesavealpha($dst, true);
      imagealphablending($dst, false);
      $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
      imagefill($dst, 0, 0, $transparent);

      $inverse  = in_array($colorMode, ['inverse', 'inverse_mono'], true);
      $isMono   = in_array($colorMode, ['mono', 'inverse_mono'], true);

      for ($px = 0; $px < $w; $px++) {
         for ($py = 0; $py < $h; $py++) {
            $rgba = imagecolorat($src, $px, $py);
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            $a = ($rgba >> 24) & 0x7F; // 0=opaque, 127=transparent

            // Already transparent → keep transparent
            if ($a > 100) {
               imagesetpixel($dst, $px, $py, $transparent);
               continue;
            }

            $gray = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);

            if ($inverse) {
               // Near-white pixels (bg) → transparent; rest → white
               if ($gray > 240) {
                  imagesetpixel($dst, $px, $py, $transparent);
               } else {
                  $white = imagecolorallocatealpha($dst, 255, 255, 255, $a);
                  imagesetpixel($dst, $px, $py, $white);
               }
            } else if ($isMono) {
               // Near-white → white; rest → black
               if ($gray > 240) {
                  $c = imagecolorallocatealpha($dst, 255, 255, 255, $a);
               } else {
                  $c = imagecolorallocatealpha($dst, 0, 0, 0, $a);
               }
               imagesetpixel($dst, $px, $py, $c);
            } else {
               // bw: grayscale
               $c = imagecolorallocatealpha($dst, $gray, $gray, $gray, $a);
               imagesetpixel($dst, $px, $py, $c);
            }
         }
      }

      imagepng($dst, $tmpPath);
      self::freeImage($src);
      self::freeImage($dst);

      return $tmpPath;
   }

   // ── Text layout (shared by PDF and PNG) ───────────────────────────────────

   /** mm per typographic point. */
   private const PT_MM = 0.3528;

   /**
    * Shorten $text with an ellipsis until it fits $maxW (mm).
    */
   private static function fitText(string $text, float $maxW, float $pt, string $style, callable $measure): string {
      if ($text === '' || $measure($text, $pt, $style) <= $maxW) {
         return $text;
      }
      while (mb_strlen($text) > 1 && $measure($text . '…', $pt, $style) > $maxW) {
         $text = mb_substr($text, 0, -1);
      }
      return rtrim($text) . '…';
   }

   /**
    * Lay out the text column of a label.
    *
    * Every enabled field is kept on every tape size: the line spacing is
    * tightened first, then the whole text block moves up into the top margin,
    * then all fonts shrink together (down to 60 %). Long values are shortened
    * with an ellipsis instead of being silently dropped.
    *
    * @param array    $ts      Tape preset (self::$tapeSizes entry).
    * @param array    $asset   Asset data.
    * @param array    $opt     show_date, show_location, owner_text.
    * @param float    $textW   Width of the text column (mm).
    * @param callable $measure fn(string $text, float $pt, string $style): float — width in mm.
    * @return array<int, array{text:string, pt:float, style:string, color:string, align:string, baseline:float}>
    *               Baselines in mm from the top edge of the label.
    */
   static function layoutText(array $ts, array $asset, array $opt, float $textW, callable $measure): array {
      $top = [
         ['text' => (string)($asset['name'] ?? ''), 'pt' => $ts['font_name'], 'style' => 'B', 'color' => 'main'],
         ['text' => (string)($asset['type_label'] ?? ''), 'pt' => $ts['font_type'], 'style' => '', 'color' => 'sub'],
         ['text' => 'S/N: ' . (($asset['serial'] ?? '') !== '' ? $asset['serial'] : 'N/A'),
          'pt' => $ts['font_sn'], 'style' => 'B', 'color' => 'sn'],
      ];
      if (!empty($opt['show_date']) && ($asset['date_inv'] ?? '') !== '') {
         $top[] = ['text' => 'Inv : ' . $asset['date_inv'], 'pt' => $ts['font_loc'], 'style' => '', 'color' => 'sub'];
      }
      if (!empty($opt['show_location']) && ($asset['location'] ?? '') !== '') {
         $top[] = ['text' => (string)$asset['location'], 'pt' => $ts['font_loc'], 'style' => 'I', 'color' => 'loc'];
      }
      $top = array_values(array_filter($top, static function ($l) { return $l['text'] !== ''; }));

      $owner = trim((string)($opt['owner_text'] ?? ''));
      $inv   = (string)($asset['otherserial'] ?? '');
      $inv   = $inv !== '' ? 'Inv : ' . $inv : '';

      $asc   = 0.75 * self::PT_MM;  // ascender height per pt
      $desc  = 0.22 * self::PT_MM;  // descender depth per pt
      $pitch = 1.2 * self::PT_MM;   // minimal baseline-to-baseline distance per pt
      $kMax  = 1.75;                // airy spacing used when there is room
      $gap   = 1.2;                 // mm between top block and bottom block

      // Attempts [font scale, raise text into the top margin, minimal spacing
      // factor]: nominal position with comfortable spacing, then the text block
      // raised, then all fonts scaled down together (last attempt always wins).
      $attempts = [[1.0, false, 1.3]];
      for ($s = 1.0; $s >= 0.599; $s -= 0.05) {
         $attempts[] = [round($s, 2), true, 1.0];
      }

      $result = [];
      foreach ($attempts as $n => [$scale, $raise, $kMin]) {
         $isLast = ($n === count($attempts) - 1);

         // ── Bottom block: owner (left) + inventory number (right) ──
         $bpt    = $ts['font_inv'] * $scale;
         $bottom = [];
         if ($owner !== '' && $inv !== '') {
            $invW = $measure($inv, $bpt, '');
            if ($measure($owner, $bpt, '') + 2 + $invW <= $textW) {
               $bottom[] = [
                  ['text' => $owner, 'align' => 'L'],
                  ['text' => $inv, 'align' => 'R'],
               ];
            } else {
               $bottom[] = [['text' => self::fitText($owner, $textW, $bpt, '', $measure), 'align' => 'L']];
               $bottom[] = [['text' => self::fitText($inv, $textW, $bpt, '', $measure), 'align' => 'L']];
            }
         } else if ($owner !== '' || $inv !== '') {
            $bottom[] = [['text' => self::fitText($owner !== '' ? $owner : $inv, $textW, $bpt, '', $measure), 'align' => 'L']];
         }

         $bottomBase = (float)$ts['bottom_base'];
         $nb         = count($bottom);
         $firstBottomBase = $bottomBase - ($nb > 1 ? ($nb - 1) * $bpt * $pitch : 0);
         $limit = $nb > 0
            ? $firstBottomBase - $bpt * $asc - $gap
            : $bottomBase + $bpt * $desc;

         // ── Top block ──
         $firstPt  = $top ? $top[0]['pt'] * $scale : 0;
         $nameBase = (float)$ts['name_base'];
         if ($raise && $top) {
            $nameBase = min($nameBase, (float)$ts['text_top'] + $firstPt * $asc);
         }
         $needed = 0.0;
         for ($i = 1, $c = count($top); $i < $c; $i++) {
            $needed += $top[$i]['pt'] * $scale * $pitch;
         }
         $lastDesc = $top ? end($top)['pt'] * $scale * $desc : 0;
         $avail    = $limit - $lastDesc - $nameBase;

         if ($needed > 0 && $needed * $kMin > $avail && !$isLast) {
            continue; // does not fit yet: try next attempt
         }
         $k = $needed > 0 ? min($kMax, max(0.8, $avail / $needed)) : 1.0;

         // ── Emit lines ──
         $y = $nameBase;
         foreach ($top as $i => $line) {
            $pt = $line['pt'] * $scale;
            if ($i > 0) {
               $y += $pt * $pitch * $k;
            }
            $result[] = [
               'text'     => self::fitText($line['text'], $textW, $pt, $line['style'], $measure),
               'pt'       => $pt,
               'style'    => $line['style'],
               'color'    => $line['color'],
               'align'    => 'L',
               'baseline' => $y,
            ];
         }
         $y = $firstBottomBase;
         foreach ($bottom as $row) {
            foreach ($row as $cell) {
               $result[] = [
                  'text'     => $cell['text'],
                  'pt'       => $bpt,
                  'style'    => '',
                  'color'    => 'inv',
                  'align'    => $cell['align'],
                  'baseline' => $y,
               ];
            }
            $y += $bpt * $pitch;
         }
         break;
      }

      return $result;
   }

   // ── PDF Generation ─────────────────────────────────────────────────────────

   /**
    * Generate a PDF with rich QR code labels using GLPI's native TCPDF.
    *
    * @param  array $assets  Array of asset data (or null for blank slots).
    * @param  array $params  Options: tape_size, color_mode, show_date, page_size, orientation.
    * @return string|false   Absolute path to the generated PDF, or false on failure.
    */
   static function printPDF(array $assets, array $params) {

      $tapeSize  = $params['tape_size']  ?? '36mm';
      $colorMode = $params['color_mode'] ?? 'bw';
      $pageSize  = strtoupper($params['page_size'] ?? 'A4');
      $isLandscape = (($params['orientation'] ?? 'Portrait') === 'Landscape');

      if (!isset(self::$tapeSizes[$tapeSize])) {
         $tapeSize = '36mm';
      }
      $ts = self::$tapeSizes[$tapeSize];
      $cm = self::$colorModes[$colorMode] ?? self::$colorModes['bw'];

      $labelW = $ts['label_w'];
      $labelH = $ts['label_h'];
      $qrSize = $ts['qr_size'];

      // Page margins (mm)
      $marginX = 10;
      $marginY = 10;
      $gapY    = 4;

      // ── Load TCPDF ────────────────────────────────────────────────────────
      if (!class_exists('\TCPDF')) {
         $tcpdfPath = GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php';
         if (!file_exists($tcpdfPath)) {
            Session::addMessageAfterRedirect(
               __('TCPDF not found in GLPI vendor directory.', 'qrcodelabel'),
               false, ERROR
            );
            return false;
         }
         require_once $tcpdfPath;
      }

      // ── Logo ──────────────────────────────────────────────────────────────
      $logoPath = GLPI_PLUGIN_DOC_DIR . '/qrcodelabel/logo.png';
      $hasLogo  = file_exists($logoPath);

      // ── TCPDF init ────────────────────────────────────────────────────────
      $orientation = $isLandscape ? 'L' : 'P';
      $pdf = new \TCPDF($orientation, 'mm', $pageSize, true, 'UTF-8', false);
      $pdf->SetCreator('GLPI QR Code Label plugin v' . PLUGIN_QRCODELABEL_VERSION);
      $pdf->SetAuthor('GLPI');
      $pdf->SetTitle(__('QR Code Labels', 'qrcodelabel'));
      $pdf->SetMargins($marginX, $marginY, $marginX, true);
      $pdf->SetAutoPageBreak(false, $marginY);
      $pdf->SetPrintHeader(false);
      $pdf->SetPrintFooter(false);
      $pdf->AddPage();

      $pageW = $pdf->getPageWidth();
      $pageH = $pdf->getPageHeight();

      // Grid calculation
      $cols    = max(1, (int)(($pageW - 2 * $marginX) / $labelW));
      $rows    = max(1, (int)(($pageH - 2 * $marginY) / ($labelH + $gapY)));
      $perPage = $cols * $rows;

      $measure = static function (string $text, float $pt, string $style) use ($pdf): float {
         $pdf->SetFont('helvetica', $style, $pt);
         return (float)$pdf->GetStringWidth($text);
      };
      $layoutOpt = [
         'show_date'     => (bool)($params['show_date'] ?? true),
         'show_location' => (bool)($params['show_location'] ?? false),
         'owner_text'    => (string)($params['owner_text'] ?? ''),
      ];

      // ── Render loop ───────────────────────────────────────────────────────
      foreach (array_values($assets) as $i => $asset) {
         $pi = $i % $perPage;
         if ($i > 0 && $pi === 0) {
            $pdf->AddPage();
         }

         $col = $pi % $cols;
         $row = (int)($pi / $cols);
         $x   = $marginX + $col * $labelW;
         $y   = $marginY + $row * ($labelH + $gapY);

         // Blank slot
         if ($asset === null) {
            continue;
         }

         // ── Background fill (inverse modes) ────────────────────────────────
         if (in_array($colorMode, ['inverse', 'inverse_mono'], true)) {
            $pdf->SetFillColor(0, 0, 0);
            $pdf->Rect($x, $y, $labelW, $labelH, 'F');
         }

         // ── Left column: logo (top) + QR (below) ──────────────────────────
         $leftColX    = $x + 2;
         $topMargin   = $ts['top_m'];
         $logoToQrGap = 0.5;

         if ($hasLogo) {
            $logoHmm = $ts['logo_h'];
            $imgInfo = @getimagesize($logoPath);
            if ($imgInfo && $imgInfo[1] > 0) {
               $ratio    = $imgInfo[0] / $imgInfo[1];
               $cellH    = $ts['logo_h'];
               $maxLogoW = $qrSize + 4;
               $logoW    = $logoHmm * $ratio;
               if ($logoW > $maxLogoW) {
                  $logoW = $maxLogoW;
                  $logoHmm = $logoW / $ratio;
               }
               $logoX = $leftColX + ($qrSize - $logoW) / 2;
               if ($logoX < $x + 0.5) { $logoX = $x + 0.5; }
               // Bottom-align within the cell of height $cellH
               $logoY = $y + $topMargin + ($cellH - $logoHmm);

               $useLogoPath = $logoPath;
               if ($cm['invert_logo'] || $colorMode === 'mono') {
                  $useLogoPath = self::processLogo($logoPath, $colorMode);
               }
               // Let TCPDF detect the image type (older installs may hold a
               // JPEG/GIF saved as logo.png).
               $pdf->Image($useLogoPath, $logoX, $logoY, $logoW, $logoHmm, '', '', '', false, 300);
            }
         }

         // QR positioned below logo (or at top if no logo)
         $qrY   = $y + $topMargin + $ts['logo_h'] + $logoToQrGap;
         $qrPng = !empty($asset['url'])
            ? self::generateQrPng($asset['url'], $cm['qr_fg'], $cm['qr_bg'])
            : false;
         if ($qrPng && file_exists($qrPng)) {
            $pdf->Image($qrPng, $leftColX, $qrY, $qrSize, $qrSize, 'PNG', '', '', false, 300);
         }

         // ── Vertical separator — centred in the gap between QR and text ───
         $qrEnd = $leftColX + $qrSize;
         $tx    = $qrEnd + 7;
         $sx    = $qrEnd + ($tx - $qrEnd) / 2;
         $pdf->SetDrawColor($cm['sep'][0], $cm['sep'][1], $cm['sep'][2]);
         $pdf->SetLineWidth(0.6);
         $pdf->Line($sx, $y + 3, $sx, $y + $labelH - 3);

         // TCPDF cells have ~1 mm inner padding on each side.
         $textW = $labelW - ($tx - $x) - 2;
         $fitW  = $textW - 2 * $pdf->getCellPaddings()['L'];

         // ── Text column ──────────────────────────────────────────────────
         foreach (self::layoutText($ts, $asset, $layoutOpt, $fitW, $measure) as $line) {
            $color = $cm[$line['color']];
            $pdf->SetFont('helvetica', $line['style'], $line['pt']);
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->SetXY($tx, $y + $line['baseline']);
            // calign 'L' + valign 'T': the Y coordinate is the text baseline.
            $pdf->Cell($textW, 0, $line['text'], 0, 0, $line['align'], false, '', 0, false, 'L', 'T');
         }

      } // foreach assets

      // ── Save PDF to temp dir ──────────────────────────────────────────────
      $uid     = (int)(Session::getLoginUserID() ?: 0);
      $pdfFile = 'qrcodelabel_' . $uid . '_' . $tapeSize . '_' . bin2hex(random_bytes(8)) . '.pdf';
      $pdfPath = GLPI_TMP_DIR . '/' . $pdfFile;
      $pdf->Output($pdfPath, 'F');

      self::cleanIntermediateFiles();

      return is_file($pdfPath) ? $pdfPath : false;
   }

   /**
    * Remove this user's intermediate QR/logo PNGs (never another user's, so a
    * concurrent generation is never disturbed).
    */
   private static function cleanIntermediateFiles(): void {
      $uid = (int)(Session::getLoginUserID() ?: 0);
      foreach (['qr', 'logo'] as $kind) {
         foreach (glob(GLPI_TMP_DIR . '/qrcodelabel_' . $kind . '_' . $uid . '_*.png') ?: [] as $f) {
            @unlink($f);
         }
      }
   }

   // ── Font resolution (for GD imagettftext) ───────────────────────────────

   /**
    * Find a usable TTF font for GD text rendering.
    * Looks in the plugin's fonts/ dir first, then common system paths.
    *
    * @param  bool $bold Whether to look for a bold variant.
    * @return string|null Path to a TTF file, or null if none found.
    */
   private static function findFont(bool $bold = false): ?string {
      $pluginFontDir = dirname(__DIR__) . '/fonts';

      $candidates = $bold ? [
         $pluginFontDir . '/NotoSans-Bold.ttf',
         $pluginFontDir . '/LiberationSans-Bold.ttf',
         $pluginFontDir . '/DejaVuSans-Bold.ttf',
         '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
         '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
         '/usr/share/fonts/liberation-sans/LiberationSans-Bold.ttf',
         '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
         '/usr/share/fonts/TTF/LiberationSans-Bold.ttf',
         '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
         'C:/Windows/Fonts/arialbd.ttf',
      ] : [
         $pluginFontDir . '/NotoSans-Regular.ttf',
         $pluginFontDir . '/LiberationSans-Regular.ttf',
         $pluginFontDir . '/DejaVuSans.ttf',
         '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
         '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
         '/usr/share/fonts/liberation-sans/LiberationSans-Regular.ttf',
         '/usr/share/fonts/dejavu/DejaVuSans.ttf',
         '/usr/share/fonts/TTF/LiberationSans-Regular.ttf',
         '/usr/share/fonts/TTF/DejaVuSans.ttf',
         'C:/Windows/Fonts/arial.ttf',
      ];

      foreach ($candidates as $path) {
         if (@file_exists($path) && is_readable($path)) {
            return $path;
         }
      }
      return null;
   }

   // ── PNG generation (one file per asset, for Brother P-Touch Cube) ────────

   /**
    * Generate a single PNG label at exact tape dimensions for Brother label printers.
    *
    * @param  array $asset   Asset data (same shape as printPDF rows).
    * @param  array $params  tape_size, color_mode, show_date, show_location, owner_text.
    * @param  int   $dpi     Render density (default 300).
    * @return string|false   Absolute path to the PNG, or false on failure.
    */
   static function printPNG(array $asset, array $params, int $dpi = 300) {
      $tapeSize  = $params['tape_size']  ?? '36mm';
      $colorMode = $params['color_mode'] ?? 'bw';

      if (!isset(self::$tapeSizes[$tapeSize])) {
         $tapeSize = '36mm';
      }
      $ts = self::$tapeSizes[$tapeSize];
      $cm = self::$colorModes[$colorMode] ?? self::$colorModes['bw'];

      $fontReg  = self::findFont(false);
      $fontBold = self::findFont(true) ?? $fontReg;
      if (!$fontReg) {
         Session::addMessageAfterRedirect(
            __('No TTF font available for PNG export. Install fonts-liberation or fonts-dejavu, or drop a TTF in plugins/qrcodelabel/fonts/.', 'qrcodelabel'),
            false, ERROR
         );
         return false;
      }

      $mmToPx = $dpi / 25.4;
      $ptToPx = $dpi / 72.0;

      $w = (int)round($ts['label_w'] * $mmToPx);
      $h = (int)round($ts['label_h'] * $mmToPx);

      // ── Canvas + background ─────────────────────────────────────────────
      $img = imagecreatetruecolor($w, $h);
      imagealphablending($img, true);
      imagesavealpha($img, true);
      $bg = imagecolorallocate($img, $cm['bg'][0], $cm['bg'][1], $cm['bg'][2]);
      imagefilledrectangle($img, 0, 0, $w, $h, $bg);

      // ── Left column: logo (top) + QR (below) ──────────────────────────
      $leftColPx   = (int)round(2 * $mmToPx);
      $topMarginPx = (int)round($ts['top_m'] * $mmToPx);
      $gapPx       = (int)round(0.5 * $mmToPx);
      $qrPx        = (int)round($ts['qr_size'] * $mmToPx);

      $logoPath = GLPI_PLUGIN_DOC_DIR . '/qrcodelabel/logo.png';
      if (file_exists($logoPath)) {
         $useLogoPath = $logoPath;
         if ($cm['invert_logo'] || $colorMode === 'mono') {
            $useLogoPath = self::processLogo($logoPath, $colorMode);
         }
         $logo = @imagecreatefrompng($useLogoPath);
         if (!$logo) {
            $data = @file_get_contents($useLogoPath);
            $logo = $data ? @imagecreatefromstring($data) : false;
         }
         if ($logo) {
            $lOrigW = imagesx($logo);
            $lOrigH = imagesy($logo);
            if ($lOrigH > 0) {
               $ratio    = $lOrigW / $lOrigH;
               $cellHPx  = (int)round($ts['logo_h'] * $mmToPx);
               $maxLogoW = $qrPx + (int)round(4 * $mmToPx);
               $logoH    = $cellHPx;
               $logoW    = (int)round($logoH * $ratio);
               if ($logoW > $maxLogoW) {
                  $logoW = $maxLogoW;
                  $logoH = (int)round($logoW / $ratio);
               }
               $logoX = $leftColPx + (int)round(($qrPx - $logoW) / 2);
               if ($logoX < 0) { $logoX = 0; }
               $logoY = $topMarginPx + ($cellHPx - $logoH);
               imagealphablending($img, true);
               imagecopyresampled($img, $logo, $logoX, $logoY, 0, 0,
                  $logoW, $logoH, $lOrigW, $lOrigH);
            }
            self::freeImage($logo);
         }
      }

      // QR below logo
      $qrX  = $leftColPx;
      $qrY  = $topMarginPx + (int)round($ts['logo_h'] * $mmToPx) + $gapPx;
      $qrPng = !empty($asset['url'])
         ? self::generateQrPng($asset['url'], $cm['qr_fg'], $cm['qr_bg'])
         : false;
      if ($qrPng && file_exists($qrPng)) {
         $qr = @imagecreatefrompng($qrPng);
         if ($qr) {
            imagecopyresampled($img, $qr, $qrX, $qrY, 0, 0,
               $qrPx, $qrPx, imagesx($qr), imagesy($qr));
            self::freeImage($qr);
         }
      }

      // ── Vertical separator — centred in the gap between QR and text ────
      $qrEndPx = $qrX + $qrPx;
      $tx      = $qrEndPx + (int)round(7 * $mmToPx);
      $sx      = (int)round(($qrEndPx + $tx) / 2);
      $sep     = imagecolorallocate($img, $cm['sep'][0], $cm['sep'][1], $cm['sep'][2]);
      // 0.6 mm wide bar (Brother thermal printers struggle with 1-pixel lines)
      $sepHalf = max(1, (int)round(0.3 * $mmToPx));
      imagefilledrectangle(
         $img,
         $sx - $sepHalf, (int)round(3 * $mmToPx),
         $sx + $sepHalf, $h - (int)round(3 * $mmToPx),
         $sep
      );

      $textWPx = $w - $tx - (int)round(2 * $mmToPx);

      // ── Text column ─────────────────────────────────────────────────────
      $fontFor = static function (string $style) use ($fontReg, $fontBold): string {
         return strpos($style, 'B') !== false ? $fontBold : $fontReg;
      };
      $widthPx = static function (string $text, float $pt, string $style) use ($ptToPx, $fontFor): float {
         if ($text === '') {
            return 0.0;
         }
         $bbox = imagettfbbox($pt * $ptToPx, 0, $fontFor($style), $text);
         return (float)abs($bbox[2] - $bbox[0]);
      };
      $measure = static function (string $text, float $pt, string $style) use ($widthPx, $mmToPx): float {
         return $widthPx($text, $pt, $style) / $mmToPx;
      };
      $layoutOpt = [
         'show_date'     => (bool)($params['show_date'] ?? true),
         'show_location' => (bool)($params['show_location'] ?? false),
         'owner_text'    => (string)($params['owner_text'] ?? ''),
      ];

      foreach (self::layoutText($ts, $asset, $layoutOpt, $textWPx / $mmToPx, $measure) as $line) {
         if ($line['text'] === '') {
            continue;
         }
         $color = $cm[$line['color']];
         $col   = imagecolorallocate($img, $color[0], $color[1], $color[2]);
         $x     = $tx;
         if ($line['align'] === 'R') {
            $x = $tx + $textWPx - (int)ceil($widthPx($line['text'], $line['pt'], $line['style']));
         }
         imagettftext($img, $line['pt'] * $ptToPx, 0, (int)$x, (int)round($line['baseline'] * $mmToPx),
            $col, $fontFor($line['style']), $line['text']);
      }

      // ── Save PNG ────────────────────────────────────────────────────────
      $uid    = (int)(Session::getLoginUserID() ?: 0);
      $safeId = (int)($asset['id'] ?? 0);
      $file   = 'qrcodelabel_' . $uid . '_' . $tapeSize . '_' . $safeId . '_' . bin2hex(random_bytes(8)) . '.png';
      $path   = GLPI_TMP_DIR . '/' . $file;
      imagepng($img, $path);
      self::freeImage($img);

      self::cleanIntermediateFiles();

      return is_file($path) ? $path : false;
   }

   /**
    * Generate one PNG per asset, optionally bundled into a ZIP.
    *
    * - 1 asset  → returns a single PNG path
    * - N assets → returns a ZIP path containing N PNGs (requires PHP zip ext)
    *
    * @param  array $assets  Array of asset data (nulls are skipped).
    * @param  array $params  Same params as printPNG().
    * @return string|false   Path to PNG or ZIP, or false on failure.
    */
   static function printPngBundle(array $assets, array $params) {
      $realAssets = array_values(array_filter($assets, static function ($a) { return $a !== null; }));
      if (empty($realAssets)) {
         return false;
      }

      if (count($realAssets) === 1) {
         return self::printPNG($realAssets[0], $params);
      }

      if (!class_exists('ZipArchive')) {
         Session::addMessageAfterRedirect(
            __('PHP zip extension is required for multi-label PNG export.', 'qrcodelabel'),
            false, ERROR
         );
         return false;
      }

      $pngPaths = [];
      foreach ($realAssets as $i => $asset) {
         $p = self::printPNG($asset, $params);
         if ($p) {
            $pngPaths[$i] = $p;
         }
      }
      if (empty($pngPaths)) {
         return false;
      }

      $tapeSize = $params['tape_size'] ?? '36mm';
      $zipName  = 'qrcodelabel_' . (int)(Session::getLoginUserID() ?: 0) . '_'
                . preg_replace('/[^a-z0-9]/i', '', (string)$tapeSize)
                . '_' . bin2hex(random_bytes(8)) . '.zip';
      $zipPath  = GLPI_TMP_DIR . '/' . $zipName;

      $zip = new \ZipArchive();
      if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
         foreach ($pngPaths as $p) { @unlink($p); }
         return false;
      }

      foreach ($pngPaths as $i => $p) {
         // Keep the PNG <-> asset association even when a PNG failed.
         $asset = $realAssets[$i];
         $id    = (int)($asset['id'] ?? 0);
         $name  = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($asset['name'] ?? 'asset'));
         $name  = substr($name, 0, 40);
         $inZip = sprintf('asset_%d_%s.png', $id, $name);
         $zip->addFile($p, $inZip);
      }
      $zip->close();

      // Clean up individual PNGs — they're now inside the ZIP
      foreach ($pngPaths as $p) { @unlink($p); }

      return is_file($zipPath) ? $zipPath : false;
   }

   // ── Token-based temp-file registry (PDF / PNG / ZIP) ────────────────────

   static function registerTmpFile(string $absPath): string {
      $token = bin2hex(random_bytes(16));
      if (!isset($_SESSION['qrcodelabel_pdf_tokens']) || !is_array($_SESSION['qrcodelabel_pdf_tokens'])) {
         $_SESSION['qrcodelabel_pdf_tokens'] = [];
      }
      // Prune tokens whose file is gone; delete files of this session that were
      // never downloaded and are older than one hour.
      foreach ($_SESSION['qrcodelabel_pdf_tokens'] as $k => $v) {
         if (!is_string($v) || !file_exists($v)) {
            unset($_SESSION['qrcodelabel_pdf_tokens'][$k]);
         } else if (@filemtime($v) < time() - 3600) {
            @unlink($v);
            unset($_SESSION['qrcodelabel_pdf_tokens'][$k]);
         }
      }
      $_SESSION['qrcodelabel_pdf_tokens'][$token] = $absPath;
      return $token;
   }

   static function resolveTmpFile(string $token): ?string {
      if (!isset($_SESSION['qrcodelabel_pdf_tokens'][$token])) {
         return null;
      }
      $path = $_SESSION['qrcodelabel_pdf_tokens'][$token];
      unset($_SESSION['qrcodelabel_pdf_tokens'][$token]);
      return (is_string($path) && file_exists($path)) ? $path : null;
   }

}
