<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Service;

use ICO\Service\VestikanLinkService;
use ICO\Tests\Support\DatabaseTestTrait;
use PHPUnit\Framework\TestCase;
use VestikanException;

class VestikanLinkServiceTest extends TestCase
{
    use DatabaseTestTrait;

    private VestikanLinkService $service;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->service = new VestikanLinkService($this->pdo);
    }

    public function testResolveAdminIdReturnsNullWhenNotLinked(): void
    {
        $this->assertNull($this->service->resolveAdminId('unknown-vestikan-id'));
    }

    public function testLinkThenResolveAdminIdReturnsLinkedAdmin(): void
    {
        $this->service->link('vestikan-id-1', 42);

        $this->assertSame(42, $this->service->resolveAdminId('vestikan-id-1'));
    }

    public function testLinkThrowsWhenVestikanIdAlreadyLinked(): void
    {
        $this->service->link('vestikan-id-1', 42);

        $this->expectException(VestikanException::class);
        $this->service->link('vestikan-id-1', 99);
    }

    public function testFindLinkedVestikanIdReturnsNullWhenNoLink(): void
    {
        $this->assertNull($this->service->findLinkedVestikanId(1));
    }

    public function testFindLinkedVestikanIdReturnsIdWhenLinked(): void
    {
        $this->service->link('vestikan-id-2', 7);

        $this->assertSame('vestikan-id-2', $this->service->findLinkedVestikanId(7));
    }

    public function testUnlinkRemovesLinkAndReturnsTrue(): void
    {
        $this->service->link('vestikan-id-3', 5);

        $this->assertTrue($this->service->unlink('vestikan-id-3'));
        $this->assertNull($this->service->resolveAdminId('vestikan-id-3'));
    }

    public function testUnlinkReturnsFalseWhenNoLinkExists(): void
    {
        $this->assertFalse($this->service->unlink('never-linked'));
    }
}
