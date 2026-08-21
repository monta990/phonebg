<?php

declare(strict_types=1);

use Glpi\Plugin\Hooks;
use GlpiPlugin\Phonebg\Model\Phone;

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

define('PLUGIN_PHONEBG_VERSION', '1.6.0');
define('PLUGIN_PHONEBG_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_PHONEBG_MAX_GLPI_VERSION', '13.0.0');

function plugin_init_phonebg(): void
{
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['phonebg'] = 'config';
   Plugin::registerClass(Phone::class, ['addtabon' => \Phone::class]);
}

function plugin_version_phonebg(): array
{
   return [
      'name'       => 'Phone Background',
      'version'    => PLUGIN_PHONEBG_VERSION,
      'author'     => 'Edwin Elias Alvarez',
      'homepage'   => 'https://github.com/monta990/phonebg',
      'license'    => 'GPLv3+',
      'requirements' => [
         'glpi' => [
            'min' => PLUGIN_PHONEBG_MIN_GLPI_VERSION,
            'max' => PLUGIN_PHONEBG_MAX_GLPI_VERSION,
         ],
         'php' => [
            'min' => '8.2',
         ],
      ],
   ];
}

function plugin_phonebg_check_prerequisites(): bool
{
   $ok = true;

   if (version_compare(GLPI_VERSION, PLUGIN_PHONEBG_MIN_GLPI_VERSION, '<')
       || version_compare(GLPI_VERSION, PLUGIN_PHONEBG_MAX_GLPI_VERSION, '>=')) {
      echo sprintf(
         __('This plugin requires GLPI >= %1$s and < %2$s.', 'phonebg'),
         PLUGIN_PHONEBG_MIN_GLPI_VERSION,
         PLUGIN_PHONEBG_MAX_GLPI_VERSION
      ) . '<br>';
      $ok = false;
   }

   if (version_compare(PHP_VERSION, '8.2.0', '<')) {
      echo __('This plugin requires PHP 8.2 or later.', 'phonebg') . '<br>';
      $ok = false;
   }

   if (!extension_loaded('gd')) {
      echo __('The PHP GD extension is required.', 'phonebg') . '<br>';
      $ok = false;
   }

   return $ok;
}

