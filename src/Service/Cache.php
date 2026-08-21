<?php

declare(strict_types=1);

namespace GlpiPlugin\Phonebg\Service;

use Glpi\Cache\CacheManager;
use Throwable;
use Toolbox;

final class Cache
{
   private static bool $scheduled = false;

   public static function clearOnLifecycle(): void
   {
      if (self::$scheduled) {
         return;
      }
      self::$scheduled = true;

      register_shutdown_function(static function (): void {
         try {
            (new CacheManager())->resetAllCaches();
         } catch (Throwable $e) {
            if (method_exists(Toolbox::class, 'logInFile')) {
               Toolbox::logInFile('phonebg', 'Unable to clear GLPI regenerable cache after plugin lifecycle: ' . $e->getMessage(), false, false);
            }
         }
      });
   }
}
