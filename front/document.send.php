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
   Http::notFound();
   return;
}

$file = str_replace(['..', '\\'], ['', '/'], $_GET['file']);
$path = GLPI_PLUGIN_DOC_DIR . '/' . $file;

if (!file_exists($path)) {
   Http::notFound();
   return;
}

// Security: file must be inside GLPI_PLUGIN_DOC_DIR
$realPath = realpath($path);
$realDoc  = realpath(GLPI_PLUGIN_DOC_DIR);
if ($realPath === false || $realDoc === false
      || strpos($realPath, $realDoc . DIRECTORY_SEPARATOR) !== 0) {
   Http::notFound();
   return;
}

$ext  = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mime = [
   'png'  => 'image/png',
   'jpg'  => 'image/jpeg',
   'jpeg' => 'image/jpeg',
   'gif'  => 'image/gif',
][$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: public, max-age=86400');

readfile($realPath);
