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

use GlpiPlugin\Qrcodelabel\Config;
use GlpiPlugin\Qrcodelabel\Label;
use GlpiPlugin\Qrcodelabel\Printprofile;

// GLPI 11: bootstrap is handled by Symfony LegacyFileLoadController.

Session::checkLoginUser();
Session::checkRight('plugin_qrcodelabel_label', CREATE);
// CSRF is auto-validated by GLPI (plugin declares csrf_compliant=true).

global $CFG_GLPI;

$itemtype  = $_POST['itemtype']  ?? '';
$items_id  = (int)($_POST['items_id'] ?? 0);

// Load print profile from DB
$profileId = (int)($_POST['profile_id'] ?? 0);
$profile   = Printprofile::getProfileById($profileId);
if (!$profile) {
   // Fallback to default profile
   $profile = Printprofile::getDefault();
}
if (!$profile) {
   Session::addMessageAfterRedirect(__('No print profile found.', 'qrcodelabel'), false, ERROR);
   Html::back();
   return;
}

$tapeSize  = $profile['tape_size'];
$colorMode = $profile['color_mode'];
// owner_text + output_format come from global config, not from the profile
$config    = Config::getConfig();
$ownerText = trim($config['owner_text'] ?? '');
$format    = in_array($config['output_format'] ?? 'pdf', ['pdf', 'png', 'both'], true)
   ? $config['output_format'] : 'pdf';

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
   'itemtype'    => $itemtype,
   'id'          => $items_id,
   'name'        => $item->fields['name'] ?? __('No name'),
   'serial'      => $item->fields['serial'] ?? '',
   'otherserial' => $item->fields['otherserial'] ?? '',
   'type_label'  => $itemtype::getTypeName(1),
   'location'    => $location,
   'date_inv'    => $dateInv,
   'url'         => $url,
];

// Always 1 label per click (users re-run for more copies)
$assets = [$assetData];

$params = [
   'tape_size'   => $tapeSize,
   'color_mode'  => $colorMode,
   'owner_text'  => $ownerText,
   'show_date'   => (bool)$profile['show_date'],
   'page_size'   => $profile['page_size'],
   'orientation' => $profile['orientation'],
];

$ok = Label::emitDownloadLinks($assets, $params, $format);
if (!$ok) {
   Session::addMessageAfterRedirect(__('Label generation failed.', 'qrcodelabel'), false, ERROR);
}

Html::back();
