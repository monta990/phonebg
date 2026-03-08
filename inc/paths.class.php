<?php

class PluginPhonebgPaths {
    
    /**
    * Directorio físico del plugin si esta en plugins (legacy) o marketeplace glpi 10+)
    */
    public static function pluginDir(): string {
    
       foreach (GLPI_PLUGINS_DIRECTORIES as $dir) {
    
          $path = $dir . '/phonebg';
    
          if (is_dir($path)) {
             return $path;
          }
       }
    
       throw new RuntimeException('No se encontró el directorio del complemento phonebg');
    }

   /**
    * Directorio físico (files/)
    */
   public static function filesDir(): string {
      return GLPI_DOC_DIR . '/_plugins/phonebg';
   }

   /**
    * Ruta física del base
    */
   public static function basePath(): string {
      return self::filesDir() . '/templates/base.png';
   }

   /**
    * Ruta física de las fuentes
    */
    public static function getFontDejaVuSans(): string {
       return self::pluginDir() . '/fonts/DejaVuSans.ttf';
    }

   /**
    * URL pública (vía resource.send.php)
    */
    public static function baseUrl(): string {
       return Plugin::getWebDir('phonebg') . '/front/resource.send.php?resource=base';
    }
}
