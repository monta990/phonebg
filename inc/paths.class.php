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
    * Parse the "Full font name" (nameID 4) or family name (nameID 1)
    * from a TTF/OTF binary without any external dependencies.
    * Returns null if the file is not a valid font or has no readable name.
    */
   private static function parseFontName(string $path): ?string
   {
      if (!is_readable($path)) {
         return null;
      }

      $f = fopen($path, 'rb');
      if (!$f) {
         return null;
      }

      try {
         /* Offset table: sfVersion(4), numTables(2), searchRange(2), entrySelector(2), rangeShift(2) */
         $header = fread($f, 12);
         if (strlen($header) < 12) {
            return null;
         }

         $sfVersion = substr($header, 0, 4);
         $valid     = ["\x00\x01\x00\x00", 'true', 'OTTO', 'ttcf'];
         if (!in_array($sfVersion, $valid, true)) {
            return null;
         }

         $numTables = unpack('n', substr($header, 4, 2))[1];

         /* Table directory: 16 bytes per entry (tag, checksum, offset, length) */
         $nameOffset = null;
         for ($i = 0; $i < $numTables; $i++) {
            $entry = fread($f, 16);
            if (strlen($entry) < 16) {
               break;
            }
            $tag    = substr($entry, 0, 4);
            $offset = unpack('N', substr($entry, 8, 4))[1];
            if ($tag === 'name') {
               $nameOffset = $offset;
               break;
            }
         }

         if ($nameOffset === null) {
            return null;
         }

         /* name table header: format(2), count(2), stringOffset(2) */
         fseek($f, $nameOffset);
         $nameHeader = fread($f, 6);
         if (strlen($nameHeader) < 6) {
            return null;
         }
         $count        = unpack('n', substr($nameHeader, 2, 2))[1];
         $stringOffset = unpack('n', substr($nameHeader, 4, 2))[1];
         $storage      = $nameOffset + $stringOffset;

         /* Read all name records: platformID(2), encodingID(2), languageID(2), nameID(2), length(2), offset(2) */
         $records = [];
         for ($i = 0; $i < $count; $i++) {
            $rec = fread($f, 12);
            if (strlen($rec) < 12) {
               break;
            }
            $u = unpack('nplatform/nencoding/nlanguage/nnameID/nlength/nstrOffset', $rec);
            if (in_array($u['nameID'], [1, 4], true)) {
               $records[] = $u;
            }
         }

         /* Extract strings, preferring Windows platform (3) with English (0x0409) */
         $fullName = null;
         $family   = null;

         /* Two passes: first English Windows, then any platform */
         foreach ([true, false] as $preferEnglish) {
            foreach ($records as $r) {
               if ($preferEnglish && !($r['platform'] === 3 && $r['language'] === 0x0409)) {
                  continue;
               }

               fseek($f, $storage + $r['strOffset']);
               $raw = fread($f, $r['length']);
               if ($raw === false || $raw === '') {
                  continue;
               }

               /* Windows platform uses UTF-16 BE; Mac/others use Latin-1 */
               if ($r['platform'] === 3) {
                  $text = mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
               } else {
                  $text = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
               }
               $text = trim((string)$text);
               if ($text === '') {
                  continue;
               }

               if ($r['nameID'] === 4 && $fullName === null) {
                  $fullName = $text;
               } elseif ($r['nameID'] === 1 && $family === null) {
                  $family = $text;
               }
            }

            /* Stop after first pass if we found what we need */
            if ($fullName !== null || $family !== null) {
               break;
            }
         }

         return $fullName ?? $family;

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
      return Plugin::getWebDir('phonebg') . '/front/resource.send.php?resource=base';
   }
}
