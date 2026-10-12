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

// GLPI 10 serves plugin front files as plain PHP scripts: GLPI must be
// bootstrapped here (without it: "Class Session not found" -> HTTP 500).
// GLPI 11 loads them through its Symfony kernel (LegacyFileLoadController),
// which has already booted GLPI and defined GLPI_ROOT.
if (!defined('GLPI_ROOT')) {
   include __DIR__ . '/../../../inc/includes.php';
}

// Gated on the plugin's own right (not core "config") so a profile such as
// Technician can manage QR Code Label settings without full GLPI setup rights.
Session::checkRight('plugin_qrcodelabel_config', UPDATE);

Html::header(__('QR Code Label', 'qrcodelabel'), $_SERVER['PHP_SELF'], "config", "plugins");

$config = new Config();
$config->showConfigForm();

Html::footer();
