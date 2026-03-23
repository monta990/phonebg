<?php
declare(strict_types=1);

class PluginPhonebgBackground {

   /**
    * Validate plugin requirements before generating an image.
    */
   public static function checkRequirements(): array
   {
      $errors = [];

      if (!extension_loaded('gd')) {
         $errors[] = __('PHP GD extension is required', 'phonebg');
      }

      if (!is_readable(PluginPhonebgPaths::basePath())) {
         $errors[] = __('Template not found', 'phonebg');
      }

      try {
         $fontPath = PluginPhonebgPaths::getFontDejaVuSans();
         if (!is_readable($fontPath)) {
            $errors[] = sprintf(
               __('TTF font not found: %s', 'phonebg'),
               $fontPath
            );
         }
      } catch (RuntimeException $e) {
         $errors[] = __('Plugin directory not found, unable to verify TTF font', 'phonebg');
      }

      return $errors;
   }

   /**
    * Draw text on image.
    * If $x <= 0 the text is horizontally centered.
    */
   private static function drawText(
      GdImage $img,
      int     $size,
      int     $x,
      int     $y,
      int     $color,
      string  $font,
      string  $text
   ): void {
      if ($x <= 0) {
         $bbox  = imagettfbbox($size, 0, $font, $text);
         $tw    = $bbox[2] - $bbox[0];
         $x     = (int)((imagesx($img) - $tw) / 2);
      }
      imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
   }

   /**
    * Generate the wallpaper PNG for the given phone and return its temp path.
    */
   public static function generatePNG(Phone $phone): string
   {
      $img = @imagecreatefrompng(PluginPhonebgPaths::basePath());

      if (!$img instanceof GdImage) {
         Session::addMessageAfterRedirect(
            __('Could not load base image', 'phonebg'),
            false,
            ERROR
         );
         return '';
      }

      imagealphablending($img, false);
      imagesavealpha($img, true);
      imagealphablending($img, true);

      try {
         $font = PluginPhonebgPaths::getFontDejaVuSans();
      } catch (RuntimeException $e) {
         imagedestroy($img);
         Session::addMessageAfterRedirect(
            __('Plugin directory not found', 'phonebg'),
            false,
            ERROR
         );
         return '';
      }

      /* Load layout config */
      $cfg = PluginPhonebgConfig::getAll();

      $hex   = ltrim((string)($cfg['font_color'] ?? '#000000'), '#');
      $color = imagecolorallocate(
         $img,
         (int)hexdec(substr($hex, 0, 2)),
         (int)hexdec(substr($hex, 2, 2)),
         (int)hexdec(substr($hex, 4, 2))
      );

      $name        = $phone->getName();
      $mobileRaw   = self::getPhoneLine($phone);

      if ($mobileRaw === null) {
         imagedestroy($img);
         Session::addMessageAfterRedirect(
            __('The phone has no assigned line', 'phonebg'),
            false,
            WARNING
         );
         return '';
      }

      $mobile = trim($mobileRaw);
      if ($mobile === '') {
         imagedestroy($img);
         Session::addMessageAfterRedirect(
            __('The phone line number is empty', 'phonebg'),
            false,
            WARNING
         );
         return '';
      }

      $imgWidth = imagesx($img);

      /* Auto-shrink name font if text is wider than the image */
      $nameSize = max(10, (int)$cfg['name_size']);
      while ($nameSize > 10) {
         $bbox = imagettfbbox($nameSize, 0, $font, $name);
         if (($bbox[2] - $bbox[0]) < $imgWidth - 40) {
            break;
         }
         $nameSize--;
      }

      /* Auto-shrink mobile font if text is wider than the image */
      $mobileSize = max(10, (int)$cfg['mobile_size']);
      while ($mobileSize > 10) {
         $bbox = imagettfbbox($mobileSize, 0, $font, $mobile);
         if (($bbox[2] - $bbox[0]) < $imgWidth - 40) {
            break;
         }
         $mobileSize--;
      }

      self::drawText($img, $nameSize,   (int)$cfg['name_x'],   (int)$cfg['name_y'],   $color, $font, $name);
      self::drawText($img, $mobileSize, (int)$cfg['mobile_x'], (int)$cfg['mobile_y'], $color, $font, $mobile);

      $out = GLPI_TMP_DIR . '/background_' . $phone->getID() . '_' . uniqid() . '.png';

      imagepng($img, $out, 6);
      imagedestroy($img);

      return $out;
   }

   private static function getPhoneLine(Phone $phone): ?string
   {
      global $DB;

      $iterator = $DB->request([
         'SELECT'     => ['glpi_lines.caller_num'],
         'FROM'       => 'glpi_items_lines',
         'INNER JOIN' => [
            'glpi_lines' => [
               'ON' => [
                  'glpi_items_lines' => 'lines_id',
                  'glpi_lines'       => 'id',
               ]
            ]
         ],
         'WHERE' => [
            'glpi_items_lines.itemtype' => 'Phone',
            'glpi_items_lines.items_id' => (int)$phone->getID(),
            'glpi_lines.is_deleted'     => 0,
         ],
         'ORDER' => 'glpi_lines.id',
         'LIMIT' => 1,
      ]);

      if (!count($iterator)) {
         return null;
      }

      return (string)$iterator->current()['caller_num'];
   }
}
