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
$maxSize     = 500 * 1024;
$allowedMime = ['image/png'];
$baseFile    = PluginPhonebgPaths::basePath();
$hasBase     = is_readable($baseFile);

/* Ensure storage directory exists */
$templatesDir = PluginPhonebgPaths::filesDir() . '/templates';
if (!is_dir($templatesDir)) {
   mkdir($templatesDir, 0755, true);
}

/* ==========================
 * POST: delete template
 * ========================== */
if (isset($_POST['delete_base']) && $hasBase) {
   if (unlink($baseFile)) {
      Session::addMessageAfterRedirect(__('Plantilla eliminada correctamente', 'phonebg'), false, INFO);
   } else {
      Session::addMessageAfterRedirect(__('No se pudo eliminar la plantilla', 'phonebg'), false, ERROR);
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
         __('Archivo demasiado grande (máx 500 KB)', 'phonebg'),
         false,
         ERROR
      );
   } elseif (!in_array($mime, $allowedMime, true)) {
      Session::addMessageAfterRedirect(
         __('Formato inválido, solo PNG', 'phonebg'),
         false,
         ERROR
      );
   } else {
      if (move_uploaded_file($tmpFile, $baseFile)) {
         chmod($baseFile, 0644);
         Session::addMessageAfterRedirect(__('Fondo guardado correctamente', 'phonebg'), false, INFO);
      } else {
         Session::addMessageAfterRedirect(__('No se pudo guardar la plantilla', 'phonebg'), false, ERROR);
      }
   }

   Html::redirect($self);
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

   Session::addMessageAfterRedirect(__('Posiciones guardadas correctamente', 'phonebg'), false, INFO);
   Html::redirect($self . '?tab=posiciones');
}

/* ==========================
 * POST: reset positions
 * ========================== */
if (isset($_POST['reset_positions'])) {
   PluginPhonebgConfig::resetToDefaults();
   Session::addMessageAfterRedirect(__('Posiciones restablecidas a valores por defecto', 'phonebg'), false, INFO);
   Html::redirect($self);
}

/* ==========================
 * Page header
 * ========================== */
Html::header(
   __('Fondo de pantalla celulares', 'phonebg'),
   $self,
   'config',
   'plugins'
);

Html::displayMessageAfterRedirect();

$cfg = PluginPhonebgConfig::getAll();

$nameX   = (int)$cfg['name_x'];
$nameY   = (int)$cfg['name_y'];
$mobileX = (int)$cfg['mobile_x'];
$mobileY = (int)$cfg['mobile_y'];

$jsConfirmUnsaved = addslashes(__("Hay cambios sin guardar en Posiciones. ¿Continuar sin guardar?", "phonebg"));

$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'posiciones' ? 'posiciones' : 'plantilla';

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
               <i class='ti ti-photo me-1'></i>" . __('Plantilla', 'phonebg') . "
            </button>
         </li>";

if ($hasBase) {
   echo "<li class='nav-item' role='presentation'>
            <button class='nav-link" . ($activeTab === 'posiciones' ? ' active' : '') . "'
                    id='tab-posiciones-btn'
                    data-bs-toggle='tab'
                    data-bs-target='#phonebg-tab-posiciones'
                    type='button' role='tab'>
               <i class='ti ti-adjustments-horizontal me-1'></i>" . __('Posiciones', 'phonebg') . "
            </button>
         </li>";
}

echo "   </ul>
      </div>";

echo "<div class='tab-content'>";

/* ============================================================
 * TAB 1 — Plantilla
 * ============================================================ */
echo "<div class='tab-pane fade" . ($activeTab === 'plantilla' ? ' show active' : '') . "'
          id='phonebg-tab-plantilla' role='tabpanel'>";
echo "<form method='post' action='{$self}' enctype='multipart/form-data'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='card-body'>";

if ($hasBase) {
   $url = PluginPhonebgPaths::baseUrl();
   echo "<div class='mb-4'>
            <label class='form-label fw-bold'>" . __('Plantilla actual', 'phonebg') . "</label><br>
            <div style='border:1px solid #ccc;padding:6px;display:inline-block;overflow:auto;max-width:100%'>
               <a href='{$url}' download title='" . __('Descargar plantilla actual', 'phonebg') . "'>
                  <img src='{$url}&t=" . time() . "'
                       style='display:block;width:auto;height:auto;max-width:none;cursor:pointer'>
               </a>
            </div><br><br>
            <button type='submit' name='delete_base' class='btn btn-danger gap-2'>
               <i class='ti ti-trash'></i>" . __('Eliminar plantilla', 'phonebg') . "
            </button>
         </div>";
}

echo "<div class='mb-3'>
         <label class='form-label fw-bold'>" . __('Cargar nueva plantilla', 'phonebg') . "</label>
         <input type='file' name='base' class='form-control' accept='image/png'
                onchange='phonebgPreviewNewBase(this)'>
         <small class='mt-1 d-block'>PNG · Máx 500 KB</small>
      </div>";

