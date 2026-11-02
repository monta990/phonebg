<?php

declare(strict_types=1);

namespace Tests\Unit;

use GlpiPlugin\Phonebg\Service\VersionChecker;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class VersionCheckerTest extends TestCase
{
    public function testBuildStatusDetectsNewerStableVersion(): void
    {
        $method = new ReflectionMethod(VersionChecker::class, 'buildStatus');
        $method->setAccessible(true);

        $status = $method->invoke(null, [
            'latest_version' => 'v1.6.5',
            'checked_at' => 123456,
        ], true);

        self::assertSame('1.6.4', $status['current_version']);
        self::assertSame('1.6.5', $status['latest_version']);
        self::assertTrue($status['update_available']);
        self::assertTrue($status['from_cache']);
        self::assertSame(123456, $status['checked_at']);
    }

    public function testBuildStatusDoesNotOfferUpdateForSameVersion(): void
    {
        $method = new ReflectionMethod(VersionChecker::class, 'buildStatus');
        $method->setAccessible(true);

        $status = $method->invoke(null, [
            'latest_version' => '1.6.4',
            'checked_at' => 123456,
        ], false);

        self::assertFalse($status['update_available']);
        self::assertFalse($status['from_cache']);
    }

    public function testBuildStatusHandlesMissingVersion(): void
    {
        $method = new ReflectionMethod(VersionChecker::class, 'buildStatus');
        $method->setAccessible(true);

        $status = $method->invoke(null, [], true);

        self::assertNull($status['latest_version']);
        self::assertFalse($status['update_available']);
    }
}
