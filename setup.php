<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------

   LICENSE

   This file is part of QR Code Label plugin for GLPI.

   QR Code Label is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   QR Code Label is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with QR Code Label. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   Plugin QR Code Label
   @author    Etienne Gaillard
   @copyright Copyright (c) 2026 Etienne Gaillard
   @license   AGPL License 3.0 or (at your option) any later version
   @link      https://github.com/tienou/glpi-qrcodelabel
   @since     2026

   ------------------------------------------------------------------------
 */

define("PLUGIN_QRCODELABEL_VERSION", "1.0.0");

// Minimal GLPI version, inclusive
define('PLUGIN_QRCODELABEL_MIN_GLPI', '10.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_QRCODELABEL_MAX_GLPI', '11.0.99');

/**
 * Supported asset types for label generation.
 */
define('PLUGIN_QRCODELABEL_ITEMTYPES', [
   'Computer',
   'Monitor',
   'Peripheral',
   'NetworkEquipment',
   'Printer',
   'Phone',
]);

function plugin_init_qrcodelabel() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['qrcodelabel'] = true;

   // GLPI 11: Plugin::registerClass() was removed.
   // Classes in inc/ are autoloaded by GLPI's built-in classloader (naming convention).
   if (method_exists('Plugin', 'registerClass')) {
      // GLPI 10.x
      Plugin::registerClass('PluginQrcodelabelProfile', ['addtabon' => ['Profile']]);
      Plugin::registerClass('PluginQrcodelabelLabel', [
         'addtabon' => PLUGIN_QRCODELABEL_ITEMTYPES,
      ]);
      Plugin::registerClass('PluginQrcodelabelConfig');
   } else {
      // GLPI 11+ — ensure class files are loaded for tab/menu discovery
      include_once(Plugin::getPhpDir('qrcodelabel') . '/inc/profile.class.php');
      include_once(Plugin::getPhpDir('qrcodelabel') . '/inc/label.class.php');
      include_once(Plugin::getPhpDir('qrcodelabel') . '/inc/config.class.php');
   }

   if (Session::haveRight('plugin_qrcodelabel_label', CREATE)
         || Session::haveRight('plugin_qrcodelabel_config', UPDATE)) {

      $PLUGIN_HOOKS['pre_item_purge']['qrcodelabel']
         = ['Profile' => ['PluginQrcodelabelProfile', 'cleanProfiles']];

      // Massive Action
      $PLUGIN_HOOKS['use_massive_action']['qrcodelabel'] = 1;

      // Menu registration — works on both GLPI 10 and 11
      $PLUGIN_HOOKS['menu_toadd']['qrcodelabel'] = ['tools' => 'PluginQrcodelabelLabel'];
      $PLUGIN_HOOKS['helpdesk_menu_entry']['qrcodelabel'] = false;
   }

   // Config page
   if (Session::haveRight('config', UPDATE)) {
      $PLUGIN_HOOKS['config_page']['qrcodelabel'] = 'front/config.php';
   }
}

function plugin_version_qrcodelabel() {
   return [
      'name'           => 'QR Code Label',
      'shortname'      => 'qrcodelabel',
      'version'        => PLUGIN_QRCODELABEL_VERSION,
      'license'        => 'AGPLv3+',
      'author'         => 'Etienne Gaillard',
      'homepage'       => 'https://github.com/tienou/glpi-qrcodelabel',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_QRCODELABEL_MIN_GLPI,
            'max' => PLUGIN_QRCODELABEL_MAX_GLPI,
         ]
      ]
   ];
}
