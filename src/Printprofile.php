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

class Printprofile extends CommonDBTM {

   static $table = 'glpi_plugin_qrcodelabel_printprofiles';

   /**
    * Get all print profiles as id => name array.
    *
    * @return array
    */
   static function getProfiles(): array {
      global $DB;

      $profiles = [];
      $iterator = $DB->request([
         'FROM'  => self::getTable(),
         'ORDER' => 'name ASC',
      ]);
      foreach ($iterator as $row) {
         $profiles[$row['id']] = $row['name'];
      }
      return $profiles;
   }

   /**
    * Get the default print profile (is_default = 1).
    * Falls back to the first profile if none is marked as default.
    *
    * @return array|null
    */
   static function getDefault(): ?array {
      global $DB;

      $iterator = $DB->request([
         'FROM'  => self::getTable(),
         'WHERE' => ['is_default' => 1],
         'LIMIT' => 1,
      ]);
      foreach ($iterator as $row) {
         return $row;
      }

      // Fallback: return first profile
      $iterator = $DB->request([
         'FROM'  => self::getTable(),
         'ORDER' => 'id ASC',
         'LIMIT' => 1,
      ]);
      foreach ($iterator as $row) {
         return $row;
      }

      return null;
   }

   /**
    * Get a full profile row by ID.
    *
    * @param  int $id
    * @return array|null
    */
   static function getProfileById(int $id): ?array {
      global $DB;

      $iterator = $DB->request([
         'FROM'  => self::getTable(),
         'WHERE' => ['id' => $id],
         'LIMIT' => 1,
      ]);
      foreach ($iterator as $row) {
         return $row;
      }

      return null;
   }
}
