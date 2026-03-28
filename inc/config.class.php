<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

class PluginPhonebgConfig {

   const TABLE = 'glpi_plugin_phonebg_config';

   /** Default values — used when no row exists in the table */
   private static array $defaults = [
      'name_x'     => 0,
      'name_y'     => 500,
      'name_size'  => 60,
      'mobile_x'   => 0,
      'mobile_y'   => 590,
      'mobile_size'=> 60,
      'font_color' => '#000000',
      'font_file'  => 'DejaVuSans.ttf',
   ];

   /* -------------------------------------------------------
    * Read
    * ------------------------------------------------------- */

   public static function get(string $name): mixed
   {
      global $DB;
      $iter = $DB->request([
         'SELECT' => ['value'],
         'FROM'   => self::TABLE,
         'WHERE'  => ['name' => $name],
         'LIMIT'  => 1,
      ]);
      if (count($iter)) {
         return $iter->current()['value'];
      }
      return self::$defaults[$name] ?? null;
   }

   public static function getAll(): array
   {
      global $DB;
      $result = self::$defaults;
      $iter   = $DB->request(['FROM' => self::TABLE]);
      foreach ($iter as $row) {
         if (array_key_exists($row['name'], $result)) {
            $result[$row['name']] = $row['value'];
         }
      }
      return $result;
   }

   /* -------------------------------------------------------
    * Write
    * ------------------------------------------------------- */

   public static function set(string $name, mixed $value): void
   {
      global $DB;
      $exists = count($DB->request([
         'SELECT' => ['id'],
         'FROM'   => self::TABLE,
         'WHERE'  => ['name' => $name],
         'LIMIT'  => 1,
      ]));
      if ($exists) {
         $DB->update(self::TABLE, ['value' => (string)$value], ['name' => $name]);
      } else {
         $DB->insert(self::TABLE, ['name' => $name, 'value' => (string)$value]);
      }
   }

   public static function resetToDefaults(): void
   {
      global $DB;
      $DB->delete(self::TABLE, [1]);
   }

   public static function getDefaults(): array
   {
      return self::$defaults;
   }

   /* -------------------------------------------------------
    * Schema
    * ------------------------------------------------------- */

   public static function createTable(): void
   {
      global $DB;
      $DB->doQuery("
         CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
            `id`    int unsigned    NOT NULL AUTO_INCREMENT,
            `name`  varchar(100)   NOT NULL,
            `value` text,
            PRIMARY KEY (`id`),
            UNIQUE  KEY `name` (`name`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      ");
   }

   public static function dropTable(): void
   {
      global $DB;
      $DB->doQuery("DROP TABLE IF EXISTS `" . self::TABLE . "`");
   }
}
