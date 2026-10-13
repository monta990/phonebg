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
   // Move old table-backed settings first. If verification fails, preserve the
   // legacy table and abort instead of silently reverting the user's settings.
   if (!Config::migrateLegacyStorage()) {
      return false;
   }

   try {
      Config::ensureDefaults();
   } catch (Throwable $e) {
      \Toolbox::logInFile(
         'phonebg',
         'Unable to initialize native plugin configuration; legacy storage was preserved. ' . $e->getMessage(),
         false,
         false
      );
      return false;
   }

   // The old table is dropped only after migration and default initialization
   // have succeeded. The plugin does not create its own configuration table.
   $migration = new Migration(PLUGIN_PHONEBG_VERSION);
   $migration->dropTable(Config::LEGACY_TABLE);
   $migration->executeMigration();

   Paths::ensureBundledFont();
   Cache::clearOnLifecycle();
   return true;
}

function plugin_phonebg_uninstall(): bool
{
   // Remove only this plugin's namespaced entries from glpi_configs.
   Config::removeConfiguration();

   // Compatibility cleanup for installations being upgraded from older releases.
   $migration = new Migration(PLUGIN_PHONEBG_VERSION);
   $migration->dropTable(Config::LEGACY_TABLE);
   $migration->executeMigration();

   Cache::clearOnLifecycle();
   return true;
}
