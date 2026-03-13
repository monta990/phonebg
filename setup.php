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

function plugin_version_phonebg() {
   return [
      'name'         => 'Phone Background',
      'version'      => '1.2.1',
      'author'       => 'Edwin Elias Alvarez',
      'license'      => 'GPLv2+',
      'homepage'     => 'https://sontechs.com',
      'requirements' => [
         'glpi' => [
            'min' => '11.0',
            'max' => '12.0',
         ]
      ]
   ];
}

function plugin_phonebg_install() {
   $dir = GLPI_DOC_DIR . '/_plugins/phonebg/templates';
   if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
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
