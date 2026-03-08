<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginPhonebgPhone extends CommonGLPI {

   public static function getTypeName($nb = 0) {
      return __('Fondo Celular', 'phonebg');
   }

   /* =====================================================
    * TAB (GLPI 11 requires array return)
    * ===================================================== */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): array
   {
      if (!$item instanceof Phone) {
         return [];
      }

      return [
         1 => "<span class='d-flex align-items-center'>
                  <i class='ti ti-photo me-2'></i>" .
                  __('Fondo', 'phonebg') .
               "</span>"
      ];
   }

   /* =====================================================
    * TAB CONTENT
    * ===================================================== */
   public static function displayTabContentForItem(
      CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0
   ): bool {
      if ($item instanceof Phone) {
         self::showTab($item);
      }
      return true;
   }

   /* =====================================================
    * TAB UI
    * ===================================================== */
   private static function showTab(Phone $phone): void
   {
      $basefile    = PluginPhonebgPaths::basePath();
      $hasBase     = is_readable($basefile);
      $downloadUrl = Plugin::getWebDir('phonebg') . '/front/download.php';
      $previewUrl  = $downloadUrl . '?phoneid=' . (int)$phone->getID() . '&preview=1';
      $phoneId     = (int)$phone->getID();
      $modalId     = 'pb-preview-modal-' . $phoneId;

      Html::displayMessageAfterRedirect();

      $phone_name = $phone->getName();

      echo "<div class='card mt-3 shadow-sm'>";

      echo "<div class='card-header pt-2 position-relative'>
               <div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>
                  <i class='fs-2x ti ti-photo'></i>
               </div>
               <h4 class='card-title ms-5 mb-0'>" .
                  __('Generador de fondo de pantalla para celular', 'phonebg') .
               "</h4>
            </div>";

      echo "<div class='card-body text-center'>";

      if (!$hasBase) {
         echo "<div class='alert alert-warning text-start'>
                  <i class='ti ti-alert-triangle me-2'></i>
                  <strong>" . __('No se encontró una plantilla base.', 'phonebg') . "</strong><br>
                  " . __('Por favor, sube una imagen PNG base en la configuración del complemento.', 'phonebg') . "
               </div>";
      }

      echo "<p class='text-muted mb-3'>" .
               sprintf(
                  __('Generar fondo para: %s', 'phonebg'),
                  htmlspecialchars($phone_name, ENT_QUOTES, 'UTF-8')
               ) .
            "</p>";

      echo "<div class='mt-4 d-flex justify-content-center gap-3'>";

      /* Preview button */
      echo "<button type='button'
                    class='btn btn-outline-secondary'
                    " . (!$hasBase ? 'disabled' : '') . "
                    onclick='phonebgOpenPreview(" . $phoneId . ")'>
               <i class='ti ti-eye me-2'></i>
               " . __('Vista previa', 'phonebg') . "
            </button>";

      /* Download form */
      echo "<form method='get' action='{$downloadUrl}' style='display:inline'>
               <input type='hidden' name='phoneid' value='{$phoneId}'>
               <button type='submit'
                       class='btn btn-primary'
                       " . (!$hasBase ? 'disabled' : '') . ">
                  <i class='ti ti-download me-2'></i>
                  " . __('Descargar fondo', 'phonebg') . "
               </button>
            </form>";

      echo "</div>"; /* /d-flex */

      echo "</div></div>"; /* /card-body /card */

      /* =====================================================
       * Preview modal
       * ===================================================== */
      echo "<div class='modal fade' id='{$modalId}' tabindex='-1' aria-hidden='true'>
               <div class='modal-dialog modal-dialog-centered modal-lg'>
                  <div class='modal-content'>
                     <div class='modal-header'>
                        <h5 class='modal-title'>
                           <i class='ti ti-photo me-2'></i>" .
                           __('Vista previa del fondo', 'phonebg') .
                        "</h5>
                        <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                     </div>
                     <div class='modal-body text-center' id='pb-modal-body-{$phoneId}'>
                        <div class='pb-modal-spinner'>
                           <div class='spinner-border text-primary' role='status'>
                              <span class='visually-hidden'>Cargando...</span>
                           </div>
                        </div>
                     </div>
                     <div class='modal-footer'>
                        <a id='pb-modal-download-{$phoneId}'
                           href='{$downloadUrl}?phoneid={$phoneId}'
                           class='btn btn-primary gap-2'>
                           <i class='ti ti-download'></i>
                           " . __('Descargar fondo', 'phonebg') . "
                        </a>
                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                           <i class='ti ti-x me-1'></i>" . __('Cerrar', 'phonebg') . "
                        </button>
                     </div>
                  </div>
               </div>
            </div>";

      /* JS */
      $safePreviewUrl = htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8');
      echo <<<HTML
<script>
function phonebgOpenPreview(phoneId) {
   var modalEl  = document.getElementById('pb-preview-modal-' + phoneId);
   var body     = document.getElementById('pb-modal-body-' + phoneId);
   var previewUrl = '{$safePreviewUrl}';

   /* Reset body to spinner */
   body.innerHTML = '<div class="pb-modal-spinner"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

   var modal = new bootstrap.Modal(modalEl);
   modal.show();

   /* Load image after modal is visible */
   modalEl.addEventListener('shown.bs.modal', function handler() {
      modalEl.removeEventListener('shown.bs.modal', handler);
      var img = new Image();
      img.onload = function() {
         body.innerHTML = '';
         img.style.maxWidth  = '100%';
         img.style.height    = 'auto';
         img.style.display   = 'block';
         img.style.margin    = '0 auto';
         body.appendChild(img);
      };
      img.onerror = function() {
         body.innerHTML = '<div class="alert alert-danger">No se pudo cargar la vista previa.</div>';
      };
      img.src = previewUrl + '&t=' + Date.now();
   });
}
</script>
HTML;
   }
}
