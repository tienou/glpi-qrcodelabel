<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

namespace GlpiPlugin\Qrcodelabel;

use CommonDBTM;
use Dropdown;
use Html;
use Plugin;
use Session;

class Config extends CommonDBTM {

   static $table = 'glpi_plugin_qrcodelabel_configs';

   static $rightname = 'plugin_qrcodelabel_config';

   /**
    * Get the singleton config row from the database.
    *
    * @return array
    */
   static function getConfig(): array {
      $defaults = [
         'printer_type' => 'sheet',
         'tape_size'    => '36mm',
         'color_mode'   => 'bw',
         'show_date'    => 1,
         'page_size'    => 'A4',
         'orientation'  => 'Portrait',
         'owner_text'   => '',
      ];

      $config = new self();
      if ($config->getFromDB(1)) {
         return array_merge($defaults, $config->fields);
      }
      return $defaults;
   }

   /**
    * Show the admin configuration form.
    */
   function showConfigForm(): void {

      $config = self::getConfig();

      echo "<form name='form' method='post' action='"
         . Plugin::getWebDir('qrcodelabel') . "/front/config.form.php'"
         . " enctype='multipart/form-data'>";

      echo "<div class='center'>";
      echo "<table class='tab_cadre_fixe'>";

      // ── Header ────────────────────────────────────────────────────────────
      echo "<tr><th colspan='4'>"
         . __('QR Code Label - Configuration', 'qrcodelabel')
         . "</th></tr>";

      // ── Printer type ────────────────────────────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Printer type', 'qrcodelabel') . "</td><td colspan='3'>";
      Dropdown::showFromArray('printer_type', [
         'sheet'  => __('Sheet printer (A4/Letter label grid)', 'qrcodelabel'),
         'label'  => __('Label printer (Brother QL, Dymo...)', 'qrcodelabel'),
      ], ['value' => $config['printer_type'], 'width' => '350']);
      echo "</td></tr>";

      // ── Tape size ─────────────────────────────────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Default tape size', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('tape_size', [
         '25mm' => '25 mm', '36mm' => '36 mm', '50mm' => '50 mm',
      ], ['value' => $config['tape_size'], 'width' => '120']);
      echo "</td>";

      // ── Color mode ────────────────────────────────────────────────────────
      echo "<td>" . __('Default color mode', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('color_mode', [
         'bw'           => __('Black & White', 'qrcodelabel'),
         'mono'         => __('Monochrome', 'qrcodelabel'),
         'color'        => __('Color', 'qrcodelabel'),
         'inverse'      => __('Inverse (white on black)', 'qrcodelabel'),
         'inverse_mono' => __('Inverse Mono', 'qrcodelabel'),
      ], ['value' => $config['color_mode'], 'width' => '200']);
      echo "</td></tr>";

      // ── Show date ─────────────────────────────────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Show inventory date', 'qrcodelabel') . "</td><td>";
      Dropdown::showYesNo('show_date', $config['show_date'], -1, ['width' => '100']);
      echo "</td>";

      // ── Page size ─────────────────────────────────────────────────────────
      echo "<td>" . __('Default page size', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('page_size', [
         'A4' => 'A4', 'A3' => 'A3', 'LETTER' => 'Letter', 'LEGAL' => 'Legal',
      ], ['value' => $config['page_size'], 'width' => '120']);
      echo "</td></tr>";

      // ── Orientation ───────────────────────────────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Default orientation', 'qrcodelabel') . "</td><td>";
      Dropdown::showFromArray('orientation', [
         'Portrait'  => __('Portrait', 'qrcodelabel'),
         'Landscape' => __('Landscape', 'qrcodelabel'),
      ], ['value' => $config['orientation'], 'width' => '120']);
      echo "</td><td colspan='2'></td></tr>";

      // ── Owner text ────────────────────────────────────────────────────────
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Owner text', 'qrcodelabel') . "</td><td colspan='3'>";
      echo "<input type='text' name='owner_text' value='"
         . htmlspecialchars($config['owner_text'] ?? '', ENT_QUOTES) . "'"
         . " size='50' placeholder='" . __('e.g. Property of: My Company', 'qrcodelabel') . "'>";
      echo "</td></tr>";

      // ── Save button ───────────────────────────────────────────────────────
      echo "<tr><td class='tab_bg_1' colspan='4' align='center'>";
      echo "<input type='submit' name='saveConfig' value='" . __('Save') . "' class='submit'>";
      echo "</td></tr>";

      // ── Logo section ──────────────────────────────────────────────────────
      echo "<tr><th colspan='4'>" . __('Company logo', 'qrcodelabel') . "</th></tr>";

      $logoPath = GLPI_PLUGIN_DOC_DIR . '/qrcodelabel/logo.png';
      if (file_exists($logoPath)) {
         echo "<tr class='tab_bg_1'><td colspan='4' align='center'>";
         echo "<img src='" . Plugin::getWebDir('qrcodelabel')
            . "/front/document.send.php?file=qrcodelabel/logo.png' width='300'/>";
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'><td colspan='4' align='center'>";
         echo "<input type='submit' name='dropLogo' value='"
            . __('Delete the logo', 'qrcodelabel') . "' class='submit'>";
         echo "</td></tr>";
      }

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2' align='center'><input type='file' name='logo' /></td>";
      echo "<td colspan='2'><input type='submit' name='uploadLogo' value='"
         . __('Upload logo', 'qrcodelabel') . "' class='submit'></td>";
      echo "</tr>";

      echo "</table></div>";

      // ── Print Profiles section (inside the same form) ──────────────────────
      $this->showPrintProfilesSection();

      Html::closeForm();
   }

   /**
    * Show the print profiles management section.
    */
   private function showPrintProfilesSection(): void {

      $colorModeLabels = [
         'bw'           => __('Black & White', 'qrcodelabel'),
         'mono'         => __('Monochrome', 'qrcodelabel'),
         'color'        => __('Color', 'qrcodelabel'),
         'inverse'      => __('Inverse (white on black)', 'qrcodelabel'),
         'inverse_mono' => __('Inverse Mono', 'qrcodelabel'),
      ];
      $orientationLabels = [
         'Portrait'  => __('Portrait', 'qrcodelabel'),
         'Landscape' => __('Landscape', 'qrcodelabel'),
      ];
      $tapeSizeOptions = ['25mm' => '25 mm', '36mm' => '36 mm', '50mm' => '50 mm'];
      $pageSizeOptions = ['A4' => 'A4', 'A3' => 'A3', 'LETTER' => 'Letter', 'LEGAL' => 'Legal'];

      // Hidden fields for profile actions (set by JavaScript before submit)
      echo "<input type='hidden' name='profile_action' id='qrcl_profile_action' value=''>";
      echo "<input type='hidden' name='profile_id' id='qrcl_profile_id' value=''>";

      echo "<div class='center'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='8'>"
         . __('Print profiles', 'qrcodelabel')
         . "</th></tr>";

      // Table header
      echo "<tr class='tab_bg_2'>";
      echo "<th>" . __('Name') . "</th>";
      echo "<th>" . __('Tape size', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Color mode', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Show inventory date', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Page size', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Orientation', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Default', 'qrcodelabel') . "</th>";
      echo "<th>" . __('Actions') . "</th>";
      echo "</tr>";

      // List existing profiles
      global $DB;
      $iterator = $DB->request([
         'FROM'  => 'glpi_plugin_qrcodelabel_printprofiles',
         'ORDER' => 'name ASC',
      ]);

      foreach ($iterator as $row) {
         $pid = (int)$row['id'];
         echo "<tr class='tab_bg_1'>";

         // Name (editable) — use unique names per row
         echo "<td><input type='text' name='pp_name_{$pid}' value='"
            . htmlspecialchars($row['name']) . "' size='15'></td>";

         // Tape size
         echo "<td>";
         Dropdown::showFromArray("pp_tape_{$pid}", $tapeSizeOptions,
            ['value' => $row['tape_size'], 'width' => '100']);
         echo "</td>";

         // Color mode
         echo "<td>";
         Dropdown::showFromArray("pp_color_{$pid}", $colorModeLabels,
            ['value' => $row['color_mode'], 'width' => '150']);
         echo "</td>";

         // Show date
         echo "<td>";
         Dropdown::showYesNo("pp_date_{$pid}", $row['show_date'], -1, ['width' => '80']);
         echo "</td>";

         // Page size
         echo "<td>";
         Dropdown::showFromArray("pp_page_{$pid}", $pageSizeOptions,
            ['value' => $row['page_size'], 'width' => '100']);
         echo "</td>";

         // Orientation
         echo "<td>";
         Dropdown::showFromArray("pp_orient_{$pid}", $orientationLabels,
            ['value' => $row['orientation'], 'width' => '100']);
         echo "</td>";

         // Is default
         echo "<td>";
         Dropdown::showYesNo("pp_default_{$pid}", $row['is_default'], -1, ['width' => '80']);
         echo "</td>";

         // Actions: Save + Delete buttons
         echo "<td>";
         echo "<button type='submit' class='submit' onclick=\"document.getElementById('qrcl_profile_action').value='update';"
            . "document.getElementById('qrcl_profile_id').value='{$pid}';\">"
            . __('Save') . "</button> ";
         echo "<button type='submit' class='submit' onclick=\"if(!confirm('" . __('Are you sure?') . "'))return false;"
            . "document.getElementById('qrcl_profile_action').value='delete';"
            . "document.getElementById('qrcl_profile_id').value='{$pid}';\">"
            . __('Delete') . "</button>";
         echo "</td></tr>";
      }

      // Add new profile row
      echo "<tr><th colspan='8'>"
         . __('Add a print profile', 'qrcodelabel')
         . "</th></tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td><input type='text' name='pp_name_new' value='' size='15' "
         . "placeholder='" . __('Name') . "'></td>";

      echo "<td>";
      Dropdown::showFromArray('pp_tape_new', $tapeSizeOptions,
         ['value' => '36mm', 'width' => '100']);
      echo "</td>";

      echo "<td>";
      Dropdown::showFromArray('pp_color_new', $colorModeLabels,
         ['value' => 'bw', 'width' => '150']);
      echo "</td>";

      echo "<td>";
      Dropdown::showYesNo('pp_date_new', 1, -1, ['width' => '80']);
      echo "</td>";

      echo "<td>";
      Dropdown::showFromArray('pp_page_new', $pageSizeOptions,
         ['value' => 'A4', 'width' => '100']);
      echo "</td>";

      echo "<td>";
      Dropdown::showFromArray('pp_orient_new', $orientationLabels,
         ['value' => 'Portrait', 'width' => '100']);
      echo "</td>";

      echo "<td>";
      Dropdown::showYesNo('pp_default_new', 0, -1, ['width' => '80']);
      echo "</td>";

      echo "<td><button type='submit' class='submit' onclick=\"document.getElementById('qrcl_profile_action').value='add';"
         . "document.getElementById('qrcl_profile_id').value='new';\">"
         . __('Add') . "</button></td>";

      echo "</tr></table></div>";
   }
}
