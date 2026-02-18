<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Repository;

use ICO\Repository\AlbumIdentifierRepository;
use ICO\Tests\Support\DatabaseTestTrait;
use PHPUnit\Framework\TestCase;

class AlbumIdentifierRepositoryTest extends TestCase
{
    use DatabaseTestTrait;

    private AlbumIdentifierRepository $repo;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repo = new AlbumIdentifierRepository($this->pdo);
    }

    // --- findIdentifierByPath ---

    public function testFindIdentifierByPathReturnsIdentifierWhenExists(): void
    {
        $this->repo->create('abc123', './liste_albums/nature');

        $result = $this->repo->findIdentifierByPath('./liste_albums/nature');

        $this->assertSame('abc123', $result);
    }

    public function testFindIdentifierByPathReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findIdentifierByPath('./liste_albums/unknown'));
    }

    // --- findByIdentifier ---

    public function testFindByIdentifierReturnsRowWhenExists(): void
    {
        $this->repo->create('xyz789', './liste_albums/nature');

        $result = $this->repo->findByIdentifier('xyz789');

        $this->assertNotNull($result);
        $this->assertSame('./liste_albums/nature', $result['path']);
        $this->assertSame('xyz789', $result['identifier']);
    }

    public function testFindByIdentifierReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findByIdentifier('notexist'));
    }

    // --- findAll ---

    public function testFindAllReturnsAllAlbumsOrderedByPath(): void
    {
        $this->repo->create('id1', './liste_albums/z-album');
        $this->repo->create('id2', './liste_albums/a-album');

        $results = $this->repo->findAll();

        $this->assertCount(2, $results);
        $this->assertSame('./liste_albums/a-album', $results[0]['path']);
        $this->assertSame('./liste_albums/z-album', $results[1]['path']);
    }

    // --- create ---

    public function testCreateInsertsAlbumAndReturnsIdentifier(): void
    {
        $identifier = $this->repo->create('newid', './liste_albums/test');

        $this->assertSame('newid', $identifier);
        $this->assertNotNull($this->repo->findByIdentifier('newid'));
    }

    // --- ensure ---

    public function testEnsureReturnsExistingIdentifier(): void
    {
        $this->repo->create('existingid', './liste_albums/album');

        $result = $this->repo->ensure('./liste_albums/album', fn() => 'shouldnotbeused');

        $this->assertSame('existingid', $result);
    }

    public function testEnsureCreatesNewIdentifierWhenNotExists(): void
    {
        $result = $this->repo->ensure('./liste_albums/new', fn() => 'generatedid');

        $this->assertSame('generatedid', $result);
        $this->assertNotNull($this->repo->findByIdentifier('generatedid'));
    }

    public function testEnsureUsesDefaultGeneratorWhenNoneProvided(): void
    {
        $result = $this->repo->ensure('./liste_albums/auto');

        $this->assertNotEmpty($result);
        $this->assertSame(32, strlen($result)); // bin2hex(random_bytes(16)) = 32 chars
    }
}
