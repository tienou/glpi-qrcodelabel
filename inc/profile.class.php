<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------
 */

class PluginQrcodelabelProfile extends Profile {

   static $rightname = "config";

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getID() > 0 && $item->fields['interface'] == 'central') {
         return self::createTabEntry(__('QR Code Label', 'qrcodelabel'));
      }
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      $profile = new self();
      $profile->showForm($item->getID());
      return true;
   }

   function showForm($ID, array $options = []) {
      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]))) {
         $profile = new Profile();
         echo "<form method='post' action='" . $profile->getFormURL() . "'>";
      }

      $profile = new Profile();
      $profile->getFromDB($ID);

      $rights = $this->getAllRights();
      $profile->displayRightsChoiceMatrix($rights, [
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

   function getAllRights() {
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
      include_once(Plugin::getPhpDir('qrcodelabel') . "/inc/profile.class.php");
      $profile = new self();
      foreach ($profile->getAllRights() as $right) {
         self::addDefaultProfileInfos($profiles_id,
                                       [$right['field'] => ALLSTANDARDRIGHT]);
      }
   }

   static function removeRights() {
      $profile = new self();
      foreach ($profile->getAllRights() as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
         ProfileRight::deleteProfileRights([$right['field']]);
      }
   }

   static function initProfile() {
      $pfProfile = new self();
      $profile   = new Profile();
      $a_rights  = $pfProfile->getAllRights();

      foreach ($a_rights as $data) {
         if (!countElementsInTable("glpi_profilerights", ['name' => $data['field']])) {
            ProfileRight::addProfileRights([$data['field']]);
            $_SESSION['glpiactiveprofile'][$data['field']] = 0;
         }
      }

      // Grant all rights to current profile
      if (isset($_SESSION['glpiactiveprofile'])) {
         $dataprofile       = [];
         $dataprofile['id'] = $_SESSION['glpiactiveprofile']['id'];
         $profile->getFromDB($_SESSION['glpiactiveprofile']['id']);
         foreach ($a_rights as $info) {
            if (is_array($info)
                  && (!empty($info['rights']))
                  && (!empty($info['label']))
                  && (!empty($info['field']))) {
               $rights = $info['rights'];
               foreach (array_keys($rights) as $right) {
                  $dataprofile['_' . $info['field']][$right] = 1;
                  $_SESSION['glpiactiveprofile'][$info['field']] = $right;
               }
            }
         }
         $profile->update($dataprofile);
      }
   }
}
