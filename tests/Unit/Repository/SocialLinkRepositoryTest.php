<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Repository;

use ICO\Repository\SocialLinkRepository;
use ICO\Tests\Support\DatabaseTestTrait;
use PHPUnit\Framework\TestCase;

class SocialLinkRepositoryTest extends TestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    public function testNextDisplayOrderStartsAtZero(): void
    {
        $repo = new SocialLinkRepository($this->pdo);

        $this->assertSame(0, $repo->nextDisplayOrder());
    }

    public function testNextDisplayOrderAppendsAfterExisting(): void
    {
        $repo = new SocialLinkRepository($this->pdo);
        $repo->create('Instagram', 'https://instagram.com', 0, true);
        $repo->create('YouTube', 'https://youtube.com', 1, true);

        $this->assertSame(2, $repo->nextDisplayOrder());
    }

    public function testReorderUpdatesDisplayOrderAndFindAllReflectsIt(): void
    {
        $repo = new SocialLinkRepository($this->pdo);
        $idA  = $repo->create('A', 'https://a.example', 0, true);
        $idB  = $repo->create('B', 'https://b.example', 1, true);

        $repo->reorder([$idB, $idA]);

        $labels = array_column($repo->findAll(), 'label');
        $this->assertSame(['B', 'A'], $labels);
    }
}
