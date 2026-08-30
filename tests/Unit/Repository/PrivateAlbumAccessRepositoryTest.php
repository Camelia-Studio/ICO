<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Repository;

use ICO\Enum\UserRole;
use ICO\Repository\AdminRepository;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\PrivateAlbumAccessRepository;
use ICO\Tests\Support\DatabaseTestTrait;
use PHPUnit\Framework\TestCase;

class PrivateAlbumAccessRepositoryTest extends TestCase
{
    use DatabaseTestTrait;

    private PrivateAlbumAccessRepository $repository;

    private int $visitorId;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $adminRepository = new AdminRepository($this->pdo);
        $adminRepository->create('principal', 'hash');

        $this->visitorId = $adminRepository->create('visiteur', 'hash', UserRole::VISITOR);
        $this->repository = new PrivateAlbumAccessRepository($this->pdo);
    }

    public function testReplaceStoresOnlyTheNewAlbumSelection(): void
    {
        $albums = new AlbumIdentifierRepository($this->pdo);
        $first = $albums->create('album-a', '/private/album-a');
        $second = $albums->create('album-b', '/private/album-b');

        $this->repository->replaceForUser($this->visitorId, [$first, $second]);
        $this->repository->replaceForUser($this->visitorId, [$second]);

        $this->assertSame([$second], $this->repository->findIdentifiersForUser($this->visitorId));
    }

    public function testAccessToAlbumAlsoCoversDescendantsButNotSiblings(): void
    {
        $albums = new AlbumIdentifierRepository($this->pdo);
        $identifier = $albums->create('album-parent', '/private/parent');
        $this->repository->replaceForUser($this->visitorId, [$identifier]);

        $this->assertTrue($this->repository->canAccessPath($this->visitorId, '/private/parent/photo.jpg'));
        $this->assertFalse($this->repository->canAccessPath($this->visitorId, '/private/sibling/photo.jpg'));
    }
}
