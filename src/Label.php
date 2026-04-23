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
 * + location + inventory date + company logo + owner text.
 *
 * No external vendor dependencies — TCPDF ships with GLPI 10 and 11.
 * QR codes are rendered as native vector paths (write2DBarcode).
 */
class Label extends CommonDBTM {

   static $rightname = 'plugin_qrcodelabel_label';

   /**
    * Label layout presets per tape size (mm).
    * Ported from the Python GUI application.
    */
   private static array $tapeSizes = [
      '24mm' => [
         'label_w' => 70, 'label_h' => 24, 'qr_size' => 17,
         'font_name' => 7, 'font_type' => 4.5, 'font_sn' => 5,
         'font_loc' => 4.5, 'font_inv' => 4, 'logo_h' => 7,
      ],
      '25mm' => [
         'label_w' => 70, 'label_h' => 25, 'qr_size' => 18,
         'font_name' => 7.5, 'font_type' => 5, 'font_sn' => 5.5,
         'font_loc' => 5, 'font_inv' => 4.5, 'logo_h' => 8,
      ],
      '36mm' => [
         'label_w' => 80, 'label_h' => 36, 'qr_size' => 26,
         'font_name' => 9, 'font_type' => 5.5, 'font_sn' => 6.5,
         'font_loc' => 6, 'font_inv' => 5.5, 'logo_h' => 12,
      ],
      '50mm' => [
         'label_w' => 90, 'label_h' => 50, 'qr_size' => 36,
         'font_name' => 11, 'font_type' => 7, 'font_sn' => 8,
         'font_loc' => 7, 'font_inv' => 6.5, 'logo_h' => 16,
      ],
   ];

   /**
    * Color themes.
    * Each key maps to: [bg_rgb, qr_fg, qr_bg, text_main, text_sub, text_sn, text_loc, text_inv]
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

   static function getMenuContent(): array {
      $webDir = Plugin::getWebDir('qrcodelabel', false);
      return [
         'title' => self::getTypeName(),
         'page'  => $webDir . '/front/config.php',
         'icon'  => 'fas fa-qrcode',
         'links' => [
            'config' => $webDir . '/front/config.php',
         ],
      ];
   }

   // ── Tab on asset forms (single-item label generation) ────────────────────

   /**
    * Show a "QR Label" tab on each supported asset type.
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if (in_array($item->getType(), PLUGIN_QRCODELABEL_ITEMTYPES, true)
            && $item->getID() > 0
            && Session::haveRight(self::$rightname, CREATE)) {
         return self::createTabEntry(__('QR Label', 'qrcodelabel'));
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if (in_array($item->getType(), PLUGIN_QRCODELABEL_ITEMTYPES, true)) {
         self::showSingleItemForm($item->getType(), $item->getID());
      }
      return true;
   }

   /**
    * Show the single-item label generation form on an asset tab.
    */
   static function showSingleItemForm(string $itemtype, int $items_id): void {
      $profiles = Printprofile::getProfiles();
      $defaultProfile = Printprofile::getDefault();
      $defaultId = $defaultProfile ? (int)$defaultProfile['id'] : 0;

      echo "<form name='qrcodelabel_form' method='post' action='"
         . Plugin::getWebDir('qrcodelabel') . "/front/label.form.php'>";

      echo "<input type='hidden' name='itemtype' value='" . htmlspecialchars($itemtype) . "'>";
      echo "<input type='hidden' name='items_id' value='" . (int)$items_id . "'>";

      echo "<div class='center'><table class='tab_cadre'>";
      echo "<tr><th colspan='4'>" . __('Generate QR label', 'qrcodelabel') . "</th></tr>";

      // Print profile
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Print profile', 'qrcodelabel') . "</td><td>";
      if (empty($profiles)) {
         echo "<em>" . __('No print profile configured.', 'qrcodelabel') . "</em>";
      } else {
         Dropdown::showFromArray('profile_id', $profiles, [
            'value' => $defaultId,
            'width' => '200',
         ]);
      }
      echo "</td>";

      // Number of copies
      echo "<td>" . __('Number of copies', 'qrcodelabel') . "</td><td>";
      echo "<input type='text' name='nb_copies' value='1' size='5'>";
      echo "</td></tr>";

      // Output format (PDF / PNG / both)
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Output format', 'qrcodelabel') . "</td><td colspan='3'>";
      Dropdown::showFromArray('output_format', [
         'pdf'  => __('PDF (label sheet)', 'qrcodelabel'),
         'png'  => __('PNG (Brother P-Touch Cube)', 'qrcodelabel'),
         'both' => __('Both (PDF + PNG)', 'qrcodelabel'),
      ], [
         'value' => 'pdf',
         'width' => '250',
      ]);
      echo "</td></tr>";

      // Generate button
      echo "<tr><td class='tab_bg_1' colspan='4' align='center'>";
      echo "<input type='submit' value='" . __('Generate', 'qrcodelabel') . "' class='submit'>";
      echo "</td></tr>";

      echo "</table></div>";
      Html::closeForm();
   }