echo "<div id='pb-new-preview-wrap' class='mb-3 d-none'
           style='border:1px dashed #999;padding:6px;display:inline-block;overflow:auto;max-width:100%'>
         <label class='form-label fw-bold'>" . __('Vista previa de la nueva base', 'phonebg') . "</label><br>
         <img id='pb-new-preview' style='display:block;width:auto;height:auto;max-width:none'>
      </div>";

echo "</div>"; /* /card-body */
echo "<div class='card-footer text-end'>
         <button type='submit' name='save' class='btn btn-primary gap-2'>
            <i class='ti ti-device-floppy'></i>" . __('Guardar', 'phonebg') . "
         </button>
      </div>";
echo "</form>";
echo "</div>"; /* /tab-pane plantilla */

/* ============================================================
 * TAB 2 — Posiciones
 * ============================================================ */
if ($hasBase) {
   $baseUrl = PluginPhonebgPaths::baseUrl();

   echo "<div class='tab-pane fade" . ($activeTab === 'posiciones' ? ' show active' : '') . "'
             id='phonebg-tab-posiciones' role='tabpanel'>";
   echo "<form method='post' action='{$self}'>";
   echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
   echo "<div class='card-body'>";

   echo "<p class='text-muted mb-3'>
            <i class='ti ti-hand-move me-1'></i>
            " . __('Arrastra cada etiqueta para definir su posición. X = 0 centra el texto automáticamente.', 'phonebg') . "
         </p>";

   echo "<div id='pb-editor-outer' style='overflow:auto;max-width:100%;margin-bottom:1rem;border:1px solid #ddd'>
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
                  " . __('Nombre del equipo', 'phonebg') . "
               </div>
               <div id='pb-label-mobile'
                    style='position:absolute;top:0;left:0;
                           border:2px dashed #2980b9;background:rgba(41,128,185,0.13);
                           padding:3px 8px;cursor:grab;
                           white-space:nowrap;font-size:" . (int)$cfg['mobile_size'] . "px;font-weight:600;
                           color:#2980b9;line-height:1.4;border-radius:3px;
                           user-select:none;-webkit-user-select:none'>
                  " . __('Número de línea', 'phonebg') . "
               </div>
            </div>
         </div>";

   echo "<table class='table table-sm table-bordered' style='max-width:520px'>
            <thead class='table-light'>
               <tr>
                  <th>" . __('Campo', 'phonebg') . "</th>
                  <th>" . __('Tamaño de fuente (px)', 'phonebg') . "</th>
                  <th>X</th>
                  <th>Y</th>
               </tr>
            </thead>
            <tbody>
               <tr>
                  <td><span style='color:#c0392b'>&#9632;</span> " . __('Nombre del equipo', 'phonebg') . "</td>
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
                  <td><span style='color:#2980b9'>&#9632;</span> " . __('Número de línea', 'phonebg') . "</td>
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
                  <td>" . __('Color de fuente', 'phonebg') . "</td>
                  <td colspan='3'>
                     <div class='d-flex align-items-center gap-2'>
                        <input type='text' name='font_color' id='inp-font-color-text'
                               value='" . htmlspecialchars((string)$cfg['font_color'], ENT_QUOTES, 'UTF-8') . "'
                               class='form-control form-control-sm font-monospace' style='width:100px'
                               maxlength='7' pattern='#[0-9a-fA-F]{6}' placeholder='#000000'>
                        <input type='color' id='inp-font-color-swatch'
                               value='" . htmlspecialchars((string)$cfg['font_color'], ENT_QUOTES, 'UTF-8') . "'
                               style='width:36px;height:36px;padding:2px;border:1px solid #ccc;border-radius:4px;cursor:pointer;background:none'>
                     </div>
                  </td>
               </tr>
            </tbody>
         </table>";

   echo "</div>"; /* /card-body */
   echo "<div class='card-footer d-flex justify-content-between align-items-center'>
            <button type='submit' name='reset_positions' class='btn btn-outline-secondary gap-2'>
               <i class='ti ti-refresh'></i>" . __('Restaurar valores por defecto', 'phonebg') . "
            </button>
            <button type='submit' name='save_positions' class='btn btn-primary gap-2'>
               <i class='ti ti-device-floppy'></i>" . __('Guardar', 'phonebg') . "
            </button>
         </div>";
   echo "</form>";
   echo "</div>"; /* /tab-pane posiciones */
}

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
      alert('Formato inválido. Solo se permite PNG.');
      input.value = '';
      return;
   }
   if (input.files[0].size > 500 * 1024) {
      alert('El archivo supera el tamaño máximo permitido (500 KB).');
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

   /* Re-init when switching to Posiciones tab (img may not have rendered yet) */
   var tabBtn = document.getElementById('tab-posiciones-btn');
   if (tabBtn) {
      tabBtn.addEventListener('shown.bs.tab', function () {
         /* Give browser one frame to render the image */
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
      pbForm.querySelectorAll('input').forEach(function(inp) {
         inp.addEventListener('input', markDirty);
         inp.addEventListener('change', markDirty);
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
