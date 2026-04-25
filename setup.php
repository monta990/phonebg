<?php
if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

function plugin_init_phonebg() {
   global $PLUGIN_HOOKS;
   $PLUGIN_HOOKS['csrf_compliant']['phonebg'] = true;
   $PLUGIN_HOOKS['config_page']['phonebg'] = 'front/config.form.php';

   Plugin::registerClass(
      'PluginPhonebgPhone',
      ['addtabon' => ['Phone']]
   );
}

function plugin_version_phonebg()
{
   return [
      'name'         => 'Phone Background',
      'version'      => '1.5.0',
      'author'       => 'Edwin Elias Alvarez',
      'homepage'     => 'https://github.com/monta990/phonebg',
      'license'      => 'GPLv3+',
      'requirements' => [
         'glpi' => [
            'min' => '10.0',
         ],
         'php'  => [
            'min' => '8.0',
         ],
      ]
   ];
}

function plugin_phonebg_install() {
   /* Create storage directories */
   foreach (['templates', 'fonts'] as $sub) {
      $dir = GLPI_DOC_DIR . '/_plugins/phonebg/' . $sub;
      if (!is_dir($dir)) {
         mkdir($dir, 0755, true);
      }
   }

   /* Copy bundled DejaVuSans.ttf to user fonts dir (if not already there) */
   $dest = GLPI_DOC_DIR . '/_plugins/phonebg/fonts/DejaVuSans.ttf';
   if (!file_exists($dest)) {
      foreach (GLPI_PLUGINS_DIRECTORIES as $pdir) {
         $src = $pdir . '/phonebg/fonts/DejaVuSans.ttf';
         if (is_readable($src)) {
            copy($src, $dest);
            break;
         }
      }
   }

   PluginPhonebgConfig::createTable();
   return true;
}

function plugin_phonebg_uninstall() {
   PluginPhonebgConfig::dropTable();
   return true;
}

function plugin_phonebg_check_prerequisites() {
   return true;
}

function plugin_phonebg_check_config() {
   return true;
}
