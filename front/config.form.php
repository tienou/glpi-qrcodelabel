<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

// GLPI 11: bootstrap is handled by Symfony LegacyFileLoadController.

Session::checkRight("config", UPDATE);

$docDir = GLPI_PLUGIN_DOC_DIR . '/qrcodelabel';

// Whitelists for input validation
$validPrinterTypes = ['sheet', 'label'];
$validTapeSizes    = ['25mm', '36mm', '50mm'];
$validColorModes   = ['bw', 'mono', 'color', 'inverse', 'inverse_mono'];
$validPageSizes    = ['A4', 'A3', 'LETTER', 'LEGAL'];
$validOrientations = ['Portrait', 'Landscape'];

if (isset($_POST['dropLogo'])) {
   // Delete logo
   if (is_file($docDir . '/logo.png')) {
      unlink($docDir . '/logo.png');
   }
   Session::addMessageAfterRedirect(__('The logo has been removed.', 'qrcodelabel'));

} else if (isset($_POST['uploadLogo']) && !empty($_FILES['logo']['name'])) {
   // Upload logo — validate file is actually an image
   $tmpFile = $_FILES['logo']['tmp_name'] ?? '';
   if ($tmpFile && is_uploaded_file($tmpFile)) {
      $finfo = @getimagesize($tmpFile);
      if ($finfo && in_array($finfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) {
         if (!is_dir($docDir)) {
            mkdir($docDir, 0755, true);
         }
         if (is_file($docDir . '/logo.png')) {
            @unlink($docDir . '/logo.png');
         }
         move_uploaded_file($tmpFile, $docDir . '/logo.png');
         Session::addMessageAfterRedirect(__('Logo uploaded successfully.', 'qrcodelabel'));
      } else {
         Session::addMessageAfterRedirect(
            __('Invalid file: only PNG, JPEG and GIF images are accepted.', 'qrcodelabel'),
            false, ERROR
         );
      }
   }

} else if (isset($_POST['saveConfig'])) {
   // Validate and sanitize inputs against whitelists
   $printerType = in_array($_POST['printer_type'] ?? '', $validPrinterTypes, true)
      ? $_POST['printer_type'] : 'sheet';
   $tapeSize = in_array($_POST['tape_size'] ?? '', $validTapeSizes, true)
      ? $_POST['tape_size'] : '36mm';
   $colorMode = in_array($_POST['color_mode'] ?? '', $validColorModes, true)
      ? $_POST['color_mode'] : 'bw';
   $showDate = (int)(bool)($_POST['show_date'] ?? 1);
   $pageSize = in_array($_POST['page_size'] ?? '', $validPageSizes, true)
      ? $_POST['page_size'] : 'A4';
   $orientation = in_array($_POST['orientation'] ?? '', $validOrientations, true)
      ? $_POST['orientation'] : 'Portrait';
   $ownerText = mb_substr(trim($_POST['owner_text'] ?? ''), 0, 255);

   // Save configuration
   $config = new PluginQrcodelabelConfig();
   if (!$config->getFromDB(1)) {
      // Create if missing
      global $DB;
      $DB->insert('glpi_plugin_qrcodelabel_configs', [
         'id'           => 1,
         'printer_type' => $printerType,
         'tape_size'    => $tapeSize,
         'color_mode'   => $colorMode,
         'show_date'    => $showDate,
         'page_size'    => $pageSize,
         'orientation'  => $orientation,
         'owner_text'   => $ownerText,
      ]);
   } else {
      $config->update([
         'id'           => 1,
         'printer_type' => $printerType,
         'tape_size'    => $tapeSize,
         'color_mode'   => $colorMode,
         'show_date'    => $showDate,
         'page_size'    => $pageSize,
         'orientation'  => $orientation,
         'owner_text'   => $ownerText,
      ]);
   }
   Session::addMessageAfterRedirect(__('Configuration saved.', 'qrcodelabel'));
}

Html::back();
