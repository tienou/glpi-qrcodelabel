<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------

   Handles single-item label generation from the "QR Label" asset tab.
   Receives POST with itemtype + items_id + profile_id + nb_copies.
 */

use GlpiPlugin\Qrcodelabel\Config;
use GlpiPlugin\Qrcodelabel\Label;
use GlpiPlugin\Qrcodelabel\Printprofile;

// GLPI 10 serves plugin front files as plain PHP scripts: bootstrap GLPI here
// (this also validates the CSRF token of the POST). GLPI 11 has already booted
// through its Symfony kernel (LegacyFileLoadController) and defined GLPI_ROOT.
if (!defined('GLPI_ROOT')) {
   include __DIR__ . '/../../../inc/includes.php';
}

Session::checkLoginUser();
Session::checkRight('plugin_qrcodelabel_label', CREATE);

$itemtype = (string)($_POST['itemtype'] ?? '');
$items_id = (int)($_POST['items_id'] ?? 0);

if ($itemtype === '' || $items_id <= 0) {
   Session::addMessageAfterRedirect(__('Invalid request.', 'qrcodelabel'), false, ERROR);
   Html::back();
}

if (!plugin_qrcodelabel_is_supported_itemtype($itemtype)) {
   Session::addMessageAfterRedirect(__('Unsupported item type.', 'qrcodelabel'), false, ERROR);
   Html::back();
}

$item = getItemForItemtype($itemtype);
if (!($item instanceof CommonDBTM) || !$item->getFromDB($items_id)) {
   Session::addMessageAfterRedirect(__('Item not found.', 'qrcodelabel'), false, ERROR);
   Html::back();
}
// Entity / visibility check: never print data the user cannot read.
if (!$item->can($items_id, READ)) {
   Session::addMessageAfterRedirect(__('Item not found.', 'qrcodelabel'), false, ERROR);
   Html::back();
}

$profile = Printprofile::getProfileById((int)($_POST['profile_id'] ?? 0))
   ?? Printprofile::getDefault();
if (!$profile) {
   Session::addMessageAfterRedirect(__('No print profile found.', 'qrcodelabel'), false, ERROR);
   Html::back();
}

$config = Config::getConfig();
$copies = Label::sanitizeCopies($_POST['nb_copies'] ?? 1);
$assets = array_fill(0, $copies, Label::buildAssetData($item));

$ok = Label::emitDownloadLinks(
   $assets,
   Label::buildParams($profile, $config),
   Label::getOutputFormat($config),
   Html::getBackUrl()
);
if (!$ok) {
   Session::addMessageAfterRedirect(__('Label generation failed.', 'qrcodelabel'), false, ERROR);
   Html::back();
}

// Post/Redirect/Get to a page that keeps the download buttons on screen.
Html::redirect(Label::getWebPath() . '/front/download.php');
