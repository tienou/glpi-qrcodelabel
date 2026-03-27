<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT') && !defined('GLPI_DIR')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Main class for QR Code Label generation using GLPI's native TCPDF.
 *
 * Generates rich inventory labels: QR code + asset name + type + serial number
 * + location + inventory date + company logo + owner text.
 *
 * No external vendor dependencies — TCPDF ships with GLPI 10 and 11.
 * QR codes are rendered as native vector paths (write2DBarcode).
 */
class PluginQrcodelabelLabel {

   static $rightname = 'plugin_qrcodelabel_label';

   /**
    * Label layout presets per tape size (mm).
    * Ported from the Python GUI application.
    */
   private static array $tapeSizes = [
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
      return [
         'title' => self::getTypeName(),
         'page'  => Plugin::getWebDir('qrcodelabel', false) . '/front/config.php',
         'icon'  => 'fas fa-qrcode',
         'links' => [
            'config' => '/front/config.php',
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
      $config = PluginQrcodelabelConfig::getConfig();

      echo "<form name='qrcodelabel_form' method='post' action='"
         . Plugin::getWebDir('qrcodelabel') . "/front/label.form.php'>";

      echo "<input type='hidden' name='itemtype' value='" . htmlspecialchars($itemtype) . "'>";
      echo "<input type='hidden' name='items_id' value='" . (int)$items_id . "'>";

      echo "<div class='center'><table class='tab_cadre'>";
      echo "<tr><th colspan='4'>" . __('Generate QR label', 'qrcodelabel') . "</th></tr>";

      // Tape size
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Tape size', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('tape_size', [
         '25mm' => '25 mm', '36mm' => '36 mm', '50mm' => '50 mm',
      ], ['value' => $config['tape_size'], 'width' => '120']);
      echo "</td>";

      // Color mode
      echo "<td>" . __('Color mode', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('color_mode', [
         'bw'           => __('Black & White', 'qrcodelabel'),
         'mono'         => __('Monochrome', 'qrcodelabel'),
         'color'        => __('Color', 'qrcodelabel'),
         'inverse'      => __('Inverse (white on black)', 'qrcodelabel'),
         'inverse_mono' => __('Inverse Mono', 'qrcodelabel'),
      ], ['value' => $config['color_mode'], 'width' => '200']);
      echo "</td></tr>";

      // Number of copies
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Number of copies', 'qrcodelabel') . "</td><td>";
      echo "<input type='text' name='nb_copies' value='1' size='5'>";
      echo "</td><td colspan='2'></td></tr>";

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

      $config = PluginQrcodelabelConfig::getConfig();

      echo '<center><table>';

      // Tape size
      echo '<tr><td>' . __('Tape size', 'qrcodelabel') . ' : </td><td>';
      Dropdown::showFromArray('tape_size', [
         '25mm' => '25 mm',
         '36mm' => '36 mm',
         '50mm' => '50 mm',
      ], ['value' => $config['tape_size'], 'width' => '120']);
      echo '</td></tr>';

      // Color mode
      echo '<tr><td>' . __('Color mode', 'qrcodelabel') . ' : </td><td>';
      Dropdown::showFromArray('color_mode', [
         'bw'           => __('Black & White', 'qrcodelabel'),
         'mono'         => __('Monochrome', 'qrcodelabel'),
         'color'        => __('Color', 'qrcodelabel'),
         'inverse'      => __('Inverse (white on black)', 'qrcodelabel'),
         'inverse_mono' => __('Inverse Mono', 'qrcodelabel'),
      ], ['value' => $config['color_mode'], 'width' => '200']);
      echo '</td></tr>';

      // Show date
      echo '<tr><td>' . __('Show inventory date', 'qrcodelabel') . ' : </td><td>';
      Dropdown::showYesNo('show_date', $config['show_date'], -1, ['width' => '100']);
      echo '</td></tr>';

      // Sheet-printer-only options
      $isSheet = (($config['printer_type'] ?? 'sheet') === 'sheet');

      if ($isSheet) {
         // Page size
         echo '<tr><td>' . __('Page size', 'qrcodelabel') . ' : </td><td>';
         Dropdown::showFromArray('page_size', [
            'A4' => 'A4', 'A3' => 'A3', 'LETTER' => 'Letter', 'LEGAL' => 'Legal',
         ], ['value' => $config['page_size'], 'width' => '120']);
         echo '</td></tr>';

         // Orientation
         echo '<tr><td>' . __('Orientation', 'qrcodelabel') . ' : </td><td>';
         Dropdown::showFromArray('orientation', [
            'Portrait'  => __('Portrait', 'qrcodelabel'),
            'Landscape' => __('Landscape', 'qrcodelabel'),
         ], ['value' => $config['orientation'], 'width' => '120']);
         echo '</td></tr>';

         // Skip N labels
         echo '<tr><td>' . __('Skip first N labels', 'qrcodelabel') . ' : </td><td>';
         Dropdown::showNumber('eliminate', ['width' => '100']);
         echo '</td></tr>';
      }

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

      $tapeSize  = $input['tape_size']  ?? '36mm';
      $colorMode = $input['color_mode'] ?? 'bw';
      $showDate  = (int)($input['show_date'] ?? 1);
      $pageSize  = $input['page_size']  ?? 'A4';
      $orient    = $input['orientation'] ?? 'Portrait';
      $eliminate = (int)($input['eliminate'] ?? 0);

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

      if (empty($assets)) {
         $ma->itemDone($item->getType(), 0, MassiveAction::ACTION_KO);
         return;
      }

      $config = PluginQrcodelabelConfig::getConfig();

      $pdfPath = self::printPDF($assets, [
         'tape_size'   => $tapeSize,
         'color_mode'  => $colorMode,
         'show_date'   => $showDate,
         'page_size'   => $pageSize,
         'orientation' => $orient,
         'owner_text'  => $config['owner_text'] ?? '',
      ]);

      if ($pdfPath) {
         $token = self::registerTmpPdf($pdfPath);
         $msg   = "<a href='" . Plugin::getWebDir('qrcodelabel') . '/front/send.php?token=' . urlencode($token)
                . "' target='_blank' rel='noopener noreferrer'>"
                . __('Download QR labels', 'qrcodelabel') . "</a>";
         Session::addMessageAfterRedirect($msg);
         $ma->itemDone($item->getType(), 0, MassiveAction::ACTION_OK);
      } else {
         $ma->itemDone($item->getType(), 0, MassiveAction::ACTION_KO);
      }
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

      // Use same library + params as BarcodeManager::generateQRCode() internally
      $barcode = new \Com\Tecnick\Barcode\Barcode();
      $qrObj   = $barcode->getBarcodeObj('QRCODE,H', $url, 200, 200, 'black', [10, 10, 10, 10])
                          ->setBackgroundColor('white');

      $pngData = $qrObj->getPngData(false); // false = GD, no Imagick needed
      $src     = imagecreatefromstring($pngData);
      if (!$src) {
         return false;
      }

      // Apply fg/bg colors via GD (needed for inverse/mono modes)
      $w   = imagesx($src);
      $h   = imagesy($src);
      $dst = imagecreatetruecolor($w, $h);
      $bgC = imagecolorallocate($dst, $bgColor[0], $bgColor[1], $bgColor[2]);
      $fgC = imagecolorallocate($dst, $fgColor[0], $fgColor[1], $fgColor[2]);
      imagefill($dst, 0, 0, $bgC);

      for ($py = 0; $py < $h; $py++) {
         for ($px = 0; $px < $w; $px++) {
            $c   = imagecolorat($src, $px, $py);
            $lum = (($c >> 16 & 0xFF) + ($c >> 8 & 0xFF) + ($c & 0xFF)) / 3;
            if ($lum < 128) {
               imagesetpixel($dst, $px, $py, $fgC);
            }
         }
      }
      imagedestroy($src);

      $cacheKey = md5($url . implode(',', $fgColor) . implode(',', $bgColor));
      $tmpPath  = GLPI_TMP_DIR . '/qrcodelabel_qr_' . $cacheKey . '.png';
      imagepng($dst, $tmpPath);
      imagedestroy($dst);

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
      if (!class_exists('TCPDF')) {
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
      $pdf = new TCPDF($orientation, 'mm', $pageSize, true, 'UTF-8', false);
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
         if ($tapeSize === '25mm') {
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
      $pdfFile = 'qrcodelabel_' . (int)$_SESSION['glpiID'] . '_' . $tapeSize . '_' . mt_rand() . '.pdf';
      $pdfPath = GLPI_TMP_DIR . '/' . $pdfFile;
      $pdf->Output($pdfPath, 'F');

      return $pdfPath;
   }

   // ── Token-based PDF registry ─────────────────────────────────────────────

   static function registerTmpPdf(string $absPath): string {
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

   static function resolveTmpPdf(string $token): ?string {
      if (!isset($_SESSION['qrcodelabel_pdf_tokens'][$token])) {
         return null;
      }
      $path = $_SESSION['qrcodelabel_pdf_tokens'][$token];
      unset($_SESSION['qrcodelabel_pdf_tokens'][$token]);
      return file_exists($path) ? $path : null;
   }

   function getSpecificMassiveActions($checkitem = null): array {
      return [];
   }
}
