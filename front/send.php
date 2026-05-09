<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   http_response_code(405);
   exit;
}

Session::checkLoginUser();

global $CFG_GLPI, $DB;

/* ============================
 * TEST MODE — admin sends to own address
 * ============================ */
if (($_POST['is_test'] ?? '0') === '1') {
   Session::checkRight('config', UPDATE);

   $backUrl = PluginPhonebgPaths::webDir() . '/front/config.form.php?tab=email';

   $adminId  = (int)Session::getLoginUserID();
   $emailIter = $DB->request([
      'SELECT' => ['email'],
      'FROM'   => 'glpi_useremails',
      'WHERE'  => ['users_id' => $adminId, 'is_default' => 1],
      'LIMIT'  => 1,
   ]);
   $toAddress = trim((string)(count($emailIter) ? $emailIter->current()['email'] : ''));
   if ($toAddress === '') {
      Session::addMessageAfterRedirect(
         __('No email address found in your GLPI profile.', 'phonebg'),
         false,
         ERROR
      );
      Html::redirect($backUrl);
   }

   $errors = PluginPhonebgBackground::checkRequirements();
   if (!empty($errors)) {
      foreach ($errors as $msg) {
         Session::addMessageAfterRedirect($msg, false, ERROR);
      }
      Html::redirect($backUrl);
   }

   $adminUser = new User();
   $adminUser->getFromDB($adminId);
   $friendlyName = $adminUser->getFriendlyName();
   $phoneLine    = '555-0000';

   $rawSubject = (string)PluginPhonebgConfig::get('email_subject');
   $subject    = '[TEST] ' . str_replace(['{name}', '{line}'], [$friendlyName, $phoneLine], $rawSubject);
   $bodyHtml   = PluginPhonebgBackground::buildEmailHtml(
      (string)PluginPhonebgConfig::get('email_body'),
      (string)PluginPhonebgConfig::get('email_footer'),
      $friendlyName,
      $phoneLine,
      true
   );

   try {
      $file = PluginPhonebgBackground::generateTestPNG($friendlyName, $phoneLine);
   } catch (\Throwable $e) {
      Toolbox::logError('phonebg send.php test generateTestPNG: ' . $e->getMessage());
      Session::addMessageAfterRedirect(
         __('Could not generate wallpaper. Check the GLPI log for details.', 'phonebg'),
         false,
         ERROR
      );
      Html::redirect($backUrl);
   }

   if (empty($file)) {
      $errMsg = PluginPhonebgBackground::$lastError ?: __('Could not generate wallpaper.', 'phonebg');
      Session::addMessageAfterRedirect($errMsg, false, ERROR);
      Html::redirect($backUrl);
   }

   $sent = false;
   try {
      $mail = new GLPIMailer();
      $fromName  = trim($CFG_GLPI['from_email_name']  ?? $CFG_GLPI['admin_email_name'] ?? '');
      $fromEmail = trim($CFG_GLPI['from_email']        ?? $CFG_GLPI['admin_email']      ?? '');
      if ($fromEmail !== '' && $fromName !== '' && method_exists($mail, 'getEmail')) {
         $mail->getEmail()->from(new \Symfony\Component\Mime\Address($fromEmail, $fromName));
      }
      $mail->AddAddress($toAddress, $friendlyName);
      $mail->Subject = $subject;
      $mail->isHTML(true);
      $mail->Body    = $bodyHtml;
      $mail->AddAttachment($file, 'wallpaper.png', 'base64', 'image/png');
      $sent = $mail->Send();
   } catch (\Throwable $e) {
      Toolbox::logInFile('mail', 'phonebg [TEST] ERROR to ' . $toAddress . ': ' . $e->getMessage());
      $sent = false;
   }

   if (is_file($file)) {
      unlink($file);
   }

   if ($sent) {
      Session::addMessageAfterRedirect(
         sprintf(__('Test email sent to %s.', 'phonebg'), $toAddress),
         false,
         INFO
      );
      Toolbox::logInFile('mail', 'phonebg [TEST] sent to ' . $toAddress);
   } else {
      Session::addMessageAfterRedirect(
         __('Could not send the test email. Check the outgoing mail configuration in GLPI.', 'phonebg'),
         false,
         ERROR
      );
      Toolbox::logInFile('mail', 'phonebg [TEST] FAILED to ' . $toAddress);
   }

   Html::redirect($backUrl);
}

$phoneId = (int)($_POST['phoneid'] ?? 0);
if ($phoneId <= 0) {
   Html::redirect($CFG_GLPI['root_doc']);
}

$phone = new Phone();
if (!$phone->getFromDB($phoneId)) {
   Html::redirect($CFG_GLPI['root_doc']);
}

