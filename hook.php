<?php

declare(strict_types=1);

use GlpiPlugin\Phonebg\Service\Cache;
use GlpiPlugin\Phonebg\Service\Config;
use GlpiPlugin\Phonebg\Service\Paths;

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

function plugin_phonebg_install(): bool
{
   global $DB;

   $migration = new Migration(PLUGIN_PHONEBG_VERSION);
   $table = Config::TABLE;

   if (!$DB->tableExists($table)) {
      $charset = \DBConnection::getDefaultCharset();
      $collation = \DBConnection::getDefaultCollation();

      $DB->doQuery(
         "CREATE TABLE `" . $table . "` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `value` text,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
         ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . " ROW_FORMAT=DYNAMIC"
      );
   }

   // Future structural changes belong here on the Migration instance.
   Config::ensureDefaults();

   $migration->executeMigration();
   Paths::ensureBundledFont();
   Cache::clearOnLifecycle();
   return true;
}

function plugin_phonebg_uninstall(): bool
{
   $migration = new Migration(PLUGIN_PHONEBG_VERSION);
   $migration->dropTable(Config::TABLE);
   $migration->executeMigration();
   Cache::clearOnLifecycle();
   return true;
}
