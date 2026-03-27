<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------

   Handles single-item and multi-item label generation from the asset tab.
   Receives POST with itemtype + items_id (single) or from massive action.
 */

// GLPI 11: bootstrap is handled by Symfony LegacyFileLoadController.

Session::checkLoginUser();
Session::checkRight('plugin_qrcodelabel_label', CREATE);

global $CFG_GLPI;

$itemtype  = $_POST['itemtype']  ?? '';
$items_id  = (int)($_POST['items_id'] ?? 0);
$tapeSize  = $_POST['tape_size']  ?? '36mm';
$colorMode = $_POST['color_mode'] ?? 'bw';
$ownerText = trim($_POST['owner_text'] ?? '');
$nbCopies  = max(1, min(50, (int)($_POST['nb_copies'] ?? 1)));

$config = PluginQrcodelabelConfig::getConfig();

if (!$itemtype || !$items_id) {
   Session::addMessageAfterRedirect(__('Invalid request.', 'qrcodelabel'), false, ERROR);
   Html::back();
   return;
}

if (!in_array($itemtype, PLUGIN_QRCODELABEL_ITEMTYPES, true)) {
   Session::addMessageAfterRedirect(__('Unsupported item type.', 'qrcodelabel'), false, ERROR);
   Html::back();
   return;
}

/** @var CommonDBTM $item */
$item = new $itemtype();
if (!$item->getFromDB($items_id)) {
   Session::addMessageAfterRedirect(__('Item not found.', 'qrcodelabel'), false, ERROR);
   Html::back();
   return;
}

// Build URL exactly as GLPI native QR does
$url = $CFG_GLPI['url_base'] . $itemtype::getFormURLWithID($items_id, false);

// Get location
$location = '';
if ($item->isField('locations_id') && $item->fields['locations_id'] > 0) {
   $loc = new Location();
   if ($loc->getFromDB($item->fields['locations_id'])) {
      $location = $loc->fields['completename'] ?? $loc->fields['name'] ?? '';
   }
}

// Inventory date
$dateInv = '';
$dateRaw = $item->fields['date_creation'] ?? '';
if ($dateRaw) {
   $dateInv = substr($dateRaw, 0, 10);
}

$assetData = [
   'name'        => $item->fields['name'] ?? __('No name'),
   'serial'      => $item->fields['serial'] ?? '',
   'otherserial' => $item->fields['otherserial'] ?? '',
   'type_label'  => $itemtype::getTypeName(1),
   'location'    => $location,
   'date_inv'    => $dateInv,
   'url'         => $url,
];

// Build array with N copies
$assets = array_fill(0, $nbCopies, $assetData);

$pdfPath = PluginQrcodelabelLabel::printPDF($assets, [
   'tape_size'   => $tapeSize,
   'color_mode'  => $colorMode,
   'owner_text'  => $ownerText,
   'show_date'   => (bool)$config['show_date'],
   'page_size'   => $config['page_size'],
   'orientation' => $config['orientation'],
]);

if ($pdfPath) {
   $token = PluginQrcodelabelLabel::registerTmpPdf($pdfPath);
   $msg   = "<a href='" . Plugin::getWebDir('qrcodelabel') . '/front/send.php?token=' . urlencode($token)
          . "' target='_blank' rel='noopener noreferrer'>"
          . __('Download QR labels', 'qrcodelabel') . "</a>";
   Session::addMessageAfterRedirect($msg);
} else {
   Session::addMessageAfterRedirect(__('PDF generation failed.', 'qrcodelabel'), false, ERROR);
}

Html::back();
