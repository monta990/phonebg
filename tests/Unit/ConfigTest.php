<?php

declare(strict_types=1);

namespace Tests\Unit;

use GlpiPlugin\Phonebg\Service\Config as PluginConfig;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        \Config::$values = [];
        PluginConfig::resetToDefaults();
        $GLOBALS['DB']->exists = false;
        $GLOBALS['DB']->rows = [];
    }

    public function testDefaultsAreAvailable(): void
    {
        $defaults = PluginConfig::getDefaults();
        $configuration = PluginConfig::getAll();

        self::assertNotEmpty($defaults);
        self::assertSame($defaults, $configuration);
    }

    public function testSaveOnlyPersistsKnownKeys(): void
    {
        PluginConfig::saveAll([
            'name_size' => 72,
            'unknown_setting' => 'must-not-be-saved',
        ]);

        self::assertSame('72', PluginConfig::get('name_size'));
        self::assertArrayNotHasKey(
            'unknown_setting',
            \Config::getConfigurationValues('plugin:phonebg')
        );
    }

    public function testResetRestoresDefaults(): void
    {
        PluginConfig::saveAll(['name_size' => 88]);
        PluginConfig::resetToDefaults();

        self::assertSame(60, PluginConfig::get('name_size'));
    }

    public function testEnsureDefaultsInitializesEverySupportedKey(): void
    {
        PluginConfig::ensureDefaults();

        $stored = \Config::getConfigurationValues('plugin:phonebg');

        self::assertCount(count(PluginConfig::getDefaults()), $stored);
        self::assertSame(
            array_keys(PluginConfig::getDefaults()),
            array_keys($stored)
        );
    }

    public function testLegacyMigrationCopiesValuesAndPreservesLegacyTable(): void
    {
        $GLOBALS['DB']->exists = true;
        $GLOBALS['DB']->rows = [
            ['name' => 'name_size', 'value' => '75'],
            ['name' => 'font_color', 'value' => '#112233'],
            ['name' => 'not_supported', 'value' => 'ignored'],
        ];

        self::assertTrue(PluginConfig::migrateLegacyStorage());
        self::assertSame('75', PluginConfig::get('name_size'));
        self::assertSame('#112233', PluginConfig::get('font_color'));
        self::assertTrue($GLOBALS['DB']->tableExists('glpi_plugin_phonebg_config'));
    }

    public function testLegacyMigrationDoesNotOverwriteNativeValues(): void
    {
        PluginConfig::saveAll(['name_size' => 88]);
        $GLOBALS['DB']->exists = true;
        $GLOBALS['DB']->rows = [
            ['name' => 'name_size', 'value' => '60'],
        ];

        self::assertTrue(PluginConfig::migrateLegacyStorage());
        self::assertSame('88', PluginConfig::get('name_size'));
    }

    public function testMigrationWithoutLegacyTableIsSuccessful(): void
    {
        $GLOBALS['DB']->exists = false;

        self::assertTrue(PluginConfig::migrateLegacyStorage());
    }
}
