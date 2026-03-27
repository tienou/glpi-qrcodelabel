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

if (isset($_POST['dropLogo'])) {
   // Delete logo
   if (is_file($docDir . '/logo.png')) {
      unlink($docDir . '/logo.png');
   }
   Session::addMessageAfterRedirect(__('The logo has been removed.', 'qrcodelabel'));

} else if (isset($_POST['uploadLogo']) && !empty($_FILES['logo']['name'])) {
   // Upload logo
   if (is_file($docDir . '/logo.png')) {
      @unlink($docDir . '/logo.png');
   }
   if (!is_dir($docDir)) {
      mkdir($docDir, 0755, true);
   }
   move_uploaded_file($_FILES['logo']['tmp_name'], $docDir . '/logo.png');
   Session::addMessageAfterRedirect(__('Logo uploaded successfully.', 'qrcodelabel'));

} else if (isset($_POST['saveConfig'])) {
   // Save configuration
   $config = new PluginQrcodelabelConfig();
   if (!$config->getFromDB(1)) {
      // Create if missing
      global $DB;
      $DB->insert('glpi_plugin_qrcodelabel_configs', [
         'id' => 1,
         'tape_size'   => $_POST['tape_size']   ?? '36mm',
         'color_mode'  => $_POST['color_mode']  ?? 'bw',
         'owner_text'  => $_POST['owner_text']  ?? '',
         'show_date'   => (int)($_POST['show_date'] ?? 1),
         'page_size'   => $_POST['page_size']   ?? 'A4',
         'orientation' => $_POST['orientation']  ?? 'Portrait',
      ]);
   } else {
      $config->update([
         'id'          => 1,
         'tape_size'   => $_POST['tape_size']   ?? '36mm',
         'color_mode'  => $_POST['color_mode']  ?? 'bw',
         'owner_text'  => $_POST['owner_text']  ?? '',
         'show_date'   => (int)($_POST['show_date'] ?? 1),
         'page_size'   => $_POST['page_size']   ?? 'A4',
         'orientation' => $_POST['orientation']  ?? 'Portrait',
      ]);
   }
   Session::addMessageAfterRedirect(__('Configuration saved.', 'qrcodelabel'));
}

Html::back();
