<?php
declare(strict_types=1);

namespace GlpiPlugin\Phonebg\Model;

use GlpiPlugin\Phonebg\Service\Paths;
use GlpiPlugin\Phonebg\Service\Renderer;


if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class Phone extends \CommonGLPI {

   public static function getTypeName($nb = 0) {
      return __('Phone Background', 'phonebg');
   }

   /* =====================================================
    * TAB (array return required by GLPI 11+, accepted by 10+)
    * ===================================================== */
   public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0): array
   {
      if (!$item instanceof \Phone) {
         return [];
      }

      return [
         1 => self::createTabEntry(__('Background', 'phonebg'), 0, null, 'ti ti-photo'),
      ];
   }

   /* =====================================================
    * TAB CONTENT
    * ===================================================== */
   public static function displayTabContentForItem(
      \CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0
   ): bool {
      if ($item instanceof \Phone) {
         self::showTab($item);
      }
      return true;
   }

   /* =====================================================
    * TAB UI
    * ===================================================== */
   private static function showTab(\Phone $phone): void
   {
      $basefile    = Paths::basePath();
      $hasBase     = is_readable($basefile);
      $downloadUrl = Paths::backgroundUrl();
      $sendUrl     = Paths::sendUrl();
      $phoneId     = (int)$phone->getID();
      $previewUrl  = $downloadUrl . '?phoneid=' . $phoneId . '&preview=1';
      $assignedUserId = (int)($phone->fields['users_id'] ?? 0);
      $hasEmail    = false;
      if ($assignedUserId > 0) {
         global $DB;
         $hasEmail = (bool)$DB->request([
            'COUNT' => 'cnt',
            'FROM'  => 'glpi_useremails',
            'WHERE' => ['users_id' => $assignedUserId, 'is_default' => 1],
         ])->current()['cnt'];
      }

      \Html::displayMessageAfterRedirect();

      echo Renderer::render('phone_tab.html.twig', [
         'has_base'         => $hasBase,
         'phone_id'         => $phoneId,
         'phone_line_label' => sprintf(
            __('Generate background for: %s', 'phonebg'),
            $phone->getName()
         ),
         'download_url'     => $downloadUrl,
         'send_url'         => $sendUrl,
         'js_url'           => Paths::webDir() . '/asset/phonebg.js',
         'preview_url'      => $previewUrl,
         'modal_id'         => 'pb-preview-modal-' . $phoneId,
         'has_assigned_user' => $assignedUserId > 0,
         'has_email'        => $hasEmail,
         'csrf_token'       => version_compare(GLPI_VERSION, '12.0.0', '<')
            ? \Session::getNewCSRFToken()
            : null,
      ]);
   }
}
