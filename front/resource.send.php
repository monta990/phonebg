<?php

if (!defined('GLPI_ROOT')) {
   define('GLPI_ROOT', dirname(__DIR__, 3));
}

$resource = $_GET['resource'] ?? '';

Session::checkLoginUser();

if ($resource !== 'base') {
   http_response_code(404);
   exit;
}

$path = PluginPhonebgPaths::basePath();

if (!is_readable($path)) {
   http_response_code(404);
   exit;
}

/*  Nombre real del archivo */
$filename = 'bg_cell.png';

header('Content-Type: image/png');
header('Content-Length: ' . filesize($path));

$mtime = filemtime($path);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: "' . md5_file($path) . '"');
header('Cache-Control: private, max-age=3600');
header('Content-Disposition: attachment; filename="' . $filename . '"');

readfile($path);
exit;
