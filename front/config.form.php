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

   /* Validate extension */
   $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
   if (!in_array($ext, ['ttf', 'otf'], true)) {
      Session::addMessageAfterRedirect(
         __('Invalid font format, TTF or OTF only', 'phonebg'),
         false,
         ERROR
      );
      Html::redirect($self . '?tab=fonts');
   }

   /* Validate size */
   if ($size > $maxFontSize) {
      Session::addMessageAfterRedirect(
         __('Font file too large (max 2 MB)', 'phonebg'),
         false,
         ERROR
      );
      Html::redirect($self . '?tab=fonts');
   }

   /* Validate magic bytes: TTF = 00 01 00 00 or 74 72 75 65, OTF = 4F 54 54 4F */
   $handle = fopen($tmpFile, 'rb');
   $magic  = fread($handle, 4);
   fclose($handle);
   $validMagic = in_array($magic, [
      "\x00\x01\x00\x00",  // TrueType
      "\x74\x72\x75\x65",  // true
      "\x4F\x54\x54\x4F",  // OTTO (OTF/CFF)
   ], true);

   if (!$validMagic) {
      Session::addMessageAfterRedirect(
         __('Invalid font format, TTF or OTF only', 'phonebg'),
         false,
         ERROR
      );
      Html::redirect($self . '?tab=fonts');
   }

   /* Sanitize filename */
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
         /* If deleted font was selected, reset to default */
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

   /* Save font selection */
   $selFont = basename((string)($_POST['font_file'] ?? 'DejaVuSans.ttf'));
   $availFonts = PluginPhonebgPaths::listFonts();
   if (!in_array($selFont, $availFonts, true)) {
      $selFont = 'DejaVuSans.ttf';
   }
   PluginPhonebgConfig::set('font_file', $selFont);

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

$cfg        = PluginPhonebgConfig::getAll();
$availFonts = PluginPhonebgPaths::listFonts();

$nameX   = (int)$cfg['name_x'];
$nameY   = (int)$cfg['name_y'];
$mobileX = (int)$cfg['mobile_x'];
$mobileY = (int)$cfg['mobile_y'];

$jsConfirmUnsaved = addslashes(__("There are unsaved changes in Positions. Continue without saving?", "phonebg"));

$validTabs  = ['plantilla', 'posiciones', 'fonts'];
$activeTab  = in_array($_GET['tab'] ?? '', $validTabs, true) ? $_GET['tab'] : 'plantilla';

/* ============================================================
 * CARD with nav-tabs header
 * ============================================================ */
echo "<div class='card mt-2 shadow-sm'>";

echo "<div class='card-header py-0 position-relative' style='min-height:52px'>
         <div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1' style='z-index:2'>
            <i class='fs-2x ti ti-settings'></i>
         </div>
         <ul class='nav nav-tabs card-header-tabs ms-5' role='tablist'>";

echo "   <li class='nav-item' role='presentation'>
            <button class='nav-link" . ($activeTab === 'plantilla' ? ' active' : '') . "'
                    id='tab-plantilla-btn'
                    data-bs-toggle='tab'
                    data-bs-target='#phonebg-tab-plantilla'
                    type='button' role='tab'>
               <i class='ti ti-photo me-1'></i>" . __('Template', 'phonebg') . "
            </button>
         </li>";

if ($hasBase) {
   echo "<li class='nav-item' role='presentation'>
            <button class='nav-link" . ($activeTab === 'posiciones' ? ' active' : '') . "'
                    id='tab-posiciones-btn'
                    data-bs-toggle='tab'
                    data-bs-target='#phonebg-tab-posiciones'
                    type='button' role='tab'>
               <i class='ti ti-adjustments-horizontal me-1'></i>" . __('Positions', 'phonebg') . "
            </button>
         </li>";
}

echo "   <li class='nav-item' role='presentation'>
            <button class='nav-link" . ($activeTab === 'fonts' ? ' active' : '') . "'
                    id='tab-fonts-btn'
                    data-bs-toggle='tab'
                    data-bs-target='#phonebg-tab-fonts'
                    type='button' role='tab'>
               <i class='ti ti-typography me-1'></i>" . __('Fonts', 'phonebg') . "
            </button>
         </li>";

echo "   </ul>
      </div>";

echo "<div class='tab-content'>";

/* ============================================================
 * TAB 1 — Template
 * ============================================================ */
echo "<div class='tab-pane fade" . ($activeTab === 'plantilla' ? ' show active' : '') . "'
          id='phonebg-tab-plantilla' role='tabpanel'>";
