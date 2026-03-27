<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT') && !defined('GLPI_DIR')) {
   die("Sorry. You can't access directly to this file");
}

class PluginQrcodelabelConfig extends CommonDBTM {

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
      Html::closeForm();
   }
}
