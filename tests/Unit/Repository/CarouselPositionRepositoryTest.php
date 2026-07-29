<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Repository;

use ICO\Repository\CarouselPositionRepository;
use ICO\Tests\Support\DatabaseTestTrait;
use PHPUnit\Framework\TestCase;

class CarouselPositionRepositoryTest extends TestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    public function testFindAllReturnsEmptyArrayWhenNoPositionSaved(): void
    {
        $repo = new CarouselPositionRepository($this->pdo);

        $this->assertSame([], $repo->findAll());
    }

    public function testSaveOrderPersistsPositionsIndexedByFilename(): void
    {
        $repo = new CarouselPositionRepository($this->pdo);

        $repo->saveOrder(['c.jpg', 'a.jpg', 'b.jpg']);

        $this->assertSame(['c.jpg' => 0, 'a.jpg' => 1, 'b.jpg' => 2], $repo->findAll());
    }

    public function testSaveOrderReplacesPreviousOrder(): void
    {
        $repo = new CarouselPositionRepository($this->pdo);

        $repo->saveOrder(['a.jpg', 'b.jpg']);
        $repo->saveOrder(['b.jpg', 'a.jpg']);

        $this->assertSame(['b.jpg' => 0, 'a.jpg' => 1], $repo->findAll());
    }
}
