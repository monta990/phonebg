<?php

class PluginPhonebgPaths {

   /**
    * Public web URL for the plugin root (GLPI 11/12 compatible).
    */
   public static function webDir(): string
   {
      if (defined('PLUGINS_WEB_DIR')) {
         return PLUGINS_WEB_DIR . '/phonebg';
      }

      global $CFG_GLPI;
      return rtrim($CFG_GLPI['root_doc'] ?? '', '/') . '/plugins/phonebg';
   }

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
      return self::pluginDir() . '/fonts/DejaVuSans.ttf';
   }

   /**
    * List all font files available in the user fonts directory.
    * Returns associative array: filename => display name (from font metadata).
    * Reads and caches metadata in a .meta.json sidecar file per font.
    */
   public static function listFonts(): array
   {
      $dir = self::fontsDir();
      if (!is_dir($dir)) {
         return [];
      }

      $fonts = [];
      foreach (scandir($dir) as $file) {
         if (!preg_match('/\.(ttf|otf)$/i', $file)) {
            continue;
         }
         $fonts[$file] = self::getFontDisplayName($file);
      }

      ksort($fonts);
      return $fonts;
   }

   /**
    * Return the display name for a font file.
    * Uses a .meta.json sidecar for caching; parses binary if cache is missing.
    */
   public static function getFontDisplayName(string $filename): string
   {
      $filename  = basename($filename);
      $fontPath  = self::fontsDir() . '/' . $filename;
      $metaPath  = self::fontsDir() . '/' . $filename . '.meta.json';

      /* Read from cache if available and not older than the font file */
      if (is_readable($metaPath) && filemtime($metaPath) >= filemtime($fontPath)) {
         $meta = json_decode(file_get_contents($metaPath), true);
         if (!empty($meta['name'])) {
            return $meta['name'];
         }
      }

      /* Parse font binary */
      $name = self::parseFontName($fontPath);

      /* Fallback: filename without extension, underscores/hyphens → spaces */
      if ($name === null || $name === '') {
         $name = ucwords(str_replace(['_', '-'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
      }

      /* Write cache */
      if (is_writable(self::fontsDir())) {
         file_put_contents($metaPath, json_encode(['name' => $name], JSON_UNESCAPED_UNICODE));
      }

      return $name;
   }

   /**
    * Parse the "Full font name" (nameID 16) or family name (nameID 1)
    * from a TTF/OTF binary without any external dependencies.
    * Returns null if the file is not a valid font or has no readable name.
    */
   private static function parseFontName(string $path): ?string
   {
      $fileSize = @filesize($path);
      if (!is_int($fileSize) || $fileSize < 12) {
         return null;
      }

      $f = fopen($path, 'rb');
      if ($f === false) {
         return null;
      }

      try {
         $header = fread($f, 12);
         if ($header === false || strlen($header) !== 12) {
            return null;
         }

         $magic = substr($header, 0, 4);
         if (!in_array($magic, [
            "\x00\x01\x00\x00",
            "\x74\x72\x75\x65",
            "\x4F\x54\x54\x4F",
         ], true)) {
            return null;
         }

         $numTables = unpack('n', substr($header, 4, 2))[1] ?? 0;
         if ($numTables < 1 || $numTables > 256 || (12 + ($numTables * 16)) > $fileSize) {
            return null;
         }

         $nameOffset = null;
         $nameLength = null;
         for ($i = 0; $i < $numTables; $i++) {
            $record = fread($f, 16);
            if ($record === false || strlen($record) !== 16) {
               return null;
            }
            $tag = substr($record, 0, 4);
            $table = unpack('Nchecksum/Noffset/Nlength', substr($record, 4, 12));
            $offset = $table['offset'] ?? -1;
            $length = $table['length'] ?? -1;
            if ($offset < 0 || $length < 0 || $offset > $fileSize
                || $length > ($fileSize - $offset)) {
               return null;
            }
            if ($tag === 'name') {
               $nameOffset = $offset;
               $nameLength = $length;
            }
         }

         if ($nameOffset === null || $nameLength === null || $nameLength < 6) {
            return null;
         }
         if (fseek($f, $nameOffset, SEEK_SET) !== 0) {
            return null;
         }

         $nameHeader = fread($f, 6);
         if ($nameHeader === false || strlen($nameHeader) !== 6) {
            return null;
         }
         $nameInfo = unpack('nformat/ncount/nstringOffset', $nameHeader);
         $count = $nameInfo['count'] ?? 0;
         $stringOffset = $nameInfo['stringOffset'] ?? 0;

         if ($count < 1 || $count > 4096
             || 6 + ($count * 12) > $nameLength
             || $stringOffset < 6 + ($count * 12)
             || $stringOffset > $nameLength) {
            return null;
         }

         $records = [];
         for ($i = 0; $i < $count; $i++) {
            $record = fread($f, 12);
            if ($record === false || strlen($record) !== 12) {
               return null;
            }
            $r = unpack('nplatform/nencoding/nlanguage/nnameId/nlength/nstrOffset', $record);
            $length = $r['length'] ?? -1;
            $strOffset = $r['strOffset'] ?? -1;
            if ($length < 0 || $strOffset < 0
                || $strOffset > ($nameLength - $stringOffset)
                || $length > ($nameLength - $stringOffset - $strOffset)) {
               continue;
            }
            if (($r['nameId'] ?? 0) === 16 || ($r['nameId'] ?? 0) === 1) {
               $records[] = $r;
            }
         }

         foreach ([16, 1] as $wantedNameId) {
            foreach ($records as $r) {
               if (($r['nameId'] ?? 0) !== $wantedNameId || ($r['length'] ?? 0) < 1) {
                  continue;
               }
               $relative = $stringOffset + $r['strOffset'];
               if ($relative < 0 || $relative > $nameLength
                   || $r['length'] > ($nameLength - $relative)) {
                  continue;
               }
               $absolute = $nameOffset + $relative;
               if ($absolute < 0 || $absolute > $fileSize
                   || $r['length'] > ($fileSize - $absolute)
                   || fseek($f, $absolute, SEEK_SET) !== 0) {
                  continue;
               }
               $raw = fread($f, $r['length']);
               if ($raw === false || strlen($raw) !== $r['length']) {
                  continue;
               }

               if (($r['platform'] ?? 0) === 0 || ($r['platform'] ?? 0) === 3) {
                  if (function_exists('mb_convert_encoding')) {
                     $name = mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
                  } elseif (function_exists('iconv')) {
                     $name = iconv('UTF-16BE', 'UTF-8//IGNORE', $raw);
                  } else {
                     $name = '';
                  }
               } else {
                  $name = $raw;
               }

               if (is_string($name)) {
                  $name = trim(str_replace("\0", '', $name));
                  if ($name !== '') {
                     return $name;
                  }
               }
            }
         }
         return null;
      } finally {
         fclose($f);
      }
   }

   /**
    * Delete the metadata cache for a font file (call when font is deleted).
    */
   public static function deleteFontMeta(string $filename): void
   {
      $metaPath = self::fontsDir() . '/' . basename($filename) . '.meta.json';
      if (is_file($metaPath)) {
         @unlink($metaPath);
      }
   }

   /**
    * Public URL (via resource.send.php)
    */
   public static function baseUrl(): string
   {
      return self::webDir() . '/front/resource.send.php?resource=base';
   }
}
