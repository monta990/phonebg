<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ControllerSecurityTest extends TestCase
{
    public function testEveryControllerRouteRequiresAuthentication(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Controller/PhonebgController.php'
        );

        self::assertIsString($source);

        preg_match_all('/#\[Route\(/', $source, $routes);
        preg_match_all(
            '/#\[SecurityStrategy\(Firewall::STRATEGY_AUTHENTICATED\)\]\s*#\[Route\(/',
            $source,
            $securedRoutes
        );

        self::assertNotEmpty($routes[0]);
        self::assertCount(count($routes[0]), $securedRoutes[0]);
    }

    public function testConfigurationAndResourceHandlersCheckRights(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Controller/PhonebgController.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString(
            "\\Session::checkRight('config', UPDATE)",
            $source
        );
        self::assertStringContainsString(
            "\\Session::checkRight('config', READ)",
            $source
        );
    }

    public function testSendHandlerRejectsNonPostRequestsAndChecksOwnership(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Controller/PhonebgController.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString(
            'if (!$request->isMethod(',
            $source
        );
        self::assertStringContainsString(
            'if (!$is_admin && !$is_owner)',
            $source
        );
    }

    public function testBackgroundHandlerChecksPhoneAccess(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Controller/PhonebgController.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString(
            'if (!$this->canAccessPhone($phone))',
            $source
        );
    }
}
