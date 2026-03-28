<?php

class PluginPhonebgPaths {

   /**
    * Physical plugin directory (plugins/ or marketplace/)
    */
   public static function pluginDir(): string
   {
      foreach (GLPI_PLUGINS_DIRECTORIES as $dir) {
         $path = $dir . '/phonebg';
         if (is_dir($path)) {
            return $path;
         }
      }
      throw new RuntimeException('Plugin directory not found: phonebg');
   }

   /**
    * Root files/ storage directory
    */
   public static function filesDir(): string
   {
      return GLPI_DOC_DIR . '/_plugins/phonebg';
   }

   /**
    * Template base image path
    */
   public static function basePath(): string
   {
      return self::filesDir() . '/templates/base.png';
   }

   /**
    * User fonts directory (writable, inside GLPI files/)
    */
   public static function fontsDir(): string
   {
      return self::filesDir() . '/fonts';
   }

   /**
    * Resolve a font filename to its full path.
    * Looks first in the user fonts dir, then falls back to the bundled font.
    */
   public static function getFontPath(string $filename = 'DejaVuSans.ttf'): string
   {
      $userPath = self::fontsDir() . '/' . basename($filename);
      if (is_readable($userPath)) {
         return $userPath;
      }
      // Fallback: bundled font inside the plugin
      return self::pluginDir() . '/fonts/DejaVuSans.ttf';
   }

   /**
    * List all font files available in the user fonts directory.
    * Returns array of filenames (basename only), sorted.
    */
   public static function listFonts(): array
   {
      $dir = self::fontsDir();
      if (!is_dir($dir)) {
         return [];
      }
      $fonts = [];
      foreach (scandir($dir) as $file) {
         if (preg_match('/\.(ttf|otf)$/i', $file)) {
            $fonts[] = $file;
         }
      }
      sort($fonts);
      return $fonts;
   }

   /**
    * Public URL (via resource.send.php)
    */
   public static function baseUrl(): string
   {
      return Plugin::getWebDir('phonebg') . '/front/resource.send.php?resource=base';
   }
}
