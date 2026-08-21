<?php

declare(strict_types=1);

namespace GlpiPlugin\Phonebg\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Phonebg\Service\Background;
use GlpiPlugin\Phonebg\Service\Config;
use GlpiPlugin\Phonebg\Service\Paths;
use GlpiPlugin\Phonebg\Service\Renderer;
use GlpiPlugin\Phonebg\Service\VersionChecker;
use GlpiPlugin\Phonebg\Service\Mail;
use GlpiPlugin\Phonebg\Model\Phone as PhoneTab;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class PhonebgController extends AbstractController
{
   #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
   #[Route('/asset/phonebg.js', name: 'plugin_phonebg_asset_js', methods: ['GET'])]
   public function assetJs(): Response
   {
      $path = Paths::pluginDir() . '/js/phonebg.js';
      if (!is_readable($path)) {
         return new Response('', Response::HTTP_NOT_FOUND);
      }

      $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($path);
      $response->headers->set('Content-Type', 'application/javascript; charset=UTF-8');
      $response->headers->set('Cache-Control', 'public, max-age=3600');

      return $response;
   }

   #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
   #[Route('/config', name: 'plugin_phonebg_config', methods: ['GET', 'POST'])]
   public function config(Request $request): Response
   {
      \Session::checkRight('config', UPDATE);

      return $request->isMethod('POST')
         ? $this->handleConfigPost($request)
         : $this->renderConfig($request);
   }

   #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
   #[Route('/background', name: 'plugin_phonebg_background', methods: ['GET'])]
   public function background(Request $request): Response
   {
      $phone_id = (int) $request->query->get('phoneid', 0);
      $is_preview = $request->query->get('preview') === '1';
      $phone = new \Phone();

      if ($phone_id <= 0 || !$phone->getFromDB($phone_id)) {
         if ($is_preview) {
            return new JsonResponse(
               ['error' => __('Phone not found.', 'phonebg')],
               Response::HTTP_NOT_FOUND
            );
         }

         return new RedirectResponse(Paths::phoneUrl(0));
      }

      if (!$this->canAccessPhone($phone)) {
         if ($is_preview) {
            return new JsonResponse(
               ['error' => __('You are not allowed to access this phone background.', 'phonebg')],
               Response::HTTP_FORBIDDEN
            );
         }

         throw new AccessDeniedHttpException();
      }

      $errors = Background::checkRequirements();
      if (!empty($errors)) {
         if ($is_preview) {
            return new JsonResponse(
               ['error' => implode(' ', $errors)],
               Response::HTTP_INTERNAL_SERVER_ERROR
            );
         }

         foreach ($errors as $message) {
            \Session::addMessageAfterRedirect($message, false, ERROR);
         }

         return new RedirectResponse($this->phoneBackUrl($phone_id));
      }

      try {
         $file = Background::generatePNG($phone);
      } catch (Throwable $e) {
         \Toolbox::logInFile(
            'phonebg',
            'background generatePNG: ' . $e->getMessage(),
            false,
            false
         );
         $file = '';
      }

      if (!is_file($file)) {
         $message = Background::$lastError ?: __('Failed to generate background image.', 'phonebg');

         if ($is_preview) {
            return new JsonResponse(
               ['error' => $message],
               Response::HTTP_UNPROCESSABLE_ENTITY
            );
         }

         \Session::addMessageAfterRedirect($message, false, ERROR);
         return new RedirectResponse($this->phoneBackUrl($phone_id));
      }

      $safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $phone->getName());
      $response = new BinaryFileResponse($file);
      $response->headers->set('Cache-Control', 'no-store');
      $response->setContentDisposition(
         $is_preview
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
         'background_' . $safe_name . '.png'
      );
      $response->deleteFileAfterSend(true);

      return $response;
   }

   #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
   #[Route('/resource/base', name: 'plugin_phonebg_resource_base', methods: ['GET'])]
   public function resourceBase(Request $request): Response
   {
      \Session::checkRight('config', READ);
      $path = Paths::basePath();
      if (!is_readable($path)) {
         return new Response('', Response::HTTP_NOT_FOUND);
      }

      $response = new BinaryFileResponse($path);
      $mtime = @filemtime($path);
      $etag = @md5_file($path);

      if ($mtime !== false) {
         $response->setLastModified((new \DateTimeImmutable())->setTimestamp($mtime));
      }
      if ($etag !== false) {
         $response->setEtag($etag);
      }

      $response->setContentDisposition(
         ResponseHeaderBag::DISPOSITION_INLINE,
         'bg_cell.png'
      );
      $response->headers->set('Cache-Control', 'no-cache, must-revalidate');

      $response->isNotModified($request);

      return $response;
   }

   #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
   #[Route('/send', name: 'plugin_phonebg_send', methods: ['GET', 'POST'])]
   public function send(Request $request): Response
   {
      // GLPI < 11.0.7 compatibility: older routing may evaluate a POST route
      // as GET. Never mutate state unless the actual request method is POST.
      if (!$request->isMethod('POST')) {
         return new Response('', Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'POST']);
      }

      if ($request->request->get('is_test', '0') === '1') {
         return $this->sendTest();
      }

      $phone_id = (int) $request->request->get('phoneid', 0);
      $phone = new \Phone();
      if ($phone_id <= 0 || !$phone->getFromDB($phone_id)) {
         return new RedirectResponse($this->rootUrl());
      }

      $current_user_id = (int) \Session::getLoginUserID();
      $is_admin = \Session::haveRight('config', UPDATE);
      $is_owner = (int) ($phone->fields['users_id'] ?? 0) === $current_user_id;
      if (!$is_admin && !$is_owner) {
         throw new AccessDeniedHttpException();
      }

      $back_url = $this->phoneBackUrl($phone_id);
      $errors = Background::checkRequirements();
      if (!empty($errors)) {
         foreach ($errors as $message) {
            \Session::addMessageAfterRedirect($message, false, ERROR);
         }
         return new RedirectResponse($back_url);
      }

      $assigned_user_id = (int) ($phone->fields['users_id'] ?? 0);
      if ($assigned_user_id <= 0) {
         \Session::addMessageAfterRedirect(
            __('No user assigned to this phone.', 'phonebg'),
            false,
            ERROR
         );
         return new RedirectResponse($back_url);
      }

      global $DB;
      $email_iter = $DB->request([
         'SELECT' => ['email'],
         'FROM'   => 'glpi_useremails',
         'WHERE'  => ['users_id' => $assigned_user_id, 'is_default' => 1],
         'LIMIT'  => 1,
      ]);
      $to_address = trim((string) (count($email_iter) ? $email_iter->current()['email'] : ''));

      if ($to_address === '') {
         \Session::addMessageAfterRedirect(
            __('No email address found for the assigned user.', 'phonebg'),
            false,
            ERROR
         );
         return new RedirectResponse($back_url);
      }

      $assigned_user = new \User();
      $assigned_user->getFromDB($assigned_user_id);
      $friendly_name = $assigned_user->getFriendlyName();
      $phone_line = Background::getPhoneLine($phone) ?? '';
      $subject = str_replace(
         ['{name}', '{line}'],
         [$friendly_name, $phone_line],
         (string) Config::get('email_subject')
      );
      $body_html = Background::buildEmailHtml(
         (string) Config::get('email_body'),
         (string) Config::get('email_footer'),
         $friendly_name,
         $phone_line
      );

      try {
         $file = Background::generatePNG($phone);
      } catch (Throwable $e) {
         \Toolbox::logInFile(
            'phonebg',
            'send generatePNG: ' . $e->getMessage(),
            false,
            false
         );
         \Session::addMessageAfterRedirect(
            __('Could not generate wallpaper. Check the GLPI log for details.', 'phonebg'),
            false,
            ERROR
         );
         return new RedirectResponse($back_url);
      }

      if (empty($file)) {
         \Session::addMessageAfterRedirect(
            Background::$lastError ?: __('Could not generate wallpaper.', 'phonebg'),
            false,
            ERROR
         );
         return new RedirectResponse($back_url);
      }

      $sent = Mail::sendWallpaper(
         $to_address,
         $friendly_name,
         $subject,
         $body_html,
         $file,
         false
      );

      if (is_file($file)) {
         @unlink($file);
      }

      if ($sent) {
         \Session::addMessageAfterRedirect(
            sprintf(__('Wallpaper sent to %s.', 'phonebg'), $to_address),
            false,
            INFO
         );
         \Toolbox::logInFile(
            'mail',
            'phonebg: sent to user ' . $assigned_user_id . ' (phone: ' . (int) $phone->getID() . ')',
            false,
            false
         );
      } else {
         \Session::addMessageAfterRedirect(
            __('Could not send the email. Check the outgoing mail configuration in GLPI.', 'phonebg'),
            false,
            ERROR
         );
         \Toolbox::logInFile(
            'mail',
            'phonebg FAILED to user ' . $assigned_user_id . ' (phone: ' . (int) $phone->getID() . ')',
            false,
            false
         );
      }

      return new RedirectResponse($back_url);
   }

   private function sendTest(): Response
   {
      \Session::checkRight('config', UPDATE);
      global $DB;

      $back_url = Paths::configUrl() . '?tab=email';
      $admin_id = (int) \Session::getLoginUserID();
      $email_iter = $DB->request([
         'SELECT' => ['email'],
         'FROM'   => 'glpi_useremails',
         'WHERE'  => ['users_id' => $admin_id, 'is_default' => 1],
         'LIMIT'  => 1,
      ]);
      $to_address = trim((string) (count($email_iter) ? $email_iter->current()['email'] : ''));

      if ($to_address === '') {
         \Session::addMessageAfterRedirect(
            __('No email address found in your GLPI profile.', 'phonebg'),
            false,
            ERROR
         );
         return new RedirectResponse($back_url);
      }

      $errors = Background::checkRequirements();
      if (!empty($errors)) {
         foreach ($errors as $message) {
            \Session::addMessageAfterRedirect($message, false, ERROR);
         }
         return new RedirectResponse($back_url);
      }

      $admin_user = new \User();
      $admin_user->getFromDB($admin_id);
      $friendly_name = $admin_user->getFriendlyName();
      $phone_line = '555-0000';
      $subject = '[TEST] ' . str_replace(
         ['{name}', '{line}'],
         [$friendly_name, $phone_line],
         (string) Config::get('email_subject')
      );
      $body_html = Background::buildEmailHtml(
         (string) Config::get('email_body'),
         (string) Config::get('email_footer'),
         $friendly_name,
         $phone_line,
         true
      );

      try {
         $file = Background::generateTestPNG($friendly_name, $phone_line);
      } catch (Throwable $e) {
         \Toolbox::logInFile(
            'phonebg',
            'send test generateTestPNG: ' . $e->getMessage(),
            false,
            false
         );
         \Session::addMessageAfterRedirect(
            __('Could not generate wallpaper. Check the GLPI log for details.', 'phonebg'),
            false,
            ERROR
         );
         return new RedirectResponse($back_url);
      }

      if (empty($file)) {
         \Session::addMessageAfterRedirect(
            Background::$lastError ?: __('Could not generate wallpaper.', 'phonebg'),
            false,
            ERROR
         );
         return new RedirectResponse($back_url);
      }

      $sent = Mail::sendWallpaper(
         $to_address,
         $friendly_name,
         $subject,
         $body_html,
         $file,
         true
      );

      if (is_file($file)) {
         @unlink($file);
      }

      if ($sent) {
         \Session::addMessageAfterRedirect(
            sprintf(__('Test email sent to %s.', 'phonebg'), $to_address),
            false,
            INFO
         );
         \Toolbox::logInFile('mail', 'phonebg [TEST] sent to user ' . $admin_id, false, false);
      } else {
         \Session::addMessageAfterRedirect(
            __('Could not send the test email. Check the outgoing mail configuration in GLPI.', 'phonebg'),
            false,
            ERROR
         );
         \Toolbox::logInFile('mail', 'phonebg [TEST] FAILED for user ' . $admin_id, false, false);
      }

      return new RedirectResponse($back_url);
   }


   private function handleConfigPost(Request $request): Response
   {
      $base_url = Paths::configUrl();
      $base_file = Paths::basePath();
      $fonts_dir = Paths::fontsDir();

      foreach (['templates', 'fonts'] as $subdir) {
         $dir = Paths::filesDir() . '/' . $subdir;
         if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
         }
      }
      Paths::ensureBundledFont();

      if ($request->request->has('delete_base')) {
         if (is_file($base_file) && @unlink($base_file)) {
            \Session::addMessageAfterRedirect(__('Template deleted successfully', 'phonebg'), false, INFO);
         } else {
            \Session::addMessageAfterRedirect(__('Could not delete template', 'phonebg'), false, ERROR);
         }
         return new RedirectResponse($base_url);
      }

      $base_upload = $request->files->get('base');
      if ($request->request->has('save') && $base_upload !== null) {
         if (!$base_upload->isValid()) {
            \Session::addMessageAfterRedirect(__('Could not save template', 'phonebg'), false, ERROR);
            return new RedirectResponse($base_url);
         }

         $size = $base_upload->getSize() ?? 0;
         $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($base_upload->getPathname());

         if ($size > 500 * 1024) {
            \Session::addMessageAfterRedirect(__('File too large (max 500 KB)', 'phonebg'), false, ERROR);
            return new RedirectResponse($base_url);
         }

         if ($mime !== 'image/png') {
            \Session::addMessageAfterRedirect(__('Invalid format, PNG only', 'phonebg'), false, ERROR);
            return new RedirectResponse($base_url);
         }

         $dimensions = @getimagesize($base_upload->getPathname());
         if ($dimensions === false || ($dimensions[2] ?? null) !== IMAGETYPE_PNG) {
            \Session::addMessageAfterRedirect(__('Invalid format, PNG only', 'phonebg'), false, ERROR);
            return new RedirectResponse($base_url);
         }

         $width = (int) ($dimensions[0] ?? 0);
         $height = (int) ($dimensions[1] ?? 0);
         if ($width < 1 || $height < 1 || ($width * $height) > 20_000_000) {
            \Session::addMessageAfterRedirect(__('Image dimensions are too large.', 'phonebg'), false, ERROR);
            return new RedirectResponse($base_url);
         }

         $target_dir = dirname($base_file);
         if (!is_dir($target_dir) && !@mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
            \Session::addMessageAfterRedirect(__('Could not save template', 'phonebg'), false, ERROR);
            return new RedirectResponse($base_url);
         }

         $image = @imagecreatefrompng($base_upload->getPathname());
         if ($image === false) {
            \Session::addMessageAfterRedirect(__('Could not save template', 'phonebg'), false, ERROR);
            return new RedirectResponse($base_url);
         }

         imagesavealpha($image, true);
         $sanitized_file = tempnam($target_dir, 'phonebg_png_');
         if ($sanitized_file === false || !@imagepng($image, $sanitized_file, 9)) {
            unset($image);
            if ($sanitized_file !== false && is_file($sanitized_file)) {
               @unlink($sanitized_file);
            }
            \Session::addMessageAfterRedirect(__('Could not save template', 'phonebg'), false, ERROR);
            return new RedirectResponse($base_url);
         }
         unset($image);

         @chmod($sanitized_file, 0644);
         if (!@rename($sanitized_file, $base_file)) {
            @unlink($sanitized_file);
            \Session::addMessageAfterRedirect(__('Could not save template', 'phonebg'), false, ERROR);
            return new RedirectResponse($base_url);
         }

         \Session::addMessageAfterRedirect(__('Background saved successfully', 'phonebg'), false, INFO);
         return new RedirectResponse($base_url);
      }

      $font_upload = $request->files->get('font_file');
      if ($request->request->has('upload_font') && $font_upload !== null) {
         $tab_url = $base_url . '?tab=fonts';

         if (!$font_upload->isValid()) {
            \Session::addMessageAfterRedirect(__('Could not save font', 'phonebg'), false, ERROR);
            return new RedirectResponse($tab_url);
         }

         $original_name = basename((string) $font_upload->getClientOriginalName());
         $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
         $size = $font_upload->getSize() ?? 0;

         if (!in_array($extension, ['ttf', 'otf'], true)) {
            \Session::addMessageAfterRedirect(__('Invalid font format, TTF or OTF only', 'phonebg'), false, ERROR);
            return new RedirectResponse($tab_url);
         }

         if ($size > 2 * 1024 * 1024) {
            \Session::addMessageAfterRedirect(__('Font file too large (max 2 MB)', 'phonebg'), false, ERROR);
            return new RedirectResponse($tab_url);
         }

         if (!$this->validateFontUpload($font_upload->getPathname())) {
            \Session::addMessageAfterRedirect(__('Invalid font format, TTF or OTF only', 'phonebg'), false, ERROR);
            return new RedirectResponse($tab_url);
         }

         $safe_name = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $original_name);
         try {
            $font_upload->move($fonts_dir, $safe_name);
         } catch (Throwable) {
            \Session::addMessageAfterRedirect(__('Could not save font', 'phonebg'), false, ERROR);
            return new RedirectResponse($tab_url);
         }

         $dest_path = $fonts_dir . '/' . $safe_name;
         if (is_file($dest_path)) {
            @chmod($dest_path, 0644);
            \Session::addMessageAfterRedirect(__('Font saved successfully', 'phonebg'), false, INFO);
         } else {
            \Session::addMessageAfterRedirect(__('Could not save font', 'phonebg'), false, ERROR);
         }

         return new RedirectResponse($tab_url);
      }

      if ($request->request->has('delete_font')) {
         $font_name = basename((string) $request->request->get('delete_font'));
         $font_path = $fonts_dir . '/' . $font_name;
         $tab_url = $base_url . '?tab=fonts';

         if ($font_name === 'DejaVuSans.ttf') {
            \Session::addMessageAfterRedirect(__('The default font cannot be deleted', 'phonebg'), false, WARNING);
         } elseif (is_file($font_path) && preg_match('/\.(ttf|otf)$/i', $font_path)) {
            if (@unlink($font_path)) {
               Paths::deleteFontMeta($font_name);
               if (Config::get('font_file') === $font_name) {
                  Config::set('font_file', 'DejaVuSans.ttf');
               }
               \Session::addMessageAfterRedirect(__('Font deleted successfully', 'phonebg'), false, INFO);
            } else {
               \Session::addMessageAfterRedirect(__('Could not delete font', 'phonebg'), false, ERROR);
            }
         }

         return new RedirectResponse($tab_url);
      }

      if ($request->request->has('save_positions')) {
         $data = [];
         foreach (['name_x', 'name_y', 'mobile_x', 'mobile_y'] as $field) {
            $data[$field] = max(0, (int) $request->request->get($field, 0));
         }
         foreach (['name_size', 'mobile_size'] as $field) {
            $data[$field] = max(8, (int) $request->request->get($field, 8));
         }

         $color = (string) $request->request->get('font_color', '#000000');
         if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#000000';
         }
         $data['font_color'] = $color;

         $selected_font = basename((string) $request->request->get('font_file', 'DejaVuSans.ttf'));
         $available_fonts = Paths::listFonts();
         $data['font_file'] = array_key_exists($selected_font, $available_fonts)
            ? $selected_font
            : 'DejaVuSans.ttf';

         $data['label1_enabled'] = $request->request->has('label1_enabled') ? '1' : '0';
         $data['label2_enabled'] = $request->request->has('label2_enabled') ? '1' : '0';

         foreach (['label1_text', 'label2_text'] as $field) {
            $data[$field] = substr(strip_tags((string) $request->request->get($field, '')), 0, 150);
         }
         foreach (['label1_x', 'label1_y', 'label2_x', 'label2_y'] as $field) {
            $data[$field] = max(0, (int) $request->request->get($field, 0));
         }
         foreach (['label1_size', 'label2_size'] as $field) {
            $data[$field] = max(8, (int) $request->request->get($field, 8));
         }

         Config::saveAll($data);
         \Session::addMessageAfterRedirect(__('Positions saved successfully', 'phonebg'), false, INFO);
         return new RedirectResponse($base_url . '?tab=positions');
      }

      if ($request->request->has('save_email')) {
         $subject = substr(strip_tags((string) $request->request->get('email_subject', '')), 0, 255);
         $body = substr(strip_tags((string) $request->request->get('email_body', '')), 0, 2000);
         $footer = substr(strip_tags((string) $request->request->get('email_footer', '')), 0, 500);

         Config::saveAll([
            'email_subject' => $subject,
            'email_body' => $body,
            'email_footer' => $footer,
         ]);
         \Session::addMessageAfterRedirect(__('Email settings saved', 'phonebg'), false, INFO);
         return new RedirectResponse($base_url . '?tab=email');
      }

      if ($request->request->has('reset_positions')) {
         Config::resetToDefaults();
         \Session::addMessageAfterRedirect(__('Positions reset to default values', 'phonebg'), false, INFO);
         return new RedirectResponse($base_url);
      }

      return new RedirectResponse($base_url);
   }

   private function renderConfig(Request $request): Response
   {
      $base_file = Paths::basePath();
      $has_base = is_readable($base_file);
      Paths::ensureBundledFont();
      $config = Config::getAll();
      $available_fonts = Paths::listFonts();

      $valid_tabs = ['template', 'positions', 'fonts', 'email'];
      $requested_tab = (string) $request->query->get('tab', 'template');
      $active_tab = in_array($requested_tab, $valid_tabs, true) ? $requested_tab : 'template';

      $core_config = \Config::getConfigurationValues('core');
      $mail_ok = (int) ($core_config['use_notifications'] ?? 0) === 1
         && (int) ($core_config['notifications_mailing'] ?? 0) === 1;
      $has_email_config = trim((string) ($config['email_subject'] ?? '')) !== ''
         && trim((string) ($config['email_body'] ?? '')) !== '';

      if (!$mail_ok) {
         $button_tooltip = __('GLPI mail server not configured', 'phonebg');
      } elseif (!$has_email_config) {
         $button_tooltip = __('Configure the email subject and body first', 'phonebg');
      } else {
         $button_tooltip = __('Send a test email to your registered GLPI address', 'phonebg');
      }

      $base_url = $has_base ? Paths::baseUrl() : '';
      $base_url_ts = $has_base ? $base_url . '?t=' . time() : '';
      $version_status = VersionChecker::getStatus();

      ob_start();
      \Html::header(__('Phone Wallpapers', 'phonebg'), Paths::configUrl(), 'config', 'plugins');
      \Html::displayMessageAfterRedirect();
      echo Renderer::render('config_form.html.twig', [
         'self_url' => Paths::configUrl(),
         'csrf_token' => \Session::getNewCSRFToken(),
         'has_base' => $has_base,
         'base_url' => $base_url,
         'base_url_ts' => $base_url_ts,
         'cfg' => $config,
         'avail_fonts' => $available_fonts,
         'active_tab' => $active_tab,
         'name_x' => (int) $config['name_x'],
         'name_y' => (int) $config['name_y'],
         'mobile_x' => (int) $config['mobile_x'],
         'mobile_y' => (int) $config['mobile_y'],
         'label1_x' => (int) ($config['label1_x'] ?? 0),
         'label1_y' => (int) ($config['label1_y'] ?? 650),
         'label2_x' => (int) ($config['label2_x'] ?? 0),
         'label2_y' => (int) ($config['label2_y'] ?? 720),
         'label1_enabled' => ($config['label1_enabled'] ?? '0') === '1',
         'label2_enabled' => ($config['label2_enabled'] ?? '0') === '1',
         'label1_text' => (string) ($config['label1_text'] ?? ''),
         'label2_text' => (string) ($config['label2_text'] ?? ''),
         'email_subject' => (string) $config['email_subject'],
         'email_body' => (string) $config['email_body'],
         'email_footer' => (string) ($config['email_footer'] ?? ''),
         'test_url' => Paths::sendUrl(),
         'test_csrf_token' => \Session::getNewCSRFToken(),
         'mail_ok' => $mail_ok,
         'has_email_config' => $has_email_config,
         'btn_tooltip' => $button_tooltip,
         'version_status' => $version_status,
         'js_url' => Paths::webDir() . '/asset/phonebg.js',
      ]);
      \Html::footer();

      return new Response(ob_get_clean());
   }

   private function canAccessPhone(\Phone $phone): bool
   {
      $current_user_id = (int) \Session::getLoginUserID();
      if (\Session::haveRight('config', UPDATE)) {
         return true;
      }

      return (int) ($phone->fields['users_id'] ?? 0) === $current_user_id
         || $phone->canViewItem();
   }

   private function phoneBackUrl(int $phone_id): string
   {
      return Paths::phoneUrl($phone_id) . '&forcetab=' . rawurlencode(PhoneTab::class . '$1');
   }

   private function rootUrl(): string
   {
      return rtrim($GLOBALS['CFG_GLPI']['root_doc'] ?? '', '/');
   }

   private function validateFontUpload(string $path): bool
   {
      $file_size = @filesize($path);
      if (!is_int($file_size) || $file_size < 12) {
         return false;
      }

      $handle = @fopen($path, 'rb');
      if ($handle === false) {
         return false;
      }

      try {
         $header = fread($handle, 12);
         if ($header === false || strlen($header) !== 12) {
            return false;
         }

         if (!in_array(substr($header, 0, 4), [
            "\x00\x01\x00\x00",
            "\x74\x72\x75\x65",
            "\x4F\x54\x54\x4F",
         ], true)) {
            return false;
         }

         $num_tables = unpack('n', substr($header, 4, 2))[1] ?? 0;
         if ($num_tables < 1 || $num_tables > 256 || (12 + $num_tables * 16) > $file_size) {
            return false;
         }

         for ($i = 0; $i < $num_tables; $i++) {
            $record = fread($handle, 16);
            if ($record === false || strlen($record) !== 16) {
               return false;
            }

            $table = unpack('Nchecksum/Noffset/Nlength', substr($record, 4, 12));
            $offset = $table['offset'] ?? -1;
            $length = $table['length'] ?? -1;
            if ($offset < 0 || $length < 0 || $offset > $file_size
                || $length > ($file_size - $offset)) {
               return false;
            }
         }

         return true;
      } finally {
         fclose($handle);
      }
   }
}