echo "<form method='post' action='{$self}' enctype='multipart/form-data'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='card-body'>";

if ($hasBase) {
   $url = PluginPhonebgPaths::baseUrl();
   echo "<div class='mb-4'>
            <label class='form-label fw-semibold'>" . __('Current template', 'phonebg') . "</label>
            <div class='border rounded overflow-auto d-inline-block mb-3' style='max-width:100%'>
               <a href='{$url}' download title='" . __('Download current template', 'phonebg') . "'>
                  <img src='{$url}&t=" . time() . "'
                       style='display:block;width:auto;height:auto;max-width:none;cursor:pointer'>
               </a>
            </div>
            <div>
               <button type='submit' name='delete_base' class='btn btn-outline-danger gap-2'>
                  <i class='ti ti-trash'></i>" . __('Delete template', 'phonebg') . "
               </button>
            </div>
         </div>";
}

echo "<div class='mb-3'>
         <label class='form-label fw-semibold'>" . __('Upload new template', 'phonebg') . "</label>
         <input type='file' name='base' class='form-control' accept='image/png'
                onchange='phonebgPreviewNewBase(this)'>
         <div class='small mt-1'>" . __('Accepted format: PNG · Max 500 KB', 'phonebg') . "</div>
      </div>";

echo "<div id='pb-new-preview-wrap' class='mb-3 d-none'>
         <label class='form-label fw-semibold'>" . __('Preview new template', 'phonebg') . "</label>
         <div class='border rounded overflow-auto d-inline-block' style='max-width:100%'>
            <img id='pb-new-preview' style='display:block;width:auto;height:auto;max-width:none'>
         </div>
      </div>";

echo "</div>"; /* /card-body */
echo "<div class='card-footer text-end'>
         <button type='submit' name='save' class='btn btn-primary gap-2'>
            <i class='ti ti-device-floppy'></i>" . __('Save', 'phonebg') . "
         </button>
      </div>";
echo "</form>";
echo "</div>"; /* /tab-pane plantilla */

/* ============================================================
 * TAB 2 — Positions
 * ============================================================ */