   // ── Massive Action form ────────────────────────────────────────────────────

   static function showMassiveActionsSubForm(MassiveAction $ma): bool {
      if ($ma->getAction() !== 'GenerateLabels') {
         return false;
      }

      $profiles = Printprofile::getProfiles();
      $defaultProfile = Printprofile::getDefault();
      $defaultId = $defaultProfile ? (int)$defaultProfile['id'] : 0;

      echo '<center><table>';

      // Print profile
      echo '<tr><td>' . __('Print profile', 'qrcodelabel') . ' : </td><td>';
      if (empty($profiles)) {
         echo '<em>' . __('No print profile configured.', 'qrcodelabel') . '</em>';
      } else {
         Dropdown::showFromArray('profile_id', $profiles, [
            'value' => $defaultId,
            'width' => '200',
         ]);
      }
      echo '</td></tr>';

      // Skip N labels (specific to each print job, not part of profile)
      echo '<tr><td>' . __('Skip first N labels', 'qrcodelabel') . ' : </td><td>';
      Dropdown::showNumber('eliminate', ['width' => '100']);
      echo '</td></tr>';

      // Output format
      echo '<tr><td>' . __('Output format', 'qrcodelabel') . ' : </td><td>';
      Dropdown::showFromArray('output_format', [
         'pdf'  => __('PDF (label sheet)', 'qrcodelabel'),
         'png'  => __('PNG (Brother P-Touch Cube)', 'qrcodelabel'),
         'both' => __('Both (PDF + PNG)', 'qrcodelabel'),
      ], [
         'value' => 'pdf',
         'width' => '250',
      ]);
      echo '</td></tr>';

      echo '</table></center><br/>';
      echo Html::submit(__('Generate', 'qrcodelabel'), ['value' => 'generate']);

      return true;
   }

