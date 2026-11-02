<?php

declare(strict_types=1);

namespace Tests\Unit;

use GlpiPlugin\Phonebg\Service\VersionChecker;
use PHPUnit\Framework\TestCase;

final class VersionCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        $cacheFile = GLPI_CACHE_DIR . '/phonebg/release_cache.json';
        if (is_file($cacheFile)) {
            unlink($cacheFile);
        }
    }

    public function testCachedStatusDetectsNewerStableVersion(): void
    {
        $this->writeCache([
            'latest_version' => 'v1.6.5',
            'checked_at' => time(),
        ]);

        $status = VersionChecker::getStatus();

        self::assertSame('1.6.4', $status['current_version']);
        self::assertSame('1.6.5', $status['latest_version']);
        self::assertTrue($status['update_available']);
        self::assertTrue($status['from_cache']);
        self::assertTrue($status['github_available']);
    }

    public function testCachedStatusDoesNotOfferUpdateForSameVersion(): void
    {
        $this->writeCache([
            'latest_version' => '1.6.4',
            'checked_at' => time(),
        ]);

        $status = VersionChecker::getStatus();

        self::assertSame('1.6.4', $status['latest_version']);
        self::assertFalse($status['update_available']);
        self::assertTrue($status['from_cache']);
    }

    public function testCachedStatusHandlesMissingVersion(): void
    {
        $this->writeCache(['checked_at' => time()]);

        $status = VersionChecker::getStatus();

        self::assertNull($status['latest_version']);
        self::assertFalse($status['update_available']);
        self::assertTrue($status['from_cache']);
    }

    private function writeCache(array $data): void
    {
        $directory = GLPI_CACHE_DIR . '/phonebg';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $directory . '/release_cache.json',
            json_encode($data, JSON_THROW_ON_ERROR)
        );
    }
}
