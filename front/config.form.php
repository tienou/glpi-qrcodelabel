<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

use GlpiPlugin\Qrcodelabel\Config;
use GlpiPlugin\Qrcodelabel\Printprofile;

// GLPI 11: bootstrap is handled by Symfony LegacyFileLoadController.

Session::checkRight("config", UPDATE);

$docDir = GLPI_PLUGIN_DOC_DIR . '/qrcodelabel';

// Whitelists for input validation
$validPrinterTypes = ['sheet', 'label'];
$validTapeSizes    = ['25mm', '36mm', '50mm'];
$validColorModes   = ['bw', 'mono', 'color', 'inverse', 'inverse_mono'];
$validPageSizes    = ['A4', 'A3', 'LETTER', 'LEGAL'];
$validOrientations = ['Portrait', 'Landscape'];

// ── Profile actions (single-form approach with pp_* fields) ────────────────
$profileAction = $_POST['profile_action'] ?? '';
$profileId     = (int)($_POST['profile_id'] ?? 0);

if ($profileAction === 'update' && $profileId > 0) {
   $pName = mb_substr(trim($_POST["pp_name_{$profileId}"] ?? ''), 0, 100);
   if ($pName !== '') {
      $pTapeSize    = in_array($_POST["pp_tape_{$profileId}"] ?? '', $validTapeSizes, true)
         ? $_POST["pp_tape_{$profileId}"] : '36mm';
      $pColorMode   = in_array($_POST["pp_color_{$profileId}"] ?? '', $validColorModes, true)
         ? $_POST["pp_color_{$profileId}"] : 'bw';
      $pShowDate    = (int)(bool)($_POST["pp_date_{$profileId}"] ?? 1);
      $pPageSize    = in_array($_POST["pp_page_{$profileId}"] ?? '', $validPageSizes, true)
         ? $_POST["pp_page_{$profileId}"] : 'A4';
      $pOrientation = in_array($_POST["pp_orient_{$profileId}"] ?? '', $validOrientations, true)
         ? $_POST["pp_orient_{$profileId}"] : 'Portrait';
      $pIsDefault   = (int)(bool)($_POST["pp_default_{$profileId}"] ?? 0);

      if ($pIsDefault) {
         global $DB;
         $DB->update('glpi_plugin_qrcodelabel_printprofiles', ['is_default' => 0], ['is_default' => 1]);
      }

      $profile = new Printprofile();
      $profile->update([
         'id'          => $profileId,
         'name'        => $pName,
         'tape_size'   => $pTapeSize,
         'color_mode'  => $pColorMode,
         'show_date'   => $pShowDate,
         'page_size'   => $pPageSize,
         'orientation' => $pOrientation,
         'is_default'  => $pIsDefault,
      ]);
      Session::addMessageAfterRedirect(__('Print profile updated.', 'qrcodelabel'));
   }

} else if ($profileAction === 'delete' && $profileId > 0) {
   $profile = new Printprofile();
   if ($profile->getFromDB($profileId)) {
      $profile->delete(['id' => $profileId]);
      Session::addMessageAfterRedirect(__('Print profile deleted.', 'qrcodelabel'));
   }

} else if ($profileAction === 'add') {
   $pName = mb_substr(trim($_POST['pp_name_new'] ?? ''), 0, 100);
   if ($pName !== '') {
      $pTapeSize    = in_array($_POST['pp_tape_new'] ?? '', $validTapeSizes, true)
         ? $_POST['pp_tape_new'] : '36mm';
      $pColorMode   = in_array($_POST['pp_color_new'] ?? '', $validColorModes, true)
         ? $_POST['pp_color_new'] : 'bw';
      $pShowDate    = (int)(bool)($_POST['pp_date_new'] ?? 1);
      $pPageSize    = in_array($_POST['pp_page_new'] ?? '', $validPageSizes, true)
         ? $_POST['pp_page_new'] : 'A4';
      $pOrientation = in_array($_POST['pp_orient_new'] ?? '', $validOrientations, true)
         ? $_POST['pp_orient_new'] : 'Portrait';
      $pIsDefault   = (int)(bool)($_POST['pp_default_new'] ?? 0);

      if ($pIsDefault) {
         global $DB;
         $DB->update('glpi_plugin_qrcodelabel_printprofiles', ['is_default' => 0], ['is_default' => 1]);
      }

      $profile = new Printprofile();
      $profile->add([
         'name'        => $pName,
         'tape_size'   => $pTapeSize,
         'color_mode'  => $pColorMode,
         'show_date'   => $pShowDate,
         'page_size'   => $pPageSize,
         'orientation' => $pOrientation,
         'is_default'  => $pIsDefault,
      ]);
      Session::addMessageAfterRedirect(__('Print profile added.', 'qrcodelabel'));
   } else {
      Session::addMessageAfterRedirect(
         __('Profile name is required.', 'qrcodelabel'),
         false, ERROR
      );
   }

} else if (isset($_POST['dropLogo'])) {
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

} else if (isset($_POST['add_profile'])) {
   // Add a new print profile
   $profileName = mb_substr(trim($_POST['profile_name'] ?? ''), 0, 100);
   if ($profileName === '') {
      Session::addMessageAfterRedirect(
         __('Profile name is required.', 'qrcodelabel'),
         false, ERROR
      );
   } else {
      $pTapeSize    = in_array($_POST['profile_tape_size'] ?? '', $validTapeSizes, true)
         ? $_POST['profile_tape_size'] : '36mm';
      $pColorMode   = in_array($_POST['profile_color_mode'] ?? '', $validColorModes, true)
         ? $_POST['profile_color_mode'] : 'bw';
      $pShowDate    = (int)(bool)($_POST['profile_show_date'] ?? 1);
      $pPageSize    = in_array($_POST['profile_page_size'] ?? '', $validPageSizes, true)
         ? $_POST['profile_page_size'] : 'A4';
      $pOrientation = in_array($_POST['profile_orientation'] ?? '', $validOrientations, true)
         ? $_POST['profile_orientation'] : 'Portrait';
      $pIsDefault   = (int)(bool)($_POST['profile_is_default'] ?? 0);

      // If setting as default, unset all others first
      if ($pIsDefault) {
         global $DB;
         $DB->update('glpi_plugin_qrcodelabel_printprofiles', ['is_default' => 0], ['is_default' => 1]);
      }

      $profile = new Printprofile();
      $profile->add([
         'name'        => $profileName,
         'tape_size'   => $pTapeSize,
         'color_mode'  => $pColorMode,
         'show_date'   => $pShowDate,
         'page_size'   => $pPageSize,
         'orientation' => $pOrientation,
         'is_default'  => $pIsDefault,
      ]);
      Session::addMessageAfterRedirect(__('Print profile added.', 'qrcodelabel'));
   }

} else if (isset($_POST['update_profile'])) {
   // Update an existing print profile
   $profileId   = (int)($_POST['profile_id'] ?? 0);
   $profileName = mb_substr(trim($_POST['profile_name'] ?? ''), 0, 100);

   if ($profileId > 0 && $profileName !== '') {
      $pTapeSize    = in_array($_POST['profile_tape_size'] ?? '', $validTapeSizes, true)
         ? $_POST['profile_tape_size'] : '36mm';
      $pColorMode   = in_array($_POST['profile_color_mode'] ?? '', $validColorModes, true)
         ? $_POST['profile_color_mode'] : 'bw';
      $pShowDate    = (int)(bool)($_POST['profile_show_date'] ?? 1);
      $pPageSize    = in_array($_POST['profile_page_size'] ?? '', $validPageSizes, true)
         ? $_POST['profile_page_size'] : 'A4';
      $pOrientation = in_array($_POST['profile_orientation'] ?? '', $validOrientations, true)
         ? $_POST['profile_orientation'] : 'Portrait';
      $pIsDefault   = (int)(bool)($_POST['profile_is_default'] ?? 0);

      if ($pIsDefault) {
         global $DB;
         $DB->update('glpi_plugin_qrcodelabel_printprofiles', ['is_default' => 0], ['is_default' => 1]);
      }

      $profile = new Printprofile();
      $profile->update([
         'id'          => $profileId,
         'name'        => $profileName,
         'tape_size'   => $pTapeSize,
         'color_mode'  => $pColorMode,
         'show_date'   => $pShowDate,
         'page_size'   => $pPageSize,
         'orientation' => $pOrientation,
         'is_default'  => $pIsDefault,
      ]);
      Session::addMessageAfterRedirect(__('Print profile updated.', 'qrcodelabel'));
   }

} else if (isset($_POST['delete_profile'])) {
   // Delete a print profile
   $profileId = (int)($_POST['profile_id'] ?? 0);
   if ($profileId > 0) {
      $profile = new Printprofile();
      if ($profile->getFromDB($profileId)) {
         $profile->delete(['id' => $profileId]);
         Session::addMessageAfterRedirect(__('Print profile deleted.', 'qrcodelabel'));
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
   $config = new Config();
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
