<?php
// Integration smoke test with real TCPDF, QR encoding and GD; no GLPI database.
class CommonDBTM {}
class Session {
   public static function getLoginUserID() { return 1; }
}
function __($text, $domain = '') { return $text; }
define('PLUGIN_QRCODELABEL_VERSION', '1.5.2');
define('GLPI_VERSION', '11.0.0');
define('GLPI_TMP_DIR', sys_get_temp_dir() . '/qrcodelabel-test-' . getmypid() . '-' . bin2hex(random_bytes(4)));
define('GLPI_PLUGIN_DOC_DIR', GLPI_TMP_DIR);
mkdir(GLPI_TMP_DIR, 0700, true);
require_once getenv('TCPDF_PATH') ?: '/usr/share/php/tcpdf/tcpdf.php';
require_once dirname(getenv('TCPDF_PATH') ?: '/usr/share/php/tcpdf/tcpdf.php') . '/tcpdf_barcodes_2d.php';
require_once __DIR__ . '/../src/Sheetlayout.php';
require_once __DIR__ . '/../src/Label.php';
use GlpiPlugin\Qrcodelabel\Label;

$assets = [];
for ($i = 1; $i <= 15; $i++) {
   $assets[] = [
      'itemtype' => 'Computer', 'id' => $i, 'name' => sprintf('L%03d', $i),
      'type_label' => 'Computer', 'serial' => 'TEST-SERIAL-' . $i,
      'otherserial' => sprintf('L%03d', $i), 'date_inv' => '02/11/2026',
      'location' => 'Upper Campus', 'url' => 'https://glpi.example.org/front/computer.form.php?id=' . $i,
   ];
}
$profile = ['tape_size' => 'avery61528', 'color_mode' => 'bw', 'show_date' => 0,
   'page_size' => 'A4', 'orientation' => 'Landscape'];
$params = Label::buildParams($profile, ['owner_text' => 'Property of TCA']);
$outputs = [];
$outputs['avery'] = Label::printPDF($assets, $params);
$params['start_position'] = 14;
$outputs['partial'] = Label::printPDF(array_slice($assets, 0, 2), $params);
$params['offset_x'] = 1.2;
$params['offset_y'] = -0.8;
$outputs['offset'] = Label::printPDF(array_slice($assets, 0, 2), $params);
$params['tape_size'] = '36mm';
$params['page_size'] = 'A4';
$params['orientation'] = 'Portrait';
$outputs['brother'] = Label::printPDF(array_slice($assets, 0, 2), $params);
// Sheet profile overrides the global PNG-only format and supplies a PDF token.
$params['tape_size'] = 'avery61528';
if (!Label::emitDownloadLinks(array_slice($assets, 0, 1), $params, 'png')) {
   throw new RuntimeException('Avery PDF override failed');
}
$download = $_SESSION['qrcodelabel_downloads']['files'][0];
$outputs['png_override'] = Label::resolveTmpFile($download['token']);
// Check that wide and square logos cannot shrink or overlap the QR.
$params['start_position'] = 1;
$params['offset_x'] = 0;
$params['offset_y'] = 0;
$params['show_date'] = true;
$params['show_location'] = true;
mkdir(GLPI_PLUGIN_DOC_DIR . '/qrcodelabel', 0700, true);
foreach (['wide_logo' => [400, 70], 'square_logo' => [100, 100]] as $name => [$width, $height]) {
   $logo = imagecreatetruecolor($width, $height);
   imagefill($logo, 0, 0, imagecolorallocate($logo, 245, 245, 245));
   imagestring($logo, 5, 8, 20, 'COMPANY', imagecolorallocate($logo, 20, 40, 80));
   imagepng($logo, GLPI_PLUGIN_DOC_DIR . '/qrcodelabel/logo.png');
   $outputs[$name] = Label::printPDF(array_slice($assets, 0, 1), $params);
}
foreach ($outputs as $file) {
   if (!$file || !is_file($file) || file_get_contents($file, false, null, 0, 4) !== '%PDF') {
      throw new RuntimeException('PDF generation failed');
   }
}
echo json_encode($outputs, JSON_PRETTY_PRINT) . "\n";
