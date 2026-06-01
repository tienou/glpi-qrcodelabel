<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------

   Serves static files from GLPI_PLUGIN_DOC_DIR (e.g. logo.png).
   Compatible with GLPI 10 and GLPI 11.
 */

Session::checkLoginUser();

if (empty($_GET['file'])) {
   Html::displayErrorAndDie(__('File not found.', 'qrcodelabel'), true);
   return;
}

$file = (string)$_GET['file'];
// Reject any traversal attempt up-front; realpath check below is a defence-in-depth.
if (strpos($file, '..') !== false || strpos($file, "\0") !== false) {
   Html::displayErrorAndDie(__('File not found.', 'qrcodelabel'), true);
   return;
}
$path = GLPI_PLUGIN_DOC_DIR . '/' . ltrim(str_replace('\\', '/', $file), '/');

if (!file_exists($path)) {
   Html::displayErrorAndDie(__('File not found.', 'qrcodelabel'), true);
   return;
}

// Security: file must be inside THIS plugin's own doc subdirectory — never the
// shared GLPI_PLUGIN_DOC_DIR root, which also holds other plugins' files.
$realPath = realpath($path);
$realDoc  = realpath(GLPI_PLUGIN_DOC_DIR . '/qrcodelabel');
if ($realPath === false || $realDoc === false
      || strpos($realPath, $realDoc . DIRECTORY_SEPARATOR) !== 0) {
   Html::displayErrorAndDie(__('File not found.', 'qrcodelabel'), true);
   return;
}

// Only ever serve image files; reject any other extension outright (no
// octet-stream fallback that could leak arbitrary files).
$ext     = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeMap = [
   'png'  => 'image/png',
   'jpg'  => 'image/jpeg',
   'jpeg' => 'image/jpeg',
   'gif'  => 'image/gif',
];
if (!isset($mimeMap[$ext])) {
   Html::displayErrorAndDie(__('File not found.', 'qrcodelabel'), true);
   return;
}
$mime = $mimeMap[$ext];

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: public, max-age=86400');

readfile($realPath);
