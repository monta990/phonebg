<?php

declare(strict_types=1);

namespace GlpiPlugin\Phonebg\Service;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;
use Toolbox;

final class Mail
{
   public static function sendWallpaper(
      string $to_address,
      string $recipient_name,
      string $subject,
      string $body_html,
      string $file,
      bool $is_test = false
   ): bool {
      global $CFG_GLPI;

      try {
         $from_name = trim((string) ($CFG_GLPI['from_email_name'] ?? $CFG_GLPI['admin_email_name'] ?? ''));
         $from_email = trim((string) ($CFG_GLPI['from_email'] ?? $CFG_GLPI['admin_email'] ?? ''));
         if ($from_email === '') {
            return false;
         }

         $email = (new Email())
            ->from(new Address($from_email, $from_name))
            ->to(new Address($to_address, $recipient_name))
            ->subject($subject)
            ->text(trim(html_entity_decode(strip_tags($body_html), ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            ->html($body_html)
            ->attachFromPath($file, 'wallpaper.png', 'image/png');

         $transport = Transport::fromDsn(\GLPIMailer::buildDsn(true));
         (new Mailer($transport))->send($email);
         return true;
      } catch (Throwable $e) {
         Toolbox::logInFile(
            'mail',
            'phonebg ' . ($is_test ? '[TEST] ' : '') . 'mail error: ' . $e->getMessage(),
            false,
            false
         );
         return false;
      }
   }
}
