<?php

declare(strict_types=1);

namespace Tests\Unit;

use GlpiPlugin\Phonebg\Service\Paths;
use PHPUnit\Framework\TestCase;

final class PathsTest extends TestCase
{
    public function testBuildsPluginWebUrlFromGlpiRootDocument(): void
    {
        self::assertSame('/glpi/plugins/phonebg', Paths::webDir());
        self::assertSame('/glpi/plugins/phonebg/config', Paths::configUrl());
        self::assertSame('/glpi/plugins/phonebg/background', Paths::backgroundUrl());
    }

    public function testResolvesPluginAndDataDirectories(): void
    {
        self::assertSame($GLOBALS['PHONEBG_TEST_PLUGIN_DIR'], Paths::pluginDir());
        self::assertSame(
            GLPI_DOC_DIR . '/_plugins/phonebg',
            Paths::filesDir()
        );
    }

    public function testBundledFontIsAvailableAsFallback(): void
    {
        $font = Paths::getFontPath('missing-custom-font.ttf');

        self::assertFileExists($font);
        self::assertSame(
            $GLOBALS['PHONEBG_TEST_PLUGIN_DIR'] . '/fonts/DejaVuSans.ttf',
            $font
        );
    }

    public function testFontDisplayNameFallsBackToReadableFilename(): void
    {
        $font = Paths::fontsDir() . '/Custom_Font.ttf';
        file_put_contents($font, str_repeat("\0", 16));

        self::assertSame('Custom Font', Paths::getFontDisplayName('Custom_Font.ttf'));
        self::assertFileExists($font . '.meta.json');

        Paths::deleteFontMeta('Custom_Font.ttf');

        self::assertFileDoesNotExist($font . '.meta.json');
        unlink($font);
    }

    public function testEnsureBundledFontCopiesMissingFallback(): void
    {
        $destination = Paths::fontsDir() . '/DejaVuSans.ttf';
        if (is_file($destination)) {
            unlink($destination);
        }

        Paths::ensureBundledFont();

        self::assertFileExists($destination);
    }
}
