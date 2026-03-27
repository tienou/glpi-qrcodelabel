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

      // Owner text + copies
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Owner text', 'qrcodelabel') . "</td><td>";
      echo "<input type='text' name='owner_text' value='"
         . htmlspecialchars($config['owner_text']) . "' size='25'>";
      echo "</td>";
      echo "<td>" . __('Number of copies', 'qrcodelabel') . "</td><td>";
      echo "<input type='text' name='nb_copies' value='1' size='5'>";
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

      // Owner text
      echo '<tr><td>' . __('Owner text', 'qrcodelabel') . ' : </td><td>';
      echo '<input type="text" name="owner_text" value="' . htmlspecialchars($config['owner_text']) . '" size="30">';
      echo '</td></tr>';

      // Show date
      echo '<tr><td>' . __('Show inventory date', 'qrcodelabel') . ' : </td><td>';
      Dropdown::showYesNo('show_date', $config['show_date'], -1, ['width' => '100']);
      echo '</td></tr>';

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
      $ownerText = $input['owner_text'] ?? '';
      $showDate  = (int)($input['show_date'] ?? 1);
      $pageSize  = $input['page_size']  ?? 'A4';
      $orient    = $input['orientation'] ?? 'Portrait';
      $eliminate = (int)($input['eliminate'] ?? 0);

      // Build asset data array
      $assets = [];

      // Prepend blank slots
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

      $pdfPath = self::printPDF($assets, [
         'tape_size'  => $tapeSize,
         'color_mode' => $colorMode,
         'owner_text' => $ownerText,
         'show_date'  => $showDate,
         'page_size'  => $pageSize,
         'orientation' => $orient,
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

   // ── PDF Generation ─────────────────────────────────────────────────────────

   /**
    * Generate a PDF with rich QR code labels using GLPI's native TCPDF.
    *
    * @param  array $assets  Array of asset data (or null for blank slots).
    * @param  array $params  Options: tape_size, color_mode, owner_text, show_date, page_size, orientation.
    * @return string|false   Absolute path to the generated PDF, or false on failure.
    */
   static function printPDF(array $assets, array $params) {

      $tapeSize  = $params['tape_size']  ?? '36mm';
      $colorMode = $params['color_mode'] ?? 'bw';
      $ownerText = trim($params['owner_text'] ?? '');
      $showDate  = (bool)($params['show_date'] ?? true);
      $pageSize  = strtoupper($params['page_size'] ?? 'A4');
      $isLandscape = (($params['orientation'] ?? 'Portrait') === 'Landscape');

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
         if ($cm['bg'][0] === 0) {
            $pdf->SetFillColor($cm['bg'][0], $cm['bg'][1], $cm['bg'][2]);
            $pdf->Rect($x, $y, $labelW, $labelH, 'F');
         }

         // ── Border ─────────────────────────────────────────────────────────
         $pdf->SetDrawColor($cm['border'][0], $cm['border'][1], $cm['border'][2]);
         $pdf->SetLineWidth(0.3);
         $pdf->Rect($x, $y, $labelW, $labelH, 'D');

         // ── QR code (vector, same URL as GLPI native) ──────────────────────
         $qrX = $x + 3;
         $qrY = $y + ($labelH - $qrSize) / 2;
         $pdf->write2DBarcode(
            $asset['url'],
            'QRCODE,M',
            $qrX, $qrY,
            $qrSize, $qrSize,
            [
               'border'        => false,
               'vpadding'      => 'auto',
               'hpadding'      => 'auto',
               'fgcolor'       => $cm['qr_fg'],
               'bgcolor'       => $cm['qr_bg'],
               'module_width'  => 1,
               'module_height' => 1,
            ],
            'N'
         );

         // ── Vertical separator ─────────────────────────────────────────────
         $sx = $x + 3 + $qrSize + 2;
         $pdf->SetDrawColor($cm['sep'][0], $cm['sep'][1], $cm['sep'][2]);
         $pdf->SetLineWidth(0.2);
         $pdf->Line($sx, $y + 3, $sx, $y + $labelH - 3);

         // Text area starts here
         $tx = $sx + 3;
         $textW = $labelW - ($tx - $x) - 2; // available text width

         // ── Logo (top-right corner) ────────────────────────────────────────
         if ($hasLogo) {
            $logoH = $ts['logo_h'];
            $logoMaxW = $textW;
            // Get logo aspect ratio
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
               $pdf->Image($logoPath, $logoX, $logoY, $logoW, $logoH, '', '', '', false, 300);
            }
         }

         // ── Text fields ────────────────────────────────────────────────────
         $curY = $y + 2;

         // Asset name (bold)
         $pdf->SetFont('helvetica', 'B', $ts['font_name']);
         $pdf->SetTextColor($cm['main'][0], $cm['main'][1], $cm['main'][2]);
         $maxChars = (int)($labelW * 0.22);
         $name = $asset['name'];
         if (mb_strlen($name) > $maxChars + 1) {
            $name = mb_substr($name, 0, $maxChars) . '...';
         }
         $nameY = $y + $labelH - 10;
         if ($tapeSize === '25mm') {
            $nameY = $y + $labelH - 8;
         }
         $pdf->SetXY($tx, $nameY);
         $pdf->Cell($textW, 0, $name, 0, 0, 'L');

         // Type
         $pdf->SetFont('helvetica', '', $ts['font_type']);
         $pdf->SetTextColor($cm['sub'][0], $cm['sub'][1], $cm['sub'][2]);
         $typeY = $nameY + ($ts['font_name'] * 0.4);
         $pdf->SetXY($tx, $typeY);
         $pdf->Cell($textW, 0, $asset['type_label'], 0, 0, 'L');

         // Serial number
         $pdf->SetFont('helvetica', 'B', $ts['font_sn']);
         $pdf->SetTextColor($cm['sn'][0], $cm['sn'][1], $cm['sn'][2]);
         $sn = $asset['serial'] ?: 'N/A';
         $snY = $typeY + ($ts['font_type'] * 0.5);
         $pdf->SetXY($tx, $snY);
         $pdf->Cell($textW, 0, 'S/N: ' . mb_substr($sn, 0, 20), 0, 0, 'L');

         // Inventory date (not on 25mm)
         $nextY = $snY + ($ts['font_sn'] * 0.5);
         $dateInv = $asset['date_inv'] ?? '';
         if ($dateInv && $tapeSize !== '25mm' && $showDate) {
            $pdf->SetFont('helvetica', '', $ts['font_loc']);
            $pdf->SetTextColor($cm['sub'][0], $cm['sub'][1], $cm['sub'][2]);
            $pdf->SetXY($tx, $nextY);
            $pdf->Cell($textW, 0, 'Inv: ' . $dateInv, 0, 0, 'L');
            $nextY += ($ts['font_loc'] * 0.5);
         }

         // Location
         $location = $asset['location'] ?? '';
         if ($location) {
            $pdf->SetFont('helvetica', 'I', $ts['font_loc']);
            $pdf->SetTextColor($cm['loc'][0], $cm['loc'][1], $cm['loc'][2]);
            $pdf->SetXY($tx, $nextY);
            $pdf->Cell($textW, 0, mb_substr($location, 0, 22), 0, 0, 'L');
         }

         // ── Bottom line: owner + inventory number ──────────────────────────
         $bottomY = $y + $labelH - ($ts['font_inv'] * 0.4) - 1;

         if ($ownerText) {
            $pdf->SetFont('helvetica', 'B', $ts['font_inv']);
            $pdf->SetTextColor($cm['main'][0], $cm['main'][1], $cm['main'][2]);
            $pdf->SetXY($tx, $bottomY);
            $pdf->Cell($textW / 2, 0, $ownerText, 0, 0, 'L');
         }

         $inv = $asset['otherserial'] ?? '';
         if ($inv && $tapeSize !== '25mm') {
            $pdf->SetFont('helvetica', '', $ts['font_inv']);
            $pdf->SetTextColor($cm['inv'][0], $cm['inv'][1], $cm['inv'][2]);
            $pdf->SetXY($tx, $bottomY);
            $align = $ownerText ? 'R' : 'L';
            $pdf->Cell($textW, 0, 'Inv: ' . $inv, 0, 0, $align);
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
