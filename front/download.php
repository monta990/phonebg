<?php
declare(strict_types=1);
if (!defined('GLPI_ROOT')) {
    die('Direct access not allowed');
}

Session::checkLoginUser();
global $CFG_GLPI;

/* =========================
 * Obtener teléfono
 * ========================= */
 $phones_id = (int)($_GET['phoneid'] ?? 0);
if ($phones_id <= 0) {
    Session::addMessageAfterRedirect(__('Invalid phone', 'phonebg'), false, ERROR);
    Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
}

 $phone = new Phone();
if (!$phone->getFromDB($phones_id)) {
    Session::addMessageAfterRedirect(__('Phone not found', 'phonebg'), false, ERROR);
    Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
}

/* =========================
 * AUTORIZACIÓN (NUEVO)
 * Permitir: Administradores, Técnicos (canViewItem) o el Usuario asignado al celular
 * ========================= */
 $current_user_id = Session::getLoginUserID();
 $is_tech_or_admin = $phone->canViewItem();
 $is_owner = isset($phone->fields['users_id']) && ($phone->fields['users_id'] == $current_user_id);

if (!$is_tech_or_admin && !$is_owner) {
    Session::addMessageAfterRedirect(__('You are not authorized to access this phone background.', 'phonebg'), false, ERROR);
    Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
}

/* =========================
 * Validaciones del plugin
 * ========================= */
 $errors = PluginPhonebgBackground::checkRequirements();
if (!empty($errors)) {
    foreach ($errors as $msg) {
        Session::addMessageAfterRedirect($msg, false, ERROR);
    }
    Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
}

/* =========================
 * Generar PNG
 * ========================= */
 $file = PluginPhonebgBackground::generatePNG($phone);
if (!is_file($file)) {
    Session::addMessageAfterRedirect(__('Could not generate image', 'phonebg'), false, ERROR);
    Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
}

/* =========================
 * Enviar imagen
 * ========================= */
 $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $phone->getName());
 $filename = 'background_' . $safe . '.png';
 $preview = isset($_GET['preview']) && $_GET['preview'] === '1';

header('Content-Type: image/png');
header('Content-Disposition: ' . ($preview ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-store');

try {
    readfile($file);
} finally {
    @unlink($file);
}
exit;
