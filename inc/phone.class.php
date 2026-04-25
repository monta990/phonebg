<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginPhonebgPhone extends CommonGLPI {

   public static function getTypeName($nb = 0) {
      return __('Phone Background', 'phonebg');
   }

   /* =====================================================
    * TAB (array return required by GLPI 11+, accepted by 10+)
    * ===================================================== */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): array
   {
      if (!$item instanceof Phone) {
         return [];
      }

      return [
         1 => "<span class='d-flex align-items-center'>
                  <i class='ti ti-photo me-2'></i>" .
                  __('Background', 'phonebg') .
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
      $phoneId     = (int)$phone->getID();
      $previewUrl  = $downloadUrl . '?phoneid=' . $phoneId . '&preview=1';

      Html::displayMessageAfterRedirect();

      echo PluginPhonebgRenderer::render('phone_tab.html.twig', [
         'has_base'        => $hasBase,
         'phone_id'        => $phoneId,
         'phone_line_label' => sprintf(
            __('Generate background for: %s', 'phonebg'),
            $phone->getName()
         ),
         'download_url'    => $downloadUrl,
         'preview_url'     => $previewUrl,
         'preview_url_js'  => json_encode($previewUrl),
         'modal_id'        => 'pb-preview-modal-' . $phoneId,
      ]);
   }
}
