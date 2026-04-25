<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

Session::checkRight('config', UPDATE);

$self = Plugin::getWebDir('phonebg') . '/front/config.form.php';

/* ==========================
 * Constants
 * ========================== */
$maxSize      = 500 * 1024;
$maxFontSize  = 2 * 1024 * 1024;
$allowedMime  = ['image/png'];
$baseFile     = PluginPhonebgPaths::basePath();
$hasBase      = is_readable($baseFile);

/* Ensure storage directories exist */
foreach (['templates', 'fonts'] as $sub) {
   $d = PluginPhonebgPaths::filesDir() . '/' . $sub;
   if (!is_dir($d)) {
      mkdir($d, 0755, true);
   }
}

/* Copy bundled font on first access if fonts dir is empty */
$fontsDir = PluginPhonebgPaths::fontsDir();
if (!file_exists($fontsDir . '/DejaVuSans.ttf')) {
   foreach (GLPI_PLUGINS_DIRECTORIES as $pdir) {
      $src = $pdir . '/phonebg/fonts/DejaVuSans.ttf';
      if (is_readable($src)) {
         copy($src, $fontsDir . '/DejaVuSans.ttf');
         break;
      }
   }
}

/* ==========================
 * POST: delete template
 * ========================== */
if (isset($_POST['delete_base']) && $hasBase) {
   if (unlink($baseFile)) {
      Session::addMessageAfterRedirect(__('Template deleted successfully', 'phonebg'), false, INFO);
   } else {
      Session::addMessageAfterRedirect(__('Could not delete template', 'phonebg'), false, ERROR);
   }
   Html::redirect($self);
}

/* ==========================
 * POST: upload template
 * ========================== */
if (isset($_POST['save']) && isset($_FILES['base'])) {

   if (!is_uploaded_file($_FILES['base']['tmp_name'])) {
      Html::redirect($self);
   }

   $tmpFile = $_FILES['base']['tmp_name'];
   $size    = $_FILES['base']['size'];

   $finfo = finfo_open(FILEINFO_MIME_TYPE);
   $mime  = finfo_file($finfo, $tmpFile);
   finfo_close($finfo);

   if ($size > $maxSize) {
      Session::addMessageAfterRedirect(
         __('File too large (max 500 KB)', 'phonebg'),
         false,
         ERROR
      );
   } elseif (!in_array($mime, $allowedMime, true)) {
      Session::addMessageAfterRedirect(
         __('Invalid format, PNG only', 'phonebg'),
         false,
         ERROR
      );
   } else {
      if (move_uploaded_file($tmpFile, $baseFile)) {
         chmod($baseFile, 0644);
         Session::addMessageAfterRedirect(__('Background saved successfully', 'phonebg'), false, INFO);
      } else {
         Session::addMessageAfterRedirect(__('Could not save template', 'phonebg'), false, ERROR);
      }
   }

   Html::redirect($self);
}

/* ==========================
 * POST: upload font
 * ========================== */
if (isset($_POST['upload_font']) && isset($_FILES['font_file'])) {

   if (!is_uploaded_file($_FILES['font_file']['tmp_name'])) {
      Html::redirect($self . '?tab=fonts');
   }

   $tmpFile  = $_FILES['font_file']['tmp_name'];
   $origName = basename($_FILES['font_file']['name']);
   $size     = $_FILES['font_file']['size'];

   $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
   if (!in_array($ext, ['ttf', 'otf'], true)) {
      Session::addMessageAfterRedirect(
         __('Invalid font format, TTF or OTF only', 'phonebg'),
         false,
         ERROR
      );
      Html::redirect($self . '?tab=fonts');
   }

   if ($size > $maxFontSize) {
      Session::addMessageAfterRedirect(
         __('Font file too large (max 2 MB)', 'phonebg'),
         false,
         ERROR
      );
      Html::redirect($self . '?tab=fonts');
   }

   $handle = fopen($tmpFile, 'rb');
   $magic  = fread($handle, 4);
   fclose($handle);
   $validMagic = in_array($magic, [
      "\x00\x01\x00\x00",
      "\x74\x72\x75\x65",
      "\x4F\x54\x54\x4F",
   ], true);

   if (!$validMagic) {
      Session::addMessageAfterRedirect(
         __('Invalid font format, TTF or OTF only', 'phonebg'),
         false,
         ERROR
      );
      Html::redirect($self . '?tab=fonts');
   }

   $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $origName);
   $destPath = $fontsDir . '/' . $safeName;

   if (move_uploaded_file($tmpFile, $destPath)) {
      chmod($destPath, 0644);
      Session::addMessageAfterRedirect(__('Font saved successfully', 'phonebg'), false, INFO);
   } else {
      Session::addMessageAfterRedirect(__('Could not save font', 'phonebg'), false, ERROR);
   }

   Html::redirect($self . '?tab=fonts');
}

/* ==========================
 * POST: delete font
 * ========================== */
