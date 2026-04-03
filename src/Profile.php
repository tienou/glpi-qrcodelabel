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
use CommonGLPI;
use Html;
use Profile as GlpiProfile;
use ProfileRight;
use Session;

class Profile extends CommonDBTM {

   static $rightname = "profile";

   static function getTypeName($nb = 0) {
      return __('QR Code Label', 'qrcodelabel');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item instanceof GlpiProfile && $item->getID() > 0) {
         return self::createTabEntry(self::getTypeName());
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item instanceof GlpiProfile) {
         $profile = new self();
         $profile->showForm($item->getID());
      }
      return true;
   }

   function showForm($ID, array $options = []) {
      echo "<div class='firstbloc'>";
      $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);

      if ($canedit) {
         $glpiProfile = new GlpiProfile();
         echo "<form method='post' action='" . $glpiProfile->getFormURL() . "'>";
      }

      $glpiProfile = new GlpiProfile();
      $glpiProfile->getFromDB($ID);

      $rights = self::getAllRights();
      $glpiProfile->displayRightsChoiceMatrix($rights, [
         'canedit'       => $canedit,
         'default_class' => 'tab_bg_2',
         'title'         => __('QR Code Label', 'qrcodelabel'),
      ]);

      if ($canedit) {
         echo "<div class='center'>";
         echo Html::hidden('id', ['value' => $ID]);
         echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";
   }

   static function cleanProfiles($item) {
      self::removeRights();
   }

   static function getAllRights() {
      return [
         [
            'rights' => [UPDATE => __('Update')],
            'label'  => __('Manage configuration', 'qrcodelabel'),
            'field'  => 'plugin_qrcodelabel_config',
         ],
         [
            'rights' => [CREATE => __('Create')],
            'label'  => __('Generate QR labels', 'qrcodelabel'),
            'field'  => 'plugin_qrcodelabel_label',
         ],
      ];
   }

   static function addDefaultProfileInfos($profiles_id, $rights) {
      $profileRight = new ProfileRight();
      foreach ($rights as $right => $value) {
         if (!countElementsInTable('glpi_profilerights',
                                    ['profiles_id' => $profiles_id, 'name' => $right])) {
            $profileRight->add([
               'profiles_id' => $profiles_id,
               'name'        => $right,
               'rights'      => $value,
            ]);
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }

   static function createFirstAccess($profiles_id) {
      foreach (self::getAllRights() as $right) {
         self::addDefaultProfileInfos($profiles_id,
                                       [$right['field'] => ALLSTANDARDRIGHT]);
      }
   }

   static function removeRights() {
      foreach (self::getAllRights() as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
         ProfileRight::deleteProfileRights([$right['field']]);
      }
   }

   static function initProfile() {
      $a_rights = self::getAllRights();

      foreach ($a_rights as $data) {
         if (!countElementsInTable("glpi_profilerights", ['name' => $data['field']])) {
            ProfileRight::addProfileRights([$data['field']]);
            $_SESSION['glpiactiveprofile'][$data['field']] = 0;
         }
      }

      // Grant all rights to current profile
      if (isset($_SESSION['glpiactiveprofile'])) {
         $glpiProfile = new GlpiProfile();
         $dataprofile = [];
         $dataprofile['id'] = $_SESSION['glpiactiveprofile']['id'];
         $glpiProfile->getFromDB($_SESSION['glpiactiveprofile']['id']);
         foreach ($a_rights as $info) {
            if (is_array($info)
                  && (!empty($info['rights']))
                  && (!empty($info['label']))
                  && (!empty($info['field']))) {
               foreach (array_keys($info['rights']) as $right) {
                  $dataprofile['_' . $info['field']][$right] = 1;
                  $_SESSION['glpiactiveprofile'][$info['field']] = $right;
               }
            }
         }
         $glpiProfile->update($dataprofile);
      }
   }
}
