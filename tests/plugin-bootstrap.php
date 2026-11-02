<?php
// Run in a separate PHP process for each GLPI version's actual Hooks.php.
require $argv[1];
define('CREATE', 4);
define('UPDATE', 2);
class Session {
   public static function haveRight($name, $right) { return true; }
}
class Plugin {
   public static array $registrations = [];
   public static function registerClass($name, $options) {
      self::$registrations[$name] = $options;
   }
}
require __DIR__ . '/../setup.php';
$PLUGIN_HOOKS = [];
plugin_init_qrcodelabel();
$hasCsrfHook = defined(Glpi\Plugin\Hooks::class . '::CSRF_COMPLIANT');
if (isset($PLUGIN_HOOKS['csrf_compliant']['qrcodelabel']) !== $hasCsrfHook) {
   throw new RuntimeException('Incorrect legacy CSRF registration');
}
foreach (['use_massive_action', 'menu_toadd', 'config_page'] as $hook) {
   if (!isset($PLUGIN_HOOKS[$hook]['qrcodelabel'])) {
      throw new RuntimeException('Missing hook: ' . $hook);
   }
}
if (count(Plugin::$registrations) !== 2
      || version_compare('12.0.1', PLUGIN_QRCODELABEL_MAX_GLPI, '>=')) {
   throw new RuntimeException('Tabs or GLPI 12 version range failed');
}
echo 'Bootstrap passed; legacy CSRF declaration: ' . ($hasCsrfHook ? 'yes' : 'no') . "\n";
