<?php

declare(strict_types=1);

namespace GlpiPlugin\Phonebg\Service;

final class Config
{
   public const TABLE = 'glpi_plugin_phonebg_config';
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

   public static function getAll(): array
   {
      if (self::$cache !== null) {
         return self::$cache;
      }

      global $DB;
      $result = self::$defaults;
      $iterator = $DB->request(['FROM' => self::TABLE]);
      foreach ($iterator as $row) {
         $name = (string) ($row['name'] ?? '');
         if ($name !== '' && array_key_exists($name, $result)) {
            $result[$name] = (string) ($row['value'] ?? '');
         }
      }

      return self::$cache = $result;
   }

   public static function saveAll(array $data): void
   {
      $data = array_intersect_key($data, self::$defaults);
      if ($data === []) {
         return;
      }

      global $DB;
      foreach ($data as $name => $value) {
         $DB->updateOrInsert(
            self::TABLE,
            [
               'name'  => (string) $name,
               'value' => (string) $value,
            ],
            [
               'name' => (string) $name,
            ]
         );
      }

      self::$cache = null;
   }

   public static function resetToDefaults(): void
   {
      global $DB;
      $DB->delete(self::TABLE, ['name' => array_keys(self::$defaults)]);
      self::$cache = null;
   }

   public static function getDefaults(): array
   {
      return self::$defaults;
   }

   public static function ensureDefaults(): void
   {
      global $DB;
      $existing = [];
      $iterator = $DB->request(['SELECT' => ['name'], 'FROM' => self::TABLE]);
      foreach ($iterator as $row) {
         $existing[(string) $row['name']] = true;
      }

      $missing = [];
      foreach (self::$defaults as $name => $value) {
         if (!isset($existing[$name])) {
            $missing[$name] = $value;
         }
      }

      if ($missing !== []) {
         self::saveAll($missing);
      }
   }


}