if ($hasBase) {
   $baseUrl = PluginPhonebgPaths::baseUrl();

   echo "<div class='tab-pane fade" . ($activeTab === 'posiciones' ? ' show active' : '') . "'
             id='phonebg-tab-posiciones' role='tabpanel'>";
   echo "<form method='post' action='{$self}'>";
   echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
   echo "<div class='card-body'>";

   echo "<p class='small mb-3'><i class='ti ti-hand-move me-1'></i>"
      . __('Drag each label to set its position. X = 0 centers the text automatically.', 'phonebg') .
         "</p>";

   echo "<div id='pb-editor-outer' class='border rounded overflow-auto mb-3'>
            <div id='pb-editor-wrap'
                 style='position:relative;display:inline-block;line-height:0;
                        user-select:none;-webkit-user-select:none'>
               <img id='pb-template-img'
                    src='{$baseUrl}&t=" . time() . "'
                    draggable='false'
                    style='display:block;width:auto;height:auto;max-width:none;pointer-events:none'>
               <div id='pb-label-name'
                    style='position:absolute;top:0;left:0;
                           border:2px dashed #c0392b;background:rgba(192,57,43,0.13);
                           padding:3px 8px;cursor:grab;
                           white-space:nowrap;font-size:" . (int)$cfg['name_size'] . "px;font-weight:600;
                           color:#c0392b;line-height:1.4;border-radius:3px;
                           user-select:none;-webkit-user-select:none'>
                  " . __('Device name', 'phonebg') . "
               </div>
               <div id='pb-label-mobile'
                    style='position:absolute;top:0;left:0;
                           border:2px dashed #2980b9;background:rgba(41,128,185,0.13);
                           padding:3px 8px;cursor:grab;
                           white-space:nowrap;font-size:" . (int)$cfg['mobile_size'] . "px;font-weight:600;
                           color:#2980b9;line-height:1.4;border-radius:3px;
                           user-select:none;-webkit-user-select:none'>
                  " . __('Line number', 'phonebg') . "
               </div>
            </div>
         </div>";

   echo "<table class='table table-sm table-hover align-middle' style='max-width:560px'>
            <thead class='table-light'>
               <tr>
                  <th class='fw-semibold'>" . __('Field', 'phonebg') . "</th>
                  <th class='fw-semibold'>" . __('Font size (px)', 'phonebg') . "</th>
                  <th class='fw-semibold'>X</th>
                  <th class='fw-semibold'>Y</th>
               </tr>
            </thead>
            <tbody>
               <tr>
                  <td>
                     <span class='badge' style='background:#c0392b'>&nbsp;</span>
                     <span class='ms-1'>" . __('Device name', 'phonebg') . "</span>
                  </td>
                  <td><input type='number' name='name_size' id='inp-name-size'
                             value='" . (int)$cfg['name_size'] . "' min='8' max='300'
                             class='form-control form-control-sm' style='width:75px'></td>
                  <td><input type='number' name='name_x' id='inp-name-x'
                             value='{$nameX}' min='0'
                             class='form-control form-control-sm' style='width:75px'></td>
                  <td><input type='number' name='name_y' id='inp-name-y'
                             value='{$nameY}' min='0'
                             class='form-control form-control-sm' style='width:75px'></td>
               </tr>
               <tr>
                  <td>
                     <span class='badge' style='background:#2980b9'>&nbsp;</span>
                     <span class='ms-1'>" . __('Line number', 'phonebg') . "</span>
                  </td>
                  <td><input type='number' name='mobile_size' id='inp-mobile-size'
                             value='" . (int)$cfg['mobile_size'] . "' min='8' max='300'
                             class='form-control form-control-sm' style='width:75px'></td>
                  <td><input type='number' name='mobile_x' id='inp-mobile-x'
                             value='{$mobileX}' min='0'
                             class='form-control form-control-sm' style='width:75px'></td>
                  <td><input type='number' name='mobile_y' id='inp-mobile-y'
                             value='{$mobileY}' min='0'
                             class='form-control form-control-sm' style='width:75px'></td>
               </tr>
               <tr>
                  <td>" . __('Font color', 'phonebg') . "</td>
                  <td colspan='3'>
                     <div class='d-flex align-items-center gap-2'>
                        <input type='text' name='font_color' id='inp-font-color-text'
                               value='" . htmlspecialchars((string)$cfg['font_color'], ENT_QUOTES, 'UTF-8') . "'
                               class='form-control form-control-sm font-monospace' style='width:100px'
                               maxlength='7' pattern='#[0-9a-fA-F]{6}' placeholder='#000000'>
                        <input type='color' id='inp-font-color-swatch'
                               value='" . htmlspecialchars((string)$cfg['font_color'], ENT_QUOTES, 'UTF-8') . "'
                               class='border rounded'
                               style='width:36px;height:31px;padding:2px;cursor:pointer;background:none'>
                     </div>
                  </td>
               </tr>
               <tr>
                  <td>" . __('Font', 'phonebg') . "</td>
                  <td colspan='3'>";

   if (!empty($availFonts)) {
      echo "<select name='font_file' id='inp-font-file' class='form-select form-select-sm' style='max-width:260px'>";
      foreach ($availFonts as $fname) {
         $sel = ($cfg['font_file'] === $fname) ? " selected" : '';
         echo "<option value='" . htmlspecialchars($fname, ENT_QUOTES) . "'{$sel}>"
            . htmlspecialchars($fname, ENT_QUOTES) . "</option>";
      }
      echo "</select>";
   } else {
      echo "<span class='small'>" . __('No fonts uploaded yet', 'phonebg') . "</span>";
   }

   echo "         </td>
               </tr>
            </tbody>
         </table>";

   echo "</div>"; /* /card-body */
   echo "<div class='card-footer d-flex justify-content-between align-items-center'>
            <button type='submit' name='reset_positions' class='btn btn-outline-secondary gap-2'>
               <i class='ti ti-refresh'></i>" . __('Reset to defaults', 'phonebg') . "
            </button>
            <button type='submit' name='save_positions' class='btn btn-primary gap-2'>
               <i class='ti ti-device-floppy'></i>" . __('Save', 'phonebg') . "
            </button>
         </div>";
   echo "</form>";
   echo "</div>"; /* /tab-pane posiciones */
}

/* ============================================================
 * TAB 3 — Fonts
 * ============================================================ */
echo "<div class='tab-pane fade" . ($activeTab === 'fonts' ? ' show active' : '') . "'
          id='phonebg-tab-fonts' role='tabpanel'>";
echo "<div class='card-body'>";

/* ---- Section: Upload ---- */
echo "<div class='mb-4'>
         <p class='fw-semibold mb-1'>" . __('Upload font', 'phonebg') . "</p>
         <p class='small mb-2'>" . __('Accepted formats: TTF · OTF · Max 2 MB', 'phonebg') . "</p>";

