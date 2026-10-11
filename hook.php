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

      $query = "CREATE TABLE `" . $table . "` (
         `id` int unsigned NOT NULL AUTO_INCREMENT,
         `name` varchar(100) NOT NULL,
         `value` text,
         PRIMARY KEY (`id`),
         UNIQUE KEY `name` (`name`)
      ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . " COLLATE=" . $collation . " ROW_FORMAT=DYNAMIC";

      // Defer schema creation to GLPI's migration lifecycle.
      $migration->addPostQuery($query);
   }

   // Execute the schema query before Config accesses the table.
   $migration->executeMigration();
   Config::ensureDefaults();
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
