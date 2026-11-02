<?php

declare(strict_types=1);

namespace Tests\Unit;

use GlpiPlugin\Phonebg\Service\Background;
use GlpiPlugin\Phonebg\Service\Config as PluginConfig;
use GlpiPlugin\Phonebg\Service\Paths;
use PHPUnit\Framework\TestCase;

final class BackgroundTest extends TestCase
{
    protected function setUp(): void
    {
        \Config::$values = [];
        PluginConfig::resetToDefaults();
        $directory = dirname(Paths::basePath());

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $image = imagecreatetruecolor(320, 240);
        imagepng($image, Paths::basePath());
    }

    public function testGeneratesAValidPngWallpaper(): void
    {
        $generated = Background::generateTestPNG('Office Phone', '555-0100');

        self::assertNotSame('', $generated);
        self::assertFileExists($generated);

        $imageInfo = getimagesize($generated);
        self::assertIsArray($imageInfo);
        self::assertSame(IMAGETYPE_PNG, $imageInfo[2]);

        unlink($generated);
    }

    public function testRejectsTemplatesAboveMaximumDimension(): void
    {
        $image = imagecreatetruecolor(8001, 1);
        imagepng($image, Paths::basePath());

        self::assertSame('', Background::generateTestPNG('Office Phone', '555-0100'));
        self::assertStringContainsString('exceed the maximum allowed', Background::$lastError);
    }

    public function testRejectsUnreadableTemplate(): void
    {
        file_put_contents(Paths::basePath(), 'not-a-png');

        self::assertSame('', Background::generateTestPNG('Office Phone', '555-0100'));
        self::assertSame('Could not read base image dimensions.', Background::$lastError);
    }

    public function testEmailRenderingSubstitutesTokensAndEscapesHtml(): void
    {
        $html = Background::buildEmailHtml(
            'Hello {name} on line {line}: <script>**important**</script>',
            '',
            'Ana & Co',
            '555 < 600'
        );

        self::assertStringContainsString('Ana &amp; Co', $html);
        self::assertStringContainsString('555 &lt; 600', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('<span style="font-weight:bold">important</span>', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testEmailRenderingIncludesFooterAndTestNotice(): void
    {
        $html = Background::buildEmailHtml(
            'Hello {name}',
            'Footer for {line}',
            'Ana',
            '555-0100',
            true
        );

        self::assertStringContainsString('This is a test email', $html);
        self::assertStringContainsString('Footer for 555-0100', $html);
        self::assertStringContainsString('<hr', $html);
    }
}
