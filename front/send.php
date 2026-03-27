<?php

/*
   ------------------------------------------------------------------------
   QR Code Label
   Copyright (C) 2026 by Etienne Gaillard
   ------------------------------------------------------------------------
   LICENSE: AGPL License 3.0 or (at your option) any later version
   ------------------------------------------------------------------------

   PDF delivery — compatible GLPI 10 and GLPI 11 (Symfony).

   GLPI 11 uses LegacyFileLoadController:
     1. ob_start()
     2. require( this file )
     3. $content = ob_get_clean()
     4. new Response($content) with headers copied from headers_list()

   Therefore:
     - Do NOT call ob_end_clean() — would destroy the Symfony buffer.
     - Do NOT call exit/die — would prevent Symfony from building the Response.
     - readfile() writes into the active buffer; Symfony captures it.
     - header() is captured by Symfony via headers_list().
 */

Session::checkLoginUser();

// ── Resolve token ─────────────────────────────────────────────────────────
if (empty($_GET['token'])) {
   Http::notFound();
   return;
}

$token   = (string)$_GET['token'];
$absPath = PluginQrcodelabelLabel::resolveTmpPdf($token);

if ($absPath === null) {
   Html::displayErrorAndDie(
      __('The requested file no longer exists or has already been downloaded.', 'qrcodelabel'),
      true
   );
   return;
}

// ── Security: path must be inside GLPI_TMP_DIR ────────────────────────────
$realPath = realpath($absPath);
$realTmp  = realpath(GLPI_TMP_DIR);

if ($realPath === false || $realTmp === false
      || strpos($realPath, $realTmp . DIRECTORY_SEPARATOR) !== 0) {
   Html::displayErrorAndDie(__('Unauthorized access to this file', 'qrcodelabel'), true);
   return;
}

// ── Download filename ─────────────────────────────────────────────────────
$downloadName = 'qr_labels.pdf';
$tmpBasename  = basename($realPath);
if (preg_match('/qrcodelabel_\d+_([a-zA-Z0-9]+)_\d+\.pdf$/', $tmpBasename, $m)) {
   $downloadName = 'qr_labels_' . $m[1] . '.pdf';
}

// ── Schedule deletion after response ──────────────────────────────────────
register_shutdown_function(static function () use ($realPath): void {
   if (file_exists($realPath)) {
      @unlink($realPath);
   }
});

$filesize = filesize($realPath);

// ── Headers ───────────────────────────────────────────────────────────────
header('Content-Type: application/pdf');
header('Content-Length: ' . $filesize);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Cache-Control: private, must-revalidate');
header('Pragma: private');
header('Expires: Mon, 26 Nov 1962 00:00:00 GMT');

// ── Output file into the active buffer ────────────────────────────────────
readfile($realPath);
