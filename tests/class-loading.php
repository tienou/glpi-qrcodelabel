<?php
// Model the actual parent-property boundary: GLPI 11 untyped vs GLPI 12 string.
if (($argv[1] ?? '') === '12') {
   class CommonGLPI { public static string $rightname = ''; }
} else {
   class CommonGLPI { public static $rightname = ''; }
}
class CommonDBTM extends CommonGLPI {}
require __DIR__ . '/../src/Profile.php';
require __DIR__ . '/../src/Label.php';
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Printprofile.php';
use GlpiPlugin\Qrcodelabel\Profile;
use GlpiPlugin\Qrcodelabel\Label;
use GlpiPlugin\Qrcodelabel\Config;
use GlpiPlugin\Qrcodelabel\Printprofile;
$expected = [Profile::class => 'profile', Label::class => 'plugin_qrcodelabel_label',
   Config::class => 'plugin_qrcodelabel_config'];
foreach ($expected as $class => $right) {
   if ($class::$rightname !== $right) {
      throw new RuntimeException('Wrong permission: ' . $class);
   }
   $property = new ReflectionProperty($class, 'rightname');
   if ($property->hasType() !== (($argv[1] ?? '') === '12')) {
      throw new RuntimeException('Wrong property type: ' . $class);
   }
}
Label::$rightname = 'test-label-right';
if (Profile::$rightname !== 'profile' || Config::$rightname !== 'plugin_qrcodelabel_config'
      || CommonGLPI::$rightname !== '' || Printprofile::$rightname !== '') {
   throw new RuntimeException('Permission leaked between classes');
}
echo 'All plugin classes loaded with GLPI ' . ($argv[1] ?? '11') . " parent properties.\n";