echo "<form method='post' action='{$self}' enctype='multipart/form-data'>
         " . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . "
         <div class='input-group' style='max-width:480px'>
            <input type='file' name='font_file' class='form-control' accept='.ttf,.otf'>
            <button type='submit' name='upload_font' class='btn btn-primary gap-1'>
               <i class='ti ti-upload'></i>" . __('Upload', 'phonebg') . "
            </button>
         </div>
      </form>";
echo "</div>"; /* /upload section */

/* ---- Section: Installed fonts ---- */
echo "<div>
         <p class='fw-semibold mb-1'>" . __('Installed fonts', 'phonebg') . "</p>
         <p class='small mb-3'>" . __('Built-in font (DejaVu Sans) is always available as fallback and cannot be deleted.', 'phonebg') . "</p>";

if (!empty($availFonts)) {
   echo "<table class='table table-sm table-hover mb-0' style='max-width:560px'>
            <thead class='table-light'>
               <tr>
                  <th>" . __('Font file', 'phonebg') . "</th>
                  <th>" . __('Description', 'phonebg') . "</th>
                  <th style='width:60px'></th>
               </tr>
            </thead><tbody>";
   foreach ($availFonts as $fname) {
      $isDefault = ($fname === 'DejaVuSans.ttf');
      echo "<tr>
               <td class='align-middle'>
                  <i class='ti ti-typography me-1 text-body-secondary'></i>" . htmlspecialchars($fname, ENT_QUOTES) . "
               </td>
               <td class='align-middle small mb-0'>";
      if ($isDefault) {
         echo "<i class='ti ti-lock me-1'></i>" . __('Default fallback font — cannot be deleted', 'phonebg');
      } else {
         echo "<i class='ti ti-user me-1'></i>" . __('User uploaded font', 'phonebg');
      }
      echo "   </td>
               <td class='align-middle text-end'>";
      if ($isDefault) {
         echo "<button type='button' class='btn btn-sm btn-outline-secondary' disabled
                       title='" . htmlspecialchars(__('The default font cannot be deleted', 'phonebg'), ENT_QUOTES) . "'>
                  <i class='ti ti-lock'></i>
               </button>";
      } else {
         echo "<form method='post' action='{$self}' style='display:inline'>
                  " . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . "
                  <input type='hidden' name='delete_font' value='" . htmlspecialchars($fname, ENT_QUOTES) . "'>
                  <button type='submit' class='btn btn-sm btn-outline-danger'
                          onclick=\"return confirm('" . addslashes(__('Delete this font?', 'phonebg')) . "')\">
                     <i class='ti ti-trash'></i>
                  </button>
               </form>";
      }
      echo "   </td>
            </tr>";
   }
   echo "</tbody></table>";
} else {
   echo "<p class='small'>" . __('No fonts uploaded yet', 'phonebg') . "</p>";
}

echo "</div>"; /* /installed fonts section */
echo "</div>"; /* /card-body */
echo "</div>"; /* /tab-pane fonts */

echo "</div>"; /* /tab-content */
echo "</div>"; /* /card */

/* ============================================================
 * JavaScript
 * ============================================================ */
