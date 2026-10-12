<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------

   Shows the download buttons of the last label generation (asset tab or
   massive action). Files themselves are served by front/send.php.
 */

use GlpiPlugin\Qrcodelabel\Label;

// GLPI 10 serves plugin front files as plain PHP scripts: bootstrap GLPI here.
// GLPI 11 has already booted through its Symfony kernel (LegacyFileLoadController)
// and defined GLPI_ROOT.
if (!defined('GLPI_ROOT')) {
   include __DIR__ . '/../../../inc/includes.php';
}

Session::checkLoginUser();
Session::checkRight('plugin_qrcodelabel_label', CREATE);

Html::header(Label::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], 'tools', 'none');

Label::showDownloadPage();

Html::footer();
