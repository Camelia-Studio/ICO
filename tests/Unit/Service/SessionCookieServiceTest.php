<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Service;

use ICO\Service\SessionCookieService;
use PHPUnit\Framework\TestCase;

class SessionCookieServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function testRefreshDoesNotThrow(): void
    {
        $service = new SessionCookieService();

        $this->expectNotToPerformAssertions();
        $service->refresh(time() + 604800);
    }

    public function testExpireDoesNotThrow(): void
    {
        $service = new SessionCookieService();

        $this->expectNotToPerformAssertions();
        $service->expire();
    }
}
