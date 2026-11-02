<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$requiredFiles = [
    'setup.php',
    'hook.php',
    'plugin.xml',
    'src/Controller/PhonebgController.php',
    'src/Model/Phone.php',
    'src/Service/Background.php',
    'src/Service/Config.php',
    'src/Service/Paths.php',
    'templates/config_form.html.twig',
    'templates/phone_tab.html.twig',
    'locales/phonebg.pot',
    'locales/es_MX.po',
    'locales/fr_FR.po',
    'fonts/DejaVuSans.ttf',
];

foreach ($requiredFiles as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        $errors[] = 'Missing required plugin file: ' . $relativePath;
    }
}

$metadata = simplexml_load_file($root . '/plugin.xml');
if ($metadata === false) {
    $errors[] = 'plugin.xml is not valid XML.';
} else {
    if ((string) $metadata->key !== 'phonebg') {
        $errors[] = 'plugin.xml must declare the phonebg plugin key.';
    }

    $compatibility = [];
    foreach ($metadata->versions->version as $version) {
        foreach ($version->compatibility as $entry) {
            $compatibility[] = (string) $entry;
        }
    }

    if (!in_array('~11.0', $compatibility, true) || !in_array('~12.0', $compatibility, true)) {
        $errors[] = 'Marketplace metadata must retain GLPI 11 and GLPI 12 compatibility.';
    }

    $setup = file_get_contents($root . '/setup.php');
    if (!is_string($setup)
        || preg_match(
            "/define\s*\(\s*['\"]PLUGIN_PHONEBG_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/",
            $setup,
            $matches
        ) !== 1) {
        $errors[] = 'Unable to read PLUGIN_PHONEBG_VERSION from setup.php.';
    } else {
        $setupVersion = $matches[1];
        $marketplaceVersion = isset($metadata->versions->version[0]->num)
            ? (string) $metadata->versions->version[0]->num
            : '';

        if ($setupVersion !== $marketplaceVersion) {
            $errors[] = 'setup.php and plugin.xml versions do not match.';
        }
    }
}

$sourceDirectory = $root . '/src';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $relativePath = substr($file->getPathname(), strlen($sourceDirectory) + 1);
    $directory = str_replace('/', '\\', dirname($relativePath));
    $expectedNamespace = 'GlpiPlugin\\Phonebg'
        . ($directory === '.' ? '' : '\\' . $directory);
    $contents = file_get_contents($file->getPathname());

    if (!is_string($contents)
        || preg_match('/^namespace\s+([^;]+);/m', $contents, $matches) !== 1) {
        $errors[] = 'Missing namespace declaration: src/' . $relativePath;
        continue;
    }

    if (trim($matches[1]) !== $expectedNamespace) {
        $errors[] = sprintf(
            'PSR-4 namespace mismatch in src/%s: expected %s, found %s.',
            $relativePath,
            $expectedNamespace,
            trim($matches[1])
        );
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }

    exit(1);
}

echo "Plugin metadata, required files, and PSR-4 namespaces are valid.\n";
