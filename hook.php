<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

/**
 * Define Massive Actions for this plugin.
 *
 * "Print QR Labels" is available on all supported hardware asset types.
 */
function plugin_qrcodelabel_MassiveActions($itemtype) {

   $action = 'PluginQrcodelabelLabel' . MassiveAction::CLASS_ACTION_SEPARATOR . 'GenerateLabels';
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

   $default_charset   = DBConnection::getDefaultCharset();
   $default_collation = DBConnection::getDefaultCollation();

   // Create plugin doc directory (for logo storage)
   if (!file_exists(GLPI_PLUGIN_DOC_DIR . "/qrcodelabel")) {
      mkdir(GLPI_PLUGIN_DOC_DIR . "/qrcodelabel", 0755, true);
   }

   // ── Table: glpi_plugin_qrcodelabel_configs ────────────────────────────────
   if (!$DB->tableExists("glpi_plugin_qrcodelabel_configs")) {
      $DB->doQuery("CREATE TABLE `glpi_plugin_qrcodelabel_configs` (
         `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
         `printer_type`  VARCHAR(20)   NOT NULL DEFAULT 'sheet',
         `tape_size`     VARCHAR(10)   NOT NULL DEFAULT '36mm',
         `color_mode`    VARCHAR(20)   NOT NULL DEFAULT 'bw',
         `show_date`     TINYINT(1)    NOT NULL DEFAULT 1,
         `page_size`     VARCHAR(10)   NOT NULL DEFAULT 'A4',
         `orientation`   VARCHAR(10)   NOT NULL DEFAULT 'Portrait',
         `owner_text`    VARCHAR(255)  NOT NULL DEFAULT '',
         PRIMARY KEY (`id`)
      ) ENGINE=InnoDB
        DEFAULT CHARSET={$default_charset}
        COLLATE={$default_collation}
        ROW_FORMAT=DYNAMIC");

      $DB->insert("glpi_plugin_qrcodelabel_configs", [
         'id'            => 1,
         'printer_type'  => 'sheet',
         'tape_size'     => '36mm',
         'color_mode'    => 'bw',
         'show_date'     => 1,
         'page_size'     => 'A4',
         'orientation'   => 'Portrait',
         'owner_text'    => '',
      ]);
   }

   // ── Table: glpi_plugin_qrcodelabel_printprofiles ────────────────────────
   if (!$DB->tableExists("glpi_plugin_qrcodelabel_printprofiles")) {
      $DB->doQuery("CREATE TABLE `glpi_plugin_qrcodelabel_printprofiles` (
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
        ROW_FORMAT=DYNAMIC");

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
   include_once Plugin::getPhpDir('qrcodelabel') . '/inc/profile.class.php';
   PluginQrcodelabelProfile::initProfile();

   return true;
}


/**
 * Uninstall process for plugin.
 */
function plugin_qrcodelabel_uninstall() {
   global $DB;

   $migration = new Migration(PLUGIN_QRCODELABEL_VERSION);

   if ($DB->tableExists("glpi_plugin_qrcodelabel_configs")) {
      $migration->dropTable("glpi_plugin_qrcodelabel_configs");
   }

   if ($DB->tableExists("glpi_plugin_qrcodelabel_printprofiles")) {
      $migration->dropTable("glpi_plugin_qrcodelabel_printprofiles");
   }

   $migration->executeMigration();

   include_once Plugin::getPhpDir('qrcodelabel') . '/inc/profile.class.php';
   PluginQrcodelabelProfile::removeRights();

   return true;
}
