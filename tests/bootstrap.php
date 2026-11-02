<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', dirname(__DIR__));
}

if (!defined('PLUGIN_PHONEBG_VERSION')) {
    define('PLUGIN_PHONEBG_VERSION', '1.6.4');
}

$testRoot = sys_get_temp_dir() . '/phonebg-tests-' . getmypid();
$pluginParent = $testRoot . '/plugins';
$pluginDirectory = $pluginParent . '/phonebg';
$documentDirectory = $testRoot . '/glpi';
$temporaryDirectory = $testRoot . '/tmp';
$cacheDirectory = $testRoot . '/cache';
$variableDirectory = $testRoot . '/var';

foreach ([
    $pluginDirectory . '/fonts',
    $documentDirectory . '/_plugins/phonebg/fonts',
    $documentDirectory . '/_plugins/phonebg/templates',
    $temporaryDirectory,
    $cacheDirectory,
    $variableDirectory,
] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create test directory: ' . $directory);
    }
}

if (!defined('GLPI_PLUGINS_DIRECTORIES')) {
    define('GLPI_PLUGINS_DIRECTORIES', [$pluginParent]);
}
if (!defined('GLPI_DOC_DIR')) {
    define('GLPI_DOC_DIR', $documentDirectory);
}
if (!defined('GLPI_TMP_DIR')) {
    define('GLPI_TMP_DIR', $temporaryDirectory);
}
if (!defined('GLPI_CACHE_DIR')) {
    define('GLPI_CACHE_DIR', $cacheDirectory);
}
if (!defined('GLPI_VAR_DIR')) {
    define('GLPI_VAR_DIR', $variableDirectory);
}

$bundledFont = dirname(__DIR__) . '/fonts/DejaVuSans.ttf';
if (!is_file($bundledFont) || !copy($bundledFont, $pluginDirectory . '/fonts/DejaVuSans.ttf')) {
    throw new RuntimeException('Unable to prepare the bundled font for tests.');
}

$GLOBALS['PHONEBG_TEST_PLUGIN_DIR'] = $pluginDirectory;
$GLOBALS['CFG_GLPI'] = ['root_doc' => '/glpi'];

if (!function_exists('__')) {
    function __(string $message, ?string $domain = null): string
    {
        return $message;
    }
}

if (!class_exists('Config', false)) {
    final class Config
    {
        public static array $values = [];

        public static function getConfigurationValues(string $context, ?array $names = null): array
        {
            $values = self::$values[$context] ?? [];

            return $names === null
                ? $values
                : array_intersect_key($values, array_fill_keys($names, true));
        }

        public static function setConfigurationValues(string $context, array $values): void
        {
            self::$values[$context] = array_merge(self::$values[$context] ?? [], $values);
        }

        public static function deleteConfigurationValues(string $context, array $names): void
        {
            foreach ($names as $name) {
                unset(self::$values[$context][$name]);
            }
        }
    }
}

if (!class_exists('Toolbox', false)) {
    final class Toolbox
    {
        public static function logInFile(string $name, string $message, bool $force = false, bool $withDate = false): void
        {
        }
    }
}

final class PhonebgTestDatabase
{
    public bool $exists = false;
    public array $rows = [];

    public function tableExists(string $table): bool
    {
        return $this->exists;
    }

    public function request(array $query): array
    {
        return $this->rows;
    }
}

$GLOBALS['DB'] = new PhonebgTestDatabase();

require_once dirname(__DIR__) . '/src/Service/Paths.php';
require_once dirname(__DIR__) . '/src/Service/Config.php';
require_once dirname(__DIR__) . '/src/Service/Background.php';
require_once dirname(__DIR__) . '/src/Service/VersionChecker.php';