$currentUserId = (int)Session::getLoginUserID();
$isAdmin       = Session::haveRight('config', UPDATE);
$isOwner       = ((int)$phone->fields['users_id'] === $currentUserId);

if (!$isAdmin && !$isOwner) {
   Html::redirect($CFG_GLPI['root_doc']);
}

$backUrl = Phone::getFormURLWithID($phoneId) . '&forcetab=PluginPhonebgPhone$1';

// Check GD / template / font
$errors = PluginPhonebgBackground::checkRequirements();
if (!empty($errors)) {
   foreach ($errors as $msg) {
      Session::addMessageAfterRedirect($msg, false, ERROR);
   }
   Html::redirect($backUrl);
}

// Resolve assigned user
$assignedUserId = (int)($phone->fields['users_id'] ?? 0);
if ($assignedUserId <= 0) {
   Session::addMessageAfterRedirect(__('No user assigned to this phone.', 'phonebg'), false, ERROR);
   Html::redirect($backUrl);
}

// Get default email
$emailIter = $DB->request([
   'SELECT' => ['email'],
   'FROM'   => 'glpi_useremails',
   'WHERE'  => ['users_id' => $assignedUserId, 'is_default' => 1],
   'LIMIT'  => 1,
]);
$toAddress = trim((string)(count($emailIter) ? $emailIter->current()['email'] : ''));
if ($toAddress === '') {
   Session::addMessageAfterRedirect(
      __('No email address found for the assigned user.', 'phonebg'),
      false,
      ERROR
   );
   Html::redirect($backUrl);
}

// Token substitution
$assignedUser = new User();
$assignedUser->getFromDB($assignedUserId);
$friendlyName = $assignedUser->getFriendlyName();
$phoneLine    = PluginPhonebgBackground::getPhoneLine($phone) ?? '';

$subject  = str_replace(
   ['{name}', '{line}'],
   [$friendlyName, $phoneLine],
   (string)PluginPhonebgConfig::get('email_subject')
);
$bodyHtml = PluginPhonebgBackground::buildEmailHtml(
   (string)PluginPhonebgConfig::get('email_body'),
   (string)PluginPhonebgConfig::get('email_footer'),
   $friendlyName,
   $phoneLine
);

// Generate PNG
try {
   $file = PluginPhonebgBackground::generatePNG($phone);
} catch (\Throwable $e) {
   Toolbox::logError('phonebg send.php generatePNG: ' . $e->getMessage());
   Session::addMessageAfterRedirect(
      __('Could not generate wallpaper. Check the GLPI log for details.', 'phonebg'),
      false,
      ERROR
   );
   Html::redirect($backUrl);
}

if (empty($file)) {
   $errMsg = PluginPhonebgBackground::$lastError ?: __('Could not generate wallpaper.', 'phonebg');
   Session::addMessageAfterRedirect($errMsg, false, ERROR);
   Html::redirect($backUrl);
}

// Send
$sent = false;
try {
   $mail = new GLPIMailer();

   $fromName  = trim($CFG_GLPI['from_email_name']  ?? $CFG_GLPI['admin_email_name'] ?? '');
   $fromEmail = trim($CFG_GLPI['from_email']        ?? $CFG_GLPI['admin_email']      ?? '');
   if ($fromEmail !== '' && $fromName !== '' && method_exists($mail, 'getEmail')) {
      $mail->getEmail()->from(new \Symfony\Component\Mime\Address($fromEmail, $fromName));
   }

   $mail->AddAddress($toAddress, $friendlyName);
   $mail->Subject = $subject;
   $mail->isHTML(true);
   $mail->Body    = $bodyHtml;
   $mail->AddAttachment($file, 'wallpaper.png', 'base64', 'image/png');
   $sent = $mail->Send();
} catch (\Throwable $e) {
   Toolbox::logInFile('mail', 'phonebg ERROR to ' . $toAddress . ': ' . $e->getMessage());
   $sent = false;
}

if (is_file($file)) {
   unlink($file);
}

if ($sent) {
   Session::addMessageAfterRedirect(
      sprintf(__('Wallpaper sent to %s.', 'phonebg'), $toAddress),
      false,
      INFO
   );
   Toolbox::logInFile('mail', 'phonebg: sent to ' . $toAddress . ' (phone: ' . $phone->getName() . ')');
} else {
   Session::addMessageAfterRedirect(
      __('Could not send the email. Check the outgoing mail configuration in GLPI.', 'phonebg'),
      false,
      ERROR
   );
   Toolbox::logInFile('mail', 'phonebg FAILED to ' . $toAddress . ' (phone: ' . $phone->getName() . ')');
}

Html::redirect($backUrl);
