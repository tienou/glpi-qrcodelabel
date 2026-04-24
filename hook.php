<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

use GlpiPlugin\Qrcodelabel\Label;
use GlpiPlugin\Qrcodelabel\Profile;

/**
 * Define Massive Actions for this plugin.
 */
function plugin_qrcodelabel_MassiveActions($itemtype) {

   $action = Label::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'GenerateLabels';
   $label  = '<i class="fas fa-qrcode"></i> '
           . __('QR Code Label', 'qrcodelabel') . ' - '
           . __('Print QR labels', 'qrcodelabel');

   if (!in_array($itemtype, PLUGIN_QRCODELABEL_ITEMTYPES, true)) {
      return [];
   }

   return [$action => $label];
}


/**
 * Install process for plugin.
 */
function plugin_qrcodelabel_install() {
   global $DB;

   // Ensure PSR-4 classes are loaded (autoloader may not be active during install)
   $srcDir = Plugin::getPhpDir('qrcodelabel') . '/src';
   require_once $srcDir . '/Profile.php';

   $default_charset   = DBConnection::getDefaultCharset();
   $default_collation = DBConnection::getDefaultCollation();

   // Create plugin doc directory (for logo storage)
   if (!file_exists(GLPI_PLUGIN_DOC_DIR . "/qrcodelabel")) {
      mkdir(GLPI_PLUGIN_DOC_DIR . "/qrcodelabel", 0755, true);
   }

   // ── Table: glpi_plugin_qrcodelabel_configs ────────────────────────────────
   if (!$DB->tableExists("glpi_plugin_qrcodelabel_configs")) {
      $DB->doQueryOrDie(
         "CREATE TABLE `glpi_plugin_qrcodelabel_configs` (
            `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `tape_size`     VARCHAR(10)   NOT NULL DEFAULT '36mm',
            `color_mode`    VARCHAR(20)   NOT NULL DEFAULT 'bw',
            `show_date`     TINYINT(1)    NOT NULL DEFAULT 1,
            `page_size`     VARCHAR(10)   NOT NULL DEFAULT 'A4',
            `orientation`   VARCHAR(10)   NOT NULL DEFAULT 'Portrait',
            `owner_text`    VARCHAR(255)  NOT NULL DEFAULT '',
            `output_format` VARCHAR(10)   NOT NULL DEFAULT 'pdf',
            `show_location` TINYINT(1)    NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
         ) ENGINE=InnoDB
           DEFAULT CHARSET={$default_charset}
           COLLATE={$default_collation}
           ROW_FORMAT=DYNAMIC",
         $DB->error()
      );

      $DB->insert("glpi_plugin_qrcodelabel_configs", [
         'id'            => 1,
         'tape_size'     => '36mm',
         'color_mode'    => 'bw',
         'show_date'     => 1,
         'page_size'     => 'A4',
         'orientation'   => 'Portrait',
         'owner_text'    => '',
         'output_format' => 'pdf',
         'show_location' => 0,
      ]);
   } else {
      $migration = new Migration(PLUGIN_QRCODELABEL_VERSION);
      // v1.4.0: add output_format column (idempotent via addField)
      $migration->addField(
         'glpi_plugin_qrcodelabel_configs',
         'output_format',
         "VARCHAR(10) NOT NULL DEFAULT 'pdf'",
         ['after' => 'owner_text']
      );
      // v1.4.1: drop unused printer_type column (idempotent via dropField)
      $migration->dropField('glpi_plugin_qrcodelabel_configs', 'printer_type');
      // v1.4.2: add show_location toggle (off by default)
      $migration->addField(
         'glpi_plugin_qrcodelabel_configs',
         'show_location',
         "TINYINT(1) NOT NULL DEFAULT 0",
         ['after' => 'output_format']
      );
      $migration->executeMigration();
   }

   // ── Table: glpi_plugin_qrcodelabel_printprofiles ────────────────────────
   if (!$DB->tableExists("glpi_plugin_qrcodelabel_printprofiles")) {
      $DB->doQueryOrDie(
         "CREATE TABLE `glpi_plugin_qrcodelabel_printprofiles` (
            `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `name`          VARCHAR(100)  NOT NULL DEFAULT '',
            `tape_size`     VARCHAR(10)   NOT NULL DEFAULT '36mm',
            `color_mode`    VARCHAR(20)   NOT NULL DEFAULT 'bw',
            `show_date`     TINYINT(1)    NOT NULL DEFAULT 1,
            `page_size`     VARCHAR(10)   NOT NULL DEFAULT 'A4',
            `orientation`   VARCHAR(10)   NOT NULL DEFAULT 'Portrait',
            `is_default`    TINYINT(1)    NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
         ) ENGINE=InnoDB
           DEFAULT CHARSET={$default_charset}
           COLLATE={$default_collation}
           ROW_FORMAT=DYNAMIC",
         $DB->error()
      );

      $DB->insert("glpi_plugin_qrcodelabel_printprofiles", [
         'name'        => 'Standard',
         'tape_size'   => '36mm',
         'color_mode'  => 'bw',
         'show_date'   => 1,
         'page_size'   => 'A4',
         'orientation' => 'Portrait',
         'is_default'  => 1,
      ]);
   }

   // ── Profile rights ────────────────────────────────────────────────────────
   Profile::initProfile();

   return true;
}


/**
 * Uninstall process for plugin.
 */
function plugin_qrcodelabel_uninstall() {
   global $DB;

   // Ensure PSR-4 classes are loaded (autoloader may not be active during uninstall)
   $srcDir = Plugin::getPhpDir('qrcodelabel') . '/src';
   require_once $srcDir . '/Profile.php';

   $migration = new Migration(PLUGIN_QRCODELABEL_VERSION);

   if ($DB->tableExists("glpi_plugin_qrcodelabel_configs")) {
      $migration->dropTable("glpi_plugin_qrcodelabel_configs");
   }

   if ($DB->tableExists("glpi_plugin_qrcodelabel_printprofiles")) {
      $migration->dropTable("glpi_plugin_qrcodelabel_printprofiles");
   }

   $migration->executeMigration();

   Profile::removeRights();

   return true;
}