if ($hasBase) {
   echo <<<HTML
<script>
/* ---- Template upload preview ---- */
function phonebgPreviewNewBase(input) {
   var wrap    = document.getElementById('pb-new-preview-wrap');
   var preview = document.getElementById('pb-new-preview');
   wrap.classList.add('d-none');
   preview.src = '';
   if (!input.files[0]) return;
   if (input.files[0].type !== 'image/png') {
      alert('Invalid format. PNG only.');
      input.value = '';
      return;
   }
   if (input.files[0].size > 500 * 1024) {
      alert('File exceeds maximum allowed size (500 KB).');
      input.value = '';
      return;
   }
   var reader = new FileReader();
   reader.onload = function(e) {
      preview.src = e.target.result;
      wrap.classList.remove('d-none');
   };
   reader.readAsDataURL(input.files[0]);
}

/* ---- Position drag editor ---- */
(function () {
   var img = document.getElementById('pb-template-img');
   if (!img) return;

   var savedCoords = {
      name:   { x: {$nameX},   y: {$nameY}   },
      mobile: { x: {$mobileX}, y: {$mobileY} }
   };

   function placeLabel(field) {
      var label = document.getElementById('pb-label-' + field);
      var wrap  = document.getElementById('pb-editor-wrap');
      if (!label || !wrap) return;
      var realX = parseInt(document.getElementById('inp-' + field + '-x').value) || 0;
      var realY = parseInt(document.getElementById('inp-' + field + '-y').value) || 0;
      /* Real-size image: coords map 1:1. Y baseline = bottom of label */
      var dispY = realY - label.offsetHeight;
      var dispX;
      if (realX <= 0) {
         dispX = Math.round((wrap.offsetWidth - label.offsetWidth) / 2);
      } else {
         dispX = realX;
      }
      label.style.left = Math.max(0, dispX) + 'px';
      label.style.top  = Math.max(0, dispY) + 'px';
   }

   function makeDraggable(field) {
      var label = document.getElementById('pb-label-' + field);
      if (!label) return;

      label.addEventListener('mousedown', function (e) {
         e.preventDefault();
         e.stopPropagation();
         var startMX   = e.clientX;
         var startMY   = e.clientY;
         var startLeft = label.offsetLeft;
         var startTop  = label.offsetTop;
         var wrap      = document.getElementById('pb-editor-wrap');
         label.style.cursor = 'grabbing';

         function onMove(ev) {
            ev.preventDefault();
            var newLeft = Math.max(0, Math.min(wrap.offsetWidth  - label.offsetWidth,  startLeft + ev.clientX - startMX));
            var newTop  = Math.max(0, Math.min(wrap.offsetHeight - label.offsetHeight, startTop  + ev.clientY - startMY));
            label.style.left = newLeft + 'px';
            label.style.top  = newTop  + 'px';
            document.getElementById('inp-' + field + '-x').value = Math.round(newLeft);
            document.getElementById('inp-' + field + '-y').value = Math.round(newTop + label.offsetHeight);
         }

         function onUp() {
            label.style.cursor = 'grab';
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup',   onUp);
            markDirty();
         }

         document.addEventListener('mousemove', onMove);
         document.addEventListener('mouseup',   onUp);
      });
   }

   function initEditor() {
      placeLabel('name');
      placeLabel('mobile');
      makeDraggable('name');
      makeDraggable('mobile');

      ['name', 'mobile'].forEach(function(field) {
         ['x', 'y'].forEach(function(axis) {
            var inp = document.getElementById('inp-' + field + '-' + axis);
            if (inp) inp.addEventListener('input', function() { placeLabel(field); });
         });
         var sizeInp = document.getElementById('inp-' + field + '-size');
         if (sizeInp) sizeInp.addEventListener('input', function() {
            var lbl = document.getElementById('pb-label-' + field);
            if (lbl) lbl.style.fontSize = Math.max(8, parseInt(this.value) || 8) + 'px';
            placeLabel(field);
         });
      });
   }

   function tryInit() {
      if (img.complete && img.naturalHeight > 0) {
         initEditor();
      } else {
         img.addEventListener('load', initEditor);
      }
   }

   /* Re-init when switching to Posiciones tab */
   var tabBtn = document.getElementById('tab-posiciones-btn');
   if (tabBtn) {
      tabBtn.addEventListener('shown.bs.tab', function () {
         setTimeout(tryInit, 50);
      });
   }

   /* Unsaved changes warning */
   var pbDirty = false;
   var pbForm  = document.querySelector('#phonebg-tab-posiciones form');

   function markDirty() {
      pbDirty = true;
   }

   if (pbForm) {
      pbForm.querySelectorAll('input, select').forEach(function(el) {
         el.addEventListener('input', markDirty);
         el.addEventListener('change', markDirty);
      });
      pbForm.addEventListener('submit', function() { pbDirty = false; });
   }

   var tabPlantillaBtn = document.getElementById('tab-plantilla-btn');
   if (tabPlantillaBtn) {
      tabPlantillaBtn.addEventListener('click', function(e) {
         if (pbDirty) {
            if (!confirm('{$jsConfirmUnsaved}')) {
               e.stopImmediatePropagation();
               e.preventDefault();
            }
         }
      });
   }

   /* Color swatch ↔ text input sync */
   var colorText   = document.getElementById('inp-font-color-text');
   var colorSwatch = document.getElementById('inp-font-color-swatch');
   if (colorText && colorSwatch) {
      colorSwatch.addEventListener('input', function() {
         colorText.value = colorSwatch.value;
         markDirty();
      });
      colorText.addEventListener('input', function() {
         if (/^#[0-9a-fA-F]{6}$/.test(colorText.value)) {
            colorSwatch.value = colorText.value;
         }
         markDirty();
      });
      colorText.addEventListener('change', function() {
         if (/^#[0-9a-fA-F]{6}$/.test(colorText.value)) {
            colorSwatch.value = colorText.value;
         }
      });
   }

   tryInit();
})();
</script>
HTML;
}

Html::footer();