   // ── Massive Action processing ──────────────────────────────────────────────

   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids): void {
      if ($ma->getAction() !== 'GenerateLabels') {
         return;
      }

      global $CFG_GLPI;
      $input = $ma->getInput();

      // Load print profile from DB
      $profileId = (int)($input['profile_id'] ?? 0);
      $profile   = Printprofile::getProfileById($profileId);
      if (!$profile) {
         // Fallback to default profile
         $profile = Printprofile::getDefault();
      }
      if (!$profile) {
         Session::addMessageAfterRedirect(
            __('No print profile found.', 'qrcodelabel'),
            false, ERROR
         );
         $ma->itemDone($item->getType(), 0, MassiveAction::ACTION_KO);
         return;
      }

      $tapeSize  = $profile['tape_size'];
      $colorMode = $profile['color_mode'];
      $showDate  = (int)$profile['show_date'];
      $pageSize  = $profile['page_size'];
      $orient    = $profile['orientation'];
      $eliminate = max(0, min(100, (int)($input['eliminate'] ?? 0)));
      $format    = in_array($input['output_format'] ?? 'pdf', ['pdf', 'png', 'both'], true)
         ? $input['output_format'] : 'pdf';

      // Build asset data array
      $assets = [];

      // Prepend blank slots (for partially used label sheets)
      for ($i = 0; $i < $eliminate; $i++) {
         $assets[] = null;
      }

      foreach ($ids as $id) {
         if (!$item->getFromDB($id)) {
            continue;
         }

         $itemtype = $item->getType();

         // Build URL exactly as GLPI native QR does
         $url = $CFG_GLPI['url_base'] . $itemtype::getFormURLWithID($id, false);

         // Get location name
         $location = '';
         if ($item->isField('locations_id') && $item->fields['locations_id'] > 0) {
            $loc = new Location();
            if ($loc->getFromDB($item->fields['locations_id'])) {
               $location = $loc->fields['completename'] ?? $loc->fields['name'] ?? '';
            }
         }

         // Get type label (translated)
         $typeLabel = $itemtype::getTypeName(1);

         // Get first inventory date
         $dateInv = '';
         $dateRaw = $item->fields['date_creation'] ?? '';
         if ($dateRaw) {
            $dateInv = substr($dateRaw, 0, 10);
         }

         $assets[] = [
            'itemtype'    => $itemtype,
            'id'          => (int)$id,
            'name'        => $item->fields['name'] ?? __('No name'),
            'serial'      => $item->fields['serial'] ?? '',
            'otherserial' => $item->fields['otherserial'] ?? '',
            'type_label'  => $typeLabel,
            'location'    => $location,
            'date_inv'    => $dateInv,
            'url'         => $url,
         ];
      }

      // Check that we have at least one real asset (not just null skip slots)
      $realAssets = array_filter($assets, static function ($a) { return $a !== null; });
      if (empty($realAssets)) {
         $ma->itemDone($item->getType(), 0, MassiveAction::ACTION_KO);
         return;
      }

      $config = Config::getConfig();
      $params = [
         'tape_size'   => $tapeSize,
         'color_mode'  => $colorMode,
         'show_date'   => $showDate,
         'page_size'   => $pageSize,
         'orientation' => $orient,
         'owner_text'  => $config['owner_text'] ?? '',
      ];

      $ok = self::emitDownloadLinks($assets, $params, $format);
      $ma->itemDone($item->getType(), 0,
         $ok ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO);
   }

   /**
    * Produce PDF and/or PNG outputs and queue Session flash messages with
    * download links.
    *
    * @param  array  $assets  Asset data (may contain nulls for blank slots — PDF only).
    * @param  array  $params  Shared params (tape_size, color_mode, etc.).
    * @param  string $format  'pdf', 'png', or 'both'.
    * @return bool            true if at least one output succeeded.
    */
   static function emitDownloadLinks(array $assets, array $params, string $format): bool {
      $webDir = Plugin::getWebDir('qrcodelabel');
      $ok     = false;

      if ($format === 'pdf' || $format === 'both') {
         $pdfPath = self::printPDF($assets, $params);
         if ($pdfPath) {
            $token = self::registerTmpFile($pdfPath);
            $msg   = "<a href='" . $webDir . '/front/send.php?token=' . urlencode($token)
                   . "' target='_blank' rel='noopener noreferrer'>"
                   . __('Download QR labels (PDF)', 'qrcodelabel') . "</a>";
            Session::addMessageAfterRedirect($msg);
            $ok = true;
         }
      }

      if ($format === 'png' || $format === 'both') {
         // Dedupe by itemtype+id so N copies of the same asset produce 1 PNG
         // (Brother Cube users re-print via iPrint&Label — no point shipping
         // duplicates in a ZIP).
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
            $token = self::registerTmpFile($pngPath);
            $label = (substr($pngPath, -4) === '.zip')
               ? __('Download QR labels (ZIP of PNGs)', 'qrcodelabel')
               : __('Download QR label (PNG)', 'qrcodelabel');
            $msg   = "<a href='" . $webDir . '/front/send.php?token=' . urlencode($token)
                   . "' target='_blank' rel='noopener noreferrer'>" . $label . "</a>";
            Session::addMessageAfterRedirect($msg);
            $ok = true;
         }
      }

      return $ok;
   }

   // ── High-res QR code generation via GD (sharp like Python app) ──────────

   /**
    * Generate a crisp QR code PNG at high resolution using TCPDF's QR engine.
    *
    * @param  string $data     Data to encode
    * @param  array  $fgColor  [r,g,b] foreground
    * @param  array  $bgColor  [r,g,b] background
    * @return string|false     Path to temp PNG file
    */
   private static function generateQrPng(string $itemtype, int $id, array $fgColor, array $bgColor) {
      global $CFG_GLPI;

      // Build same URL as GLPI's BarcodeManager
      $url = $CFG_GLPI['url_base'] . $itemtype::getFormURLWithID($id, false);

      // Use same library + params as BarcodeManager::generateQRCode() (QRCODE,H)
      $barcode = new \Com\Tecnick\Barcode\Barcode();
      $qrObj   = $barcode->getBarcodeObj('QRCODE,H', $url, -1, -1, 'black', [0, 0, 0, 0]);

      // Get raw matrix and render with GD at high resolution
      $grid = $qrObj->getGridArray('0', '1');
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
            if (isset($grid[$r][$c]) && $grid[$r][$c] === '1') {
               $px = ($c + $border) * $scale;
               $py = ($r + $border) * $scale;
               imagefilledrectangle($img, $px, $py, $px + $scale - 1, $py + $scale - 1, $fg);
            }
         }
      }

      $cacheKey = md5($url . implode(',', $fgColor) . implode(',', $bgColor));
      $tmpPath  = GLPI_TMP_DIR . '/qrcodelabel_qr_' . $cacheKey . '.png';
      imagepng($img, $tmpPath);
      imagedestroy($img);

      return $tmpPath;
   }

   // ── Logo color processing (matches Python exe logic) ────────────────────

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
      $cacheKey = md5($srcPath . $colorMode);
      $tmpPath  = GLPI_TMP_DIR . '/qrcodelabel_logo_' . $cacheKey . '.png';
      if (file_exists($tmpPath)) {
         return $tmpPath;
      }

      $src = @imagecreatefrompng($srcPath);
      if (!$src) {
         // Try JPEG/other
         $src = @imagecreatefromstring(file_get_contents($srcPath));
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

      $inverse  = in_array($colorMode, ['inverse', 'inverse_mono']);
      $isMono   = in_array($colorMode, ['mono', 'inverse_mono']);

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
      imagedestroy($src);
      imagedestroy($dst);

      return $tmpPath;
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
      $showDate  = (bool)($params['show_date'] ?? true);
      $pageSize  = strtoupper($params['page_size'] ?? 'A4');
      $isLandscape = (($params['orientation'] ?? 'Portrait') === 'Landscape');
      $ownerText = trim($params['owner_text'] ?? '');

      $ts = self::$tapeSizes[$tapeSize] ?? self::$tapeSizes['36mm'];
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

      // ── Render loop ───────────────────────────────────────────────────────
      foreach ($assets as $i => $asset) {
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
         $inverse = in_array($colorMode, ['inverse', 'inverse_mono']);
         if ($inverse) {
            $pdf->SetFillColor(0, 0, 0);
            $pdf->Rect($x, $y, $labelW, $labelH, 'F');
         }

         // ── Border ─────────────────────────────────────────────────────────
         $pdf->SetDrawColor($cm['border'][0], $cm['border'][1], $cm['border'][2]);
         $pdf->SetLineWidth(0.5);
         $pdf->Rect($x, $y, $labelW, $labelH, 'D');

         // ── QR code — GLPI native (BarcodeManager) ────────────────────────
         $qrX = $x + 3;
         $qrY = $y + ($labelH - $qrSize) / 2;
         $qrPng = (!empty($asset['itemtype']) && !empty($asset['id']))
            ? self::generateQrPng($asset['itemtype'], $asset['id'], $cm['qr_fg'], $cm['qr_bg'])
            : false;
         if ($qrPng && file_exists($qrPng)) {
            $pdf->Image($qrPng, $qrX, $qrY, $qrSize, $qrSize, 'PNG', '', '', false, 300);
         }

         // ── Vertical separator ─────────────────────────────────────────────
         $sx = $x + 3 + $qrSize + 2;
         $pdf->SetDrawColor($cm['sep'][0], $cm['sep'][1], $cm['sep'][2]);
         $pdf->SetLineWidth(0.3);
         $pdf->Line($sx, $y + 3, $sx, $y + $labelH - 3);

         // Text area starts here
         $tx = $sx + 3;
         $textW = $labelW - ($tx - $x) - 2;

         // ── Logo (top-right corner, color-adapted like Python) ─────────────
         if ($hasLogo) {
            $logoH = $ts['logo_h'];
            $logoMaxW = $textW;
            $imgInfo = @getimagesize($logoPath);
            if ($imgInfo && $imgInfo[1] > 0) {
               $ratio = $imgInfo[0] / $imgInfo[1];
               $logoW = $logoH * $ratio;
               if ($logoW > $logoMaxW) {
                  $logoW = $logoMaxW;
                  $logoH = $logoW / $ratio;
               }
               $logoX = $x + $labelW - $logoW - 2;
               $logoY = $y + 1;

               // Process logo per color mode (same logic as Python exe)
               $useLogoPath = $logoPath;
               if ($cm['invert_logo'] || $colorMode === 'mono') {
                  $useLogoPath = self::processLogo($logoPath, $colorMode);
               }
               $pdf->Image($useLogoPath, $logoX, $logoY, $logoW, $logoH, 'PNG', '', '', false, 300);
            }
         }

         // ── Text fields — fixed mm positions from Python ReportLab ─────────
         // Python (bottom-up):  drawString(tx, y + lh - Nmm)
         // TCPDF  (top-down):   SetXY(tx, y + Nmm - baseline_offset)
         // TCPDF baseline offset ≈ font_pt * 0.35 (ascender)

         $maxChars = (int)($labelW * 0.22);
         $name = $asset['name'];
         if (mb_strlen($name) > $maxChars + 1) {
            $name = mb_substr($name, 0, $maxChars) . '...';
         }

         // Fixed offsets per tape size (from top of label, in mm)
         // Derived from Python: offset_from_top = lh - python_offset
         // 36mm: name@10, type@14, sn@19.5, date@24, loc@28, bottom@lh-3
         // 25mm: name@7, type@10, sn@13.5, bottom@lh-3
         // 50mm: name@12, type@17, sn@24, date@30, loc@35, bottom@lh-3
         if ($tapeSize === '25mm' || $tapeSize === '24mm') {
            $oName = 7; $oType = 10; $oSn = 13.5; $oDate = 0; $oLoc = 16;
         } else if ($tapeSize === '50mm') {
            $oName = 12; $oType = 17; $oSn = 24; $oDate = 30; $oLoc = 35;
         } else { // 36mm
            $oName = 10; $oType = 14; $oSn = 19.5; $oDate = 24; $oLoc = 28;
         }

         // Asset name (bold)
         $pdf->SetFont('helvetica', 'B', $ts['font_name']);
         $pdf->SetTextColor($cm['main'][0], $cm['main'][1], $cm['main'][2]);
         $pdf->SetXY($tx, $y + $oName - $ts['font_name'] * 0.35);
         $pdf->Cell($textW, 0, $name, 0, 0, 'L');

         // Type
         $pdf->SetFont('helvetica', '', $ts['font_type']);
         $pdf->SetTextColor($cm['sub'][0], $cm['sub'][1], $cm['sub'][2]);
         $pdf->SetXY($tx, $y + $oType - $ts['font_type'] * 0.35);
         $pdf->Cell($textW, 0, $asset['type_label'], 0, 0, 'L');

         // Serial number
         $pdf->SetFont('helvetica', 'B', $ts['font_sn']);
         $pdf->SetTextColor($cm['sn'][0], $cm['sn'][1], $cm['sn'][2]);
         $sn = $asset['serial'] ?: 'N/A';
         $pdf->SetXY($tx, $y + $oSn - $ts['font_sn'] * 0.35);
         $pdf->Cell($textW, 0, 'S/N: ' . mb_substr($sn, 0, 20), 0, 0, 'L');

         // Inventory date (not on 25mm)
         $dateInv = $asset['date_inv'] ?? '';
         $hasDate = ($dateInv && $tapeSize !== '25mm' && $showDate && $oDate > 0);
         if ($hasDate) {
            $pdf->SetFont('helvetica', '', $ts['font_loc']);
            $pdf->SetTextColor($cm['sub'][0], $cm['sub'][1], $cm['sub'][2]);
            $pdf->SetXY($tx, $y + $oDate - $ts['font_loc'] * 0.35);
            $pdf->Cell($textW, 0, 'Inv: ' . $dateInv, 0, 0, 'L');
         }

         // Location
         $location = $asset['location'] ?? '';
         if ($location && $tapeSize !== '25mm') {
            $locOffset = $hasDate ? $oLoc : $oDate;
            if ($locOffset > 0) {
               $pdf->SetFont('helvetica', 'I', $ts['font_loc']);
               $pdf->SetTextColor($cm['loc'][0], $cm['loc'][1], $cm['loc'][2]);
               $pdf->SetXY($tx, $y + $locOffset - $ts['font_loc'] * 0.35);
               $pdf->Cell($textW, 0, mb_substr($location, 0, 22), 0, 0, 'L');
            }
         }

         // ── Bottom line: owner text (left) + inventory number (right)
         $inv      = $asset['otherserial'] ?? '';
         $hasOwner = ($ownerText !== '');
         $hasInv   = ($inv !== '');
         if (($hasOwner || $hasInv) && $tapeSize !== '25mm') {
            $bottomY = $y + $labelH - 3 - ($ts['font_inv'] * 0.35);
            $pdf->SetFont('helvetica', '', $ts['font_inv']);
            $pdf->SetTextColor($cm['inv'][0], $cm['inv'][1], $cm['inv'][2]);
            if ($hasOwner && $hasInv) {
               // Owner text left, inv number right
               $invStr = 'Inv: ' . $inv;
               $pdf->SetXY($tx, $bottomY);
               $pdf->Cell($textW, 0, $ownerText, 0, 0, 'L');
               // Overlay inv number right-aligned on same line
               $pdf->SetXY($tx, $bottomY);
               $pdf->Cell($textW, 0, $invStr, 0, 0, 'R');
            } else if ($hasOwner) {
               $pdf->SetXY($tx, $bottomY);
               $pdf->Cell($textW, 0, $ownerText, 0, 0, 'L');
            } else {
               $pdf->SetXY($tx, $bottomY);
               $pdf->Cell($textW, 0, 'Inv: ' . $inv, 0, 0, 'L');
            }
         }

      } // foreach assets

      // ── Save PDF to temp dir ──────────────────────────────────────────────
      $pdfFile = 'qrcodelabel_' . (int)(Session::getLoginUserID() ?: 0) . '_' . $tapeSize . '_' . mt_rand() . '.pdf';
      $pdfPath = GLPI_TMP_DIR . '/' . $pdfFile;
      $pdf->Output($pdfPath, 'F');

      // ── Clean up temporary QR and logo PNGs ────────────────────────────────
      $tmpFiles = glob(GLPI_TMP_DIR . '/qrcodelabel_qr_*.png');
      if ($tmpFiles) {
         foreach ($tmpFiles as $tmpFile) {
            @unlink($tmpFile);
         }
      }
      $tmpLogos = glob(GLPI_TMP_DIR . '/qrcodelabel_logo_*.png');
      if ($tmpLogos) {
         foreach ($tmpLogos as $tmpFile) {
            @unlink($tmpFile);
         }
      }

      return $pdfPath;
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
      $pluginFontDir = Plugin::getPhpDir('qrcodelabel') . '/fonts';

      $candidates = $bold ? [
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
    * Output: one PNG per asset in GLPI_TMP_DIR. Uses the same visual layout
    * as printPDF() but rendered via GD (no TCPDF page layout).
    *
    * @param  array $asset   Asset data (same shape as printPDF rows).
    * @param  array $params  tape_size, color_mode, show_date, owner_text.
    * @param  int   $dpi     Render density (default 300).
    * @return string|false   Absolute path to the PNG, or false on failure.
    */
   static function printPNG(array $asset, array $params, int $dpi = 300) {
      $tapeSize  = $params['tape_size']  ?? '36mm';
      $colorMode = $params['color_mode'] ?? 'bw';
      $showDate  = (bool)($params['show_date'] ?? true);
      $ownerText = trim($params['owner_text'] ?? '');

      $ts = self::$tapeSizes[$tapeSize] ?? self::$tapeSizes['36mm'];
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

      // ── Border (1px) ────────────────────────────────────────────────────
      $border = imagecolorallocate($img, $cm['border'][0], $cm['border'][1], $cm['border'][2]);
      imagerectangle($img, 0, 0, $w - 1, $h - 1, $border);

      // ── QR code ─────────────────────────────────────────────────────────
      $qrPx = (int)round($ts['qr_size'] * $mmToPx);
      $qrX  = (int)round(3 * $mmToPx);
      $qrY  = (int)round((($ts['label_h'] - $ts['qr_size']) / 2) * $mmToPx);
      $qrPng = (!empty($asset['itemtype']) && !empty($asset['id']))
         ? self::generateQrPng($asset['itemtype'], $asset['id'], $cm['qr_fg'], $cm['qr_bg'])
         : false;
      if ($qrPng && file_exists($qrPng)) {
         $qr = @imagecreatefrompng($qrPng);
         if ($qr) {
            imagecopyresampled($img, $qr, $qrX, $qrY, 0, 0,
               $qrPx, $qrPx, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
         }
      }

      // ── Vertical separator ──────────────────────────────────────────────
      $sx = $qrX + $qrPx + (int)round(2 * $mmToPx);
      $sep = imagecolorallocate($img, $cm['sep'][0], $cm['sep'][1], $cm['sep'][2]);
      imageline($img, $sx, (int)round(3 * $mmToPx), $sx, $h - (int)round(3 * $mmToPx), $sep);

      $tx    = $sx + (int)round(3 * $mmToPx);
      $textW = $w - $tx - (int)round(2 * $mmToPx);

      // ── Logo (top-right corner) ─────────────────────────────────────────
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
               $ratio = $lOrigW / $lOrigH;
               $logoH = (int)round($ts['logo_h'] * $mmToPx);
               $logoW = (int)round($logoH * $ratio);
               if ($logoW > $textW) {
                  $logoW = $textW;
                  $logoH = (int)round($logoW / $ratio);
               }
               $logoX = $w - $logoW - (int)round(2 * $mmToPx);
               $logoY = (int)round(1 * $mmToPx);
               imagealphablending($img, true);
               imagecopyresampled($img, $logo, $logoX, $logoY, 0, 0,
                  $logoW, $logoH, $lOrigW, $lOrigH);
            }
            imagedestroy($logo);
         }
      }

      // ── Text offsets (same logic as printPDF) ───────────────────────────
      if ($tapeSize === '25mm' || $tapeSize === '24mm') {
         $oName = 7; $oType = 10; $oSn = 13.5; $oDate = 0; $oLoc = 16;
      } else if ($tapeSize === '50mm') {
         $oName = 12; $oType = 17; $oSn = 24; $oDate = 30; $oLoc = 35;
      } else { // 36mm
         $oName = 10; $oType = 14; $oSn = 19.5; $oDate = 24; $oLoc = 28;
      }

      $maxChars = (int)($ts['label_w'] * 0.22);
      $name = $asset['name'];
      if (mb_strlen($name) > $maxChars + 1) {
         $name = mb_substr($name, 0, $maxChars) . '...';
      }

      // Helper: draw text with baseline at Y (mm from top of label)
      $drawText = static function (
         $text, float $baselineMm, float $fontPt, array $color, bool $bold = false
      ) use (&$img, $tx, $mmToPx, $ptToPx, $fontReg, $fontBold): void {
         if ($text === '' || $text === null) {
            return;
         }
         $size = $fontPt * $ptToPx;
         $y    = (int)round($baselineMm * $mmToPx);
         $col  = imagecolorallocate($img, $color[0], $color[1], $color[2]);
         imagettftext($img, $size, 0, $tx, $y, $col,
            $bold ? $fontBold : $fontReg, $text);
      };

      // Name (bold)
      $drawText($name, $oName, $ts['font_name'], $cm['main'], true);

      // Type
      $drawText($asset['type_label'] ?? '', $oType, $ts['font_type'], $cm['sub'], false);

      // Serial
      $sn = $asset['serial'] ?: 'N/A';
      $drawText('S/N: ' . mb_substr($sn, 0, 20), $oSn, $ts['font_sn'], $cm['sn'], true);

      // Date (not on 24/25mm)
      $dateInv = $asset['date_inv'] ?? '';
      $hasDate = ($dateInv && $tapeSize !== '25mm' && $tapeSize !== '24mm' && $showDate && $oDate > 0);
      if ($hasDate) {
         $drawText('Inv: ' . $dateInv, $oDate, $ts['font_loc'], $cm['sub'], false);
      }

      // Location
      $location = $asset['location'] ?? '';
      if ($location && $tapeSize !== '25mm' && $tapeSize !== '24mm') {
         $locOffset = $hasDate ? $oLoc : $oDate;
         if ($locOffset > 0) {
            $drawText(mb_substr($location, 0, 22), $locOffset, $ts['font_loc'], $cm['loc'], false);
         }
      }

      // Bottom line: owner (left) + inv number (right)
      $inv      = $asset['otherserial'] ?? '';
      $hasOwner = ($ownerText !== '');
      $hasInv   = ($inv !== '');
      if (($hasOwner || $hasInv) && $tapeSize !== '25mm' && $tapeSize !== '24mm') {
         $bottomY = $ts['label_h'] - 3;
         $invStr  = 'Inv: ' . $inv;
         $col     = imagecolorallocate($img, $cm['inv'][0], $cm['inv'][1], $cm['inv'][2]);
         $fontSizePx = $ts['font_inv'] * $ptToPx;

         if ($hasOwner) {
            imagettftext($img, $fontSizePx, 0, $tx, (int)round($bottomY * $mmToPx),
               $col, $fontReg, $ownerText);
         }
         if ($hasInv) {
            $bbox = imagettfbbox($fontSizePx, 0, $fontReg, $invStr);
            $strPx = abs($bbox[2] - $bbox[0]);
            $invX  = $w - (int)round(2 * $mmToPx) - $strPx;
            if ($hasOwner && $invX < $tx) {
               $invX = $tx; // fallback: no room for right-align, skip overlap
            }
            imagettftext($img, $fontSizePx, 0, $invX, (int)round($bottomY * $mmToPx),
               $col, $fontReg, $invStr);
         }
      }

      // ── Save PNG ────────────────────────────────────────────────────────
      $safeId = (int)($asset['id'] ?? 0);
      $file   = 'qrcodelabel_' . (int)(Session::getLoginUserID() ?: 0) . '_' . $tapeSize
              . '_' . $safeId . '_' . mt_rand() . '.png';
      $path   = GLPI_TMP_DIR . '/' . $file;
      imagepng($img, $path);
      imagedestroy($img);

      // ── Clean up intermediate QR / logo PNGs ────────────────────────────
      foreach (glob(GLPI_TMP_DIR . '/qrcodelabel_qr_*.png') ?: [] as $f) {
         @unlink($f);
      }
      foreach (glob(GLPI_TMP_DIR . '/qrcodelabel_logo_*.png') ?: [] as $f) {
         @unlink($f);
      }

      return $path;
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
      foreach ($realAssets as $asset) {
         $p = self::printPNG($asset, $params);
         if ($p) {
            $pngPaths[] = $p;
         }
      }
      if (empty($pngPaths)) {
         return false;
      }

      $tapeSize = $params['tape_size'] ?? '36mm';
      $zipName  = 'qrcodelabel_' . (int)(Session::getLoginUserID() ?: 0) . '_' . $tapeSize
                . '_' . mt_rand() . '.zip';
      $zipPath  = GLPI_TMP_DIR . '/' . $zipName;

      $zip = new \ZipArchive();
      if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
         foreach ($pngPaths as $p) { @unlink($p); }
         return false;
      }

      foreach ($pngPaths as $i => $p) {
         $asset = $realAssets[$i] ?? [];
         $id    = (int)($asset['id'] ?? 0);
         $name  = preg_replace('/[^A-Za-z0-9_\-]/', '_',
                               (string)($asset['name'] ?? 'asset'));
         $name  = mb_substr($name, 0, 40);
         $inZip = sprintf('asset_%d_%s.png', $id, $name);
         $zip->addFile($p, $inZip);
      }
      $zip->close();

      // Clean up individual PNGs — they're now inside the ZIP
      foreach ($pngPaths as $p) { @unlink($p); }

      return $zipPath;
   }

   // ── Token-based temp-file registry (PDF / PNG / ZIP) ────────────────────

   static function registerTmpFile(string $absPath): string {
      $token = bin2hex(random_bytes(16));
      if (!isset($_SESSION['qrcodelabel_pdf_tokens']) || !is_array($_SESSION['qrcodelabel_pdf_tokens'])) {
         $_SESSION['qrcodelabel_pdf_tokens'] = [];
      }
      // Prune stale tokens
      foreach ($_SESSION['qrcodelabel_pdf_tokens'] as $k => $v) {
         if (!file_exists($v)) {
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
      return file_exists($path) ? $path : null;
   }

}
