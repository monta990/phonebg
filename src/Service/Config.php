<?php

declare(strict_types=1);

namespace GlpiPlugin\Phonebg\Service;

use Throwable;

final class Config
{
   /** Legacy table retained only for a one-time, verified migration. */
   public const LEGACY_TABLE = 'glpi_plugin_phonebg_config';
   private const CONTEXT = 'plugin:phonebg';

   private static ?array $cache = null;

   /** Default values for supported plugin configuration keys. */
   private static array $defaults = [
      'name_x'         => 0,
      'name_y'         => 500,
      'name_size'      => 60,
      'mobile_x'       => 0,
      'mobile_y'       => 590,
      'mobile_size'    => 60,
      'font_color'     => '#000000',
      'font_file'      => 'DejaVuSans.ttf',
      'label1_enabled' => '0',
      'label1_text'    => '',
      'label1_x'       => 0,
      'label1_y'       => 650,
      'label1_size'    => 40,
      'label2_enabled' => '0',
      'label2_text'    => '',
      'label2_x'       => 0,
      'label2_y'       => 720,
      'label2_size'    => 40,
      'email_subject'  => 'Phone wallpaper — {name}',
      'email_body'     => 'Please find your phone wallpaper attached.',
      'email_footer'   => '',
   ];

   public static function get(string $name): mixed
   {
      $all = self::getAll();
      return $all[$name] ?? null;
   }

   /**
    * Read only this plugin's values through GLPI's native configuration API.
    */
   public static function getAll(): array
   {
      if (self::$cache !== null) {
         return self::$cache;
      }

      $result = self::$defaults;
      $stored = \Config::getConfigurationValues(self::CONTEXT, array_keys(self::$defaults));
      foreach ($stored as $name => $value) {
         if (array_key_exists($name, $result)) {
            $result[$name] = (string) ($value ?? '');
         }
      }

      return self::$cache = $result;
   }

   /** Save only known plugin settings via GLPI's native configuration API. */
   public static function saveAll(array $data): void
   {
      $data = array_intersect_key($data, self::$defaults);
      if ($data === []) {
         return;
      }

      foreach ($data as $name => $value) {
         $data[$name] = (string) $value;
      }

      \Config::setConfigurationValues(self::CONTEXT, $data);
      self::$cache = null;
   }

   /** Remove all current settings so calls read the built-in defaults. */
   public static function resetToDefaults(): void
   {
      \Config::deleteConfigurationValues(self::CONTEXT, array_keys(self::$defaults));
      self::$cache = null;
   }

   public static function getDefaults(): array
   {
      return self::$defaults;
   }

   /** Ensure every supported setting has a value in GLPI's configuration store. */
   public static function ensureDefaults(): void
   {
      $existing = \Config::getConfigurationValues(self::CONTEXT, array_keys(self::$defaults));
      $missing = array_diff_key(self::$defaults, $existing);

      if ($missing !== []) {
         self::saveAll($missing);
      }

      // Do not silently accept failed writes during install/update.
      $stored = \Config::getConfigurationValues(self::CONTEXT, array_keys(self::$defaults));
      foreach (self::$defaults as $name => $_default) {
         if (!array_key_exists($name, $stored)) {
            throw new \RuntimeException('Unable to initialize Phone Background configuration key: ' . $name);
         }
      }

      self::$cache = null;
   }

   /**
    * Migrate supported values from the legacy plugin-owned table.
    * Existing native values win, making repeated/partial migrations idempotent.
    * The caller may drop the legacy table only after this method returns true.
    */
   public static function migrateLegacyStorage(): bool
   {
      global $DB;

      try {
         if (!$DB->tableExists(self::LEGACY_TABLE)) {
            return true;
         }

         $legacyValues = [];
         foreach ($DB->request(['FROM' => self::LEGACY_TABLE]) as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '' && array_key_exists($name, self::$defaults)) {
               $legacyValues[$name] = (string) ($row['value'] ?? '');
            }
         }

         $existing = \Config::getConfigurationValues(self::CONTEXT, array_keys(self::$defaults));
         $toSave = [];
         $expected = [];

         foreach ($legacyValues as $name => $legacyValue) {
            if (array_key_exists($name, $existing)) {
               // If a previous migration wrote this key, preserve that newer value.
               $expected[$name] = (string) ($existing[$name] ?? '');
            } else {
               $toSave[$name] = $legacyValue;
               $expected[$name] = $legacyValue;
            }
         }

         if ($toSave !== []) {
            \Config::setConfigurationValues(self::CONTEXT, $toSave);
         }

         $persisted = \Config::getConfigurationValues(self::CONTEXT, array_keys($expected));
         foreach ($expected as $name => $value) {
            if (!array_key_exists($name, $persisted)
                || (string) ($persisted[$name] ?? '') !== $value) {
               \Toolbox::logInFile(
                  'phonebg',
                  'Legacy configuration migration verification failed for key: ' . $name,
                  false,
                  false
               );
               return false;
            }
         }

         return true;
      } catch (Throwable $e) {
         \Toolbox::logInFile(
            'phonebg',
            'Legacy configuration migration failed; the legacy table was preserved. ' . $e->getMessage(),
            false,
            false
         );
         return false;
      }
   }

   /** Remove every key in this plugin's dedicated GLPI configuration context. */
   public static function removeConfiguration(): void
   {
      $existing = \Config::getConfigurationValues(self::CONTEXT);
      if ($existing !== []) {
         \Config::deleteConfigurationValues(self::CONTEXT, array_keys($existing));
      }
      self::$cache = null;
   }
}