if (isset($_POST['delete_font'])) {
   $fontName = basename((string)$_POST['delete_font']);
   $fontPath = $fontsDir . '/' . $fontName;

   if ($fontName === 'DejaVuSans.ttf') {
      Session::addMessageAfterRedirect(__('The default font cannot be deleted', 'phonebg'), false, WARNING);
   } elseif (is_file($fontPath) && preg_match('/\.(ttf|otf)$/i', $fontPath)) {
      if (unlink($fontPath)) {
         PluginPhonebgPaths::deleteFontMeta($fontName);
         if (PluginPhonebgConfig::get('font_file') === $fontName) {
            PluginPhonebgConfig::set('font_file', 'DejaVuSans.ttf');
         }
         Session::addMessageAfterRedirect(__('Font deleted successfully', 'phonebg'), false, INFO);
      } else {
         Session::addMessageAfterRedirect(__('Could not delete font', 'phonebg'), false, ERROR);
      }
   }

   Html::redirect($self . '?tab=fonts');
}

/* ==========================
 * POST: save positions
 * ========================== */
if (isset($_POST['save_positions'])) {

   foreach (['name_x', 'name_y', 'mobile_x', 'mobile_y'] as $f) {
      PluginPhonebgConfig::set($f, max(0, (int)($_POST[$f] ?? 0)));
   }
   foreach (['name_size', 'mobile_size'] as $f) {
      PluginPhonebgConfig::set($f, max(8, (int)($_POST[$f] ?? 8)));
   }

   $color = $_POST['font_color'] ?? '#000000';
   if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
      $color = '#000000';
   }
   PluginPhonebgConfig::set('font_color', $color);

   $selFont    = basename((string)($_POST['font_file'] ?? 'DejaVuSans.ttf'));
   $availFonts = PluginPhonebgPaths::listFonts();
   if (!array_key_exists($selFont, $availFonts)) {
      $selFont = 'DejaVuSans.ttf';
   }
   PluginPhonebgConfig::set('font_file', $selFont);

   PluginPhonebgConfig::set('label1_enabled', isset($_POST['label1_enabled']) ? '1' : '0');
   PluginPhonebgConfig::set('label2_enabled', isset($_POST['label2_enabled']) ? '1' : '0');
   foreach (['label1_text', 'label2_text'] as $f) {
      PluginPhonebgConfig::set($f, substr(strip_tags((string)($_POST[$f] ?? '')), 0, 150));
   }
   foreach (['label1_x', 'label1_y', 'label2_x', 'label2_y'] as $f) {
      PluginPhonebgConfig::set($f, max(0, (int)($_POST[$f] ?? 0)));
   }
   foreach (['label1_size', 'label2_size'] as $f) {
      PluginPhonebgConfig::set($f, max(8, (int)($_POST[$f] ?? 8)));
   }

   Session::addMessageAfterRedirect(__('Positions saved successfully', 'phonebg'), false, INFO);
   Html::redirect($self . '?tab=posiciones');
}

/* ==========================
 * POST: reset positions
 * ========================== */
if (isset($_POST['reset_positions'])) {
   PluginPhonebgConfig::resetToDefaults();
   Session::addMessageAfterRedirect(__('Positions reset to default values', 'phonebg'), false, INFO);
   Html::redirect($self);
}

/* ==========================
 * Page header
 * ========================== */
Html::header(
   __('Phone Wallpapers', 'phonebg'),
   $self,
   'config',
   'plugins'
);

Html::displayMessageAfterRedirect();

/* ==========================
 * Prepare template data
 * ========================== */
$cfg        = PluginPhonebgConfig::getAll();
$availFonts = PluginPhonebgPaths::listFonts();

$validTabs = ['plantilla', 'posiciones', 'fonts'];
$activeTab = in_array($_GET['tab'] ?? '', $validTabs, true) ? $_GET['tab'] : 'plantilla';

$baseUrl   = $hasBase ? PluginPhonebgPaths::baseUrl() : '';
$baseUrlTs = $hasBase ? $baseUrl . '&t=' . time() : '';

PluginPhonebgRenderer::display('config_form.html.twig', [
   'self_url'       => $self,
   'csrf_token'     => Session::getNewCSRFToken(),
   'has_base'       => $hasBase,
   'base_url'       => $baseUrl,
   'base_url_ts'    => $baseUrlTs,
   'cfg'            => $cfg,
   'avail_fonts'    => $availFonts,
   'active_tab'     => $activeTab,
   'name_x'         => (int)$cfg['name_x'],
   'name_y'         => (int)$cfg['name_y'],
   'mobile_x'       => (int)$cfg['mobile_x'],
   'mobile_y'       => (int)$cfg['mobile_y'],
   'label1_x'       => (int)($cfg['label1_x'] ?? 0),
   'label1_y'       => (int)($cfg['label1_y'] ?? 650),
   'label2_x'       => (int)($cfg['label2_x'] ?? 0),
   'label2_y'       => (int)($cfg['label2_y'] ?? 720),
   'label1_enabled' => ($cfg['label1_enabled'] ?? '0') === '1',
   'label2_enabled' => ($cfg['label2_enabled'] ?? '0') === '1',
   'label1_text'    => (string)($cfg['label1_text'] ?? ''),
   'label2_text'    => (string)($cfg['label2_text'] ?? ''),
   'js_confirm'     => json_encode(__('There are unsaved changes in Positions. Continue without saving?', 'phonebg')),
]);

Html::footer();
