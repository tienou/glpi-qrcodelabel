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

use Glpi\Plugin\Hooks;
use GlpiPlugin\Qrcodelabel\Label;
use GlpiPlugin\Qrcodelabel\Profile;

define("PLUGIN_QRCODELABEL_VERSION", "1.5.0");

// Minimal GLPI version, inclusive
define('PLUGIN_QRCODELABEL_MIN_GLPI', '10.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_QRCODELABEL_MAX_GLPI', '11.0.99');

/**
 * Native GLPI asset types supported for label generation.
 * GLPI 11 custom assets are added dynamically, see plugin_qrcodelabel_get_itemtypes().
 */
define('PLUGIN_QRCODELABEL_ITEMTYPES', [
   'Computer',
   'Monitor',
   'Peripheral',
   'NetworkEquipment',
   'Printer',
   'Phone',
]);

/** Base class of GLPI 11 custom assets (absent on GLPI 10). */
define('PLUGIN_QRCODELABEL_CUSTOM_ASSET_CLASS', 'Glpi\\Asset\\Asset');

/**
 * Class names of the active GLPI 11 custom asset definitions
 * (e.g. Glpi\CustomAsset\ServerAsset). Empty on GLPI 10.
 *
 * Read from the definitions table because plugins are initialised before GLPI
 * boots its custom asset definitions (AssetDefinitionManager::bootDefinitions()).
 *
 * @return string[]
 */
function plugin_qrcodelabel_get_custom_asset_types(): array {
   global $DB;

   static $types = null;
   if ($types !== null) {
      return $types;
   }
   $types = [];

   $definitionClass = 'Glpi\\Asset\\AssetDefinition';
   if (!class_exists($definitionClass) || !($DB instanceof DBmysql)) {
      return $types;
   }
   try {
      $iterator = $DB->request([
         'SELECT' => ['system_name'],
         'FROM'   => $definitionClass::getTable(),
         'WHERE'  => ['is_active' => 1],
      ]);
      foreach ($iterator as $row) {
         $systemName = (string)$row['system_name'];
         if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $systemName)) {
            $types[] = $definitionClass::getCustomObjectNamespace() . '\\'
               . $systemName . $definitionClass::getCustomObjectClassSuffix();
         }
      }
   } catch (\Throwable $e) {
      // Database not ready (install/update in progress): no custom assets.
      $types = [];
   }
   return $types;
}

/**
 * All item types the plugin can label (native assets + GLPI 11 custom assets).
 *
 * @return string[]
 */
function plugin_qrcodelabel_get_itemtypes(): array {
   return array_merge(PLUGIN_QRCODELABEL_ITEMTYPES, plugin_qrcodelabel_get_custom_asset_types());
}

/**
 * Whether labels can be generated for the given item type.
 */
function plugin_qrcodelabel_is_supported_itemtype($itemtype): bool {
   if (!is_string($itemtype) || $itemtype === '') {
      return false;
   }
   if (in_array($itemtype, PLUGIN_QRCODELABEL_ITEMTYPES, true)) {
      return true;
   }
   // GLPI 11 custom assets: only classes of an active definition.
   return in_array($itemtype, plugin_qrcodelabel_get_custom_asset_types(), true)
      && is_a($itemtype, PLUGIN_QRCODELABEL_CUSTOM_ASSET_CLASS, true);
}

function plugin_init_qrcodelabel() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['qrcodelabel'] = true;

   // Register the Profile tab so admins can grant rights per profile.
   // Plugin::registerClass() exists on both GLPI 10 and 11 (it delegates to
   // CommonGLPI::registerStandardTab()).
   Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);

   if (Session::haveRight('plugin_qrcodelabel_label', CREATE)) {
      // "QR Label" tab on every supported asset form (native + custom assets).
      Plugin::registerClass(Label::class, ['addtabon' => plugin_qrcodelabel_get_itemtypes()]);

      // Massive Action
      $PLUGIN_HOOKS[Hooks::USE_MASSIVE_ACTION]['qrcodelabel'] = 1;
   }

   if (Session::haveRight('plugin_qrcodelabel_config', UPDATE)) {
      // Menu entry (Tools > QR Code Labels) and gear icon in Setup > Plugins
      // both open the configuration page, which requires this right.
      $PLUGIN_HOOKS[Hooks::MENU_TOADD]['qrcodelabel'] = ['tools' => Label::class];
      $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['qrcodelabel'] = 'front/config.php';
   }
   $PLUGIN_HOOKS[Hooks::HELPDESK_MENU_ENTRY]['qrcodelabel'] = false;
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
         ],
         'php' => [
            'min' => '7.4',
            'exts' => [
               'gd' => [
                  'required' => true,
               ],
            ],
         ],
      ]
   ];
}

/**
 * Check prerequisites before install.
 *
 * @return boolean
 */
function plugin_qrcodelabel_check_prerequisites() {
   if (!extension_loaded('gd')) {
      echo __('GD extension is required for QR code and logo image processing.', 'qrcodelabel');
      return false;
   }
   return true;
}

/**
 * Check configuration process for plugin.
 *
 * @param boolean $verbose Enable verbosity. Default to false
 * @return boolean
 */
function plugin_qrcodelabel_check_config($verbose = false) {
   return true;
}
