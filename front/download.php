<?php
declare(strict_types=1);
if (!defined('GLPI_ROOT')) {
    die('Direct access not allowed');
}

Session::checkLoginUser();
global $CFG_GLPI;

$is_preview = isset($_GET['preview']) && $_GET['preview'] === '1';

/** Abort helper: redirects normally or emits JSON for preview requests. */
$abort = function(string $msg) use ($is_preview, $CFG_GLPI): never {
    if ($is_preview) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $msg]);
        exit;
    }
    Session::addMessageAfterRedirect($msg, false, ERROR);
    Html::redirect($_SERVER['HTTP_REFERER'] ?? $CFG_GLPI['root_doc']);
    exit;
};

/* =========================
 * Obtener teléfono
 * ========================= */
 $phones_id = (int)($_GET['phoneid'] ?? 0);
if ($phones_id <= 0) {
    $abort(__('Invalid phone', 'phonebg'));
}

 $phone = new Phone();
if (!$phone->getFromDB($phones_id)) {
    $abort(__('Phone not found', 'phonebg'));
}

/* =========================
 * AUTORIZACIÓN (NUEVO)
 * Permitir: Administradores, Técnicos (canViewItem) o el Usuario asignado al celular
 * ========================= */
 $current_user_id = Session::getLoginUserID();
 $is_tech_or_admin = $phone->canViewItem();
 $is_owner = isset($phone->fields['users_id']) && ($phone->fields['users_id'] == $current_user_id);

if (!$is_tech_or_admin && !$is_owner) {
    $abort(__('You are not authorized to access this phone background.', 'phonebg'));
}

/* =========================
 * Validaciones del plugin
 * ========================= */
 $errors = PluginPhonebgBackground::checkRequirements();
if (!empty($errors)) {
    if ($is_preview) {
        header('Content-Type: application/json');
        echo json_encode(['error' => implode(' ', $errors)]);
        exit;
    }
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
    $abort(PluginPhonebgBackground::$lastError ?: __('Failed to generate background image.', 'phonebg'));
}

/* =========================
 * Enviar imagen
 * ========================= */
 $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $phone->getName());
 $filename = 'background_' . $safe . '.png';
header('Content-Type: image/png');
header('Content-Disposition: ' . ($is_preview ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-store');

try {
    readfile($file);
} finally {
    @unlink($file);
}
exit;
