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

// GLPI 11: bootstrap is handled by Symfony LegacyFileLoadController.

Session::checkRight("config", UPDATE);

Plugin::load('qrcodelabel');

Html::header(__('QR Code Label', 'qrcodelabel'), $_SERVER['PHP_SELF'], "config", "plugins");

$config = new Config();
$config->showConfigForm();

Html::footer();
