<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Repository;

use ICO\Repository\ShareKeyRepository;
use ICO\Tests\Support\DatabaseTestTrait;
use PHPUnit\Framework\TestCase;

class ShareKeyRepositoryTest extends TestCase
{
    use DatabaseTestTrait;

    private ShareKeyRepository $repo;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repo = new ShareKeyRepository($this->pdo);

        // Insère un album de base pour les FK
        $this->pdo->exec(
            "INSERT INTO album_identifiers (identifier, path) VALUES ('album-uuid', './liste_albums_prives/test')"
        );
    }

    private function insertKey(string $key, string $albumId, string $expiresAt, string $comment = ''): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO share_keys (key_value, album_identifier, expires_at, comment) VALUES (:k, :a, :e, :c)'
        );
        $stmt->execute([':k' => $key, ':a' => $albumId, ':e' => $expiresAt, ':c' => $comment]);
    }

    // --- findAll ---

    public function testFindAllReturnsAllKeysWhenFilterIsAll(): void
    {
        $this->insertKey('active-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));
        $this->insertKey('expired-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('-1 hour')));

        $results = $this->repo->findAll('all');

        $this->assertCount(2, $results);
    }

    public function testFindAllFiltersActiveKeys(): void
    {
        $this->insertKey('active-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));
        $this->insertKey('expired-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('-1 hour')));

        $results = $this->repo->findAll('active');

        $this->assertCount(1, $results);
        $this->assertSame('active-key', $results[0]['key_value']);
    }

    public function testFindAllFiltersExpiredKeys(): void
    {
        $this->insertKey('active-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));
        $this->insertKey('expired-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('-1 hour')));

        $results = $this->repo->findAll('expired');

        $this->assertCount(1, $results);
        $this->assertSame('expired-key', $results[0]['key_value']);
    }

    public function testFindAllFiltersbyAlbumIdentifier(): void
    {
        $this->pdo->exec(
            "INSERT INTO album_identifiers (identifier, path) VALUES ('other-uuid', './liste_albums_prives/other')"
        );
        $this->insertKey('key1', 'album-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));
        $this->insertKey('key2', 'other-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));

        $results = $this->repo->findAll('all', 'album-uuid');

        $this->assertCount(1, $results);
        $this->assertSame('key1', $results[0]['key_value']);
    }

    // --- findValidByKey ---

    public function testFindValidByKeyReturnsRowForActiveKey(): void
    {
        $this->insertKey('valid-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));

        $result = $this->repo->findValidByKey('valid-key');

        $this->assertNotNull($result);
        $this->assertSame('./liste_albums_prives/test', $result['path']);
    }

    public function testFindValidByKeyReturnsNullForExpiredKey(): void
    {
        $this->insertKey('expired-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('-1 hour')));

        $this->assertNull($this->repo->findValidByKey('expired-key'));
    }

    public function testFindValidByKeyReturnsNullForUnknownKey(): void
    {
        $this->assertNull($this->repo->findValidByKey('unknown'));
    }

    public function testFindValidForPathReturnsRowForDescendantPath(): void
    {
        $this->insertKey('parent-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));

        $result = $this->repo->findValidForPath('parent-key', './liste_albums_prives/test/child/photo.jpg');

        $this->assertNotNull($result);
        $this->assertSame('./liste_albums_prives/test', $result['path']);
    }

    public function testFindValidForPathReturnsNullForSiblingPath(): void
    {
        $this->insertKey('parent-key', 'album-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));

        $this->assertNull($this->repo->findValidForPath('parent-key', './liste_albums_prives/test-sibling/photo.jpg'));
    }

    // --- create ---

    public function testCreateInsertsKeyAndReturnsValue(): void
    {
        $key = $this->repo->create('album-uuid', 24, 'test comment', [], fn (): string => 'fixed-key-value');

        $this->assertSame('fixed-key-value', $key);
        $this->assertNotNull($this->repo->findValidByKey('fixed-key-value'));
    }

    public function testCreateUsesDefaultGeneratorWhenNoneProvided(): void
    {
        $key = $this->repo->create('album-uuid', 1);

        $this->assertNotEmpty($key);
        $this->assertSame(64, strlen($key)); // bin2hex(random_bytes(32)) = 64 chars
    }

    public function testCreateStoresOptionsAsJson(): void
    {
        $options = ['download' => false, 'source' => true, 'share' => false];
        $this->repo->create('album-uuid', 24, '', $options, fn (): string => 'opts-key');

        $row = $this->pdo->query("SELECT options FROM share_keys WHERE key_value = 'opts-key'")->fetch();
        $this->assertNotFalse($row);

        $decoded = json_decode((string) $row['options'], true);
        $this->assertFalse($decoded['download']);
        $this->assertTrue($decoded['source']);
        $this->assertFalse($decoded['share']);
    }

    public function testFindValidByKeyReturnsOptions(): void
    {
        $options = ['download' => false, 'source' => true, 'share' => true];
        $this->repo->create('album-uuid', 24, '', $options, fn (): string => 'opts-key2');

        $result = $this->repo->findValidByKey('opts-key2');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('options', $result);
        $decoded = json_decode((string) $result['options'], true);
        $this->assertFalse($decoded['download']);
    }

    // --- deleteById ---

    public function testDeleteByIdRemovesKey(): void
    {
        $this->insertKey('to-delete', 'album-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));
        $id = (int) $this->pdo->query('SELECT id FROM share_keys WHERE key_value = "to-delete"')->fetchColumn();

        $result = $this->repo->deleteById($id);

        $this->assertTrue($result);
        $this->assertNull($this->repo->findValidByKey('to-delete'));
    }

    public function testDeleteByIdReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repo->deleteById(9999));
    }

    // --- deleteExpired ---

    public function testDeleteExpiredRemovesOnlyExpiredKeys(): void
    {
        $this->insertKey('active', 'album-uuid', date('Y-m-d H:i:s', strtotime('+1 hour')));
        $this->insertKey('expired1', 'album-uuid', date('Y-m-d H:i:s', strtotime('-1 hour')));
        $this->insertKey('expired2', 'album-uuid', date('Y-m-d H:i:s', strtotime('-2 hours')));

        $deleted = $this->repo->deleteExpired();

        $this->assertSame(2, $deleted);
        $this->assertCount(1, $this->repo->findAll('all'));
    }

    // --- illimité (durationHours = 0) ---

    public function testCreateWithZeroDurationStoresSentinelDate(): void
    {
        $this->repo->create('album-uuid', 0, '', [], fn (): string => 'unlimited-key');

        $row = $this->pdo->query("SELECT expires_at FROM share_keys WHERE key_value = 'unlimited-key'")->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('9999-12-31 23:59:59', $row['expires_at']);
    }

    public function testFindValidByKeyReturnsUnlimitedKey(): void
    {
        $this->repo->create('album-uuid', 0, '', [], fn (): string => 'unlimited-key2');

        $result = $this->repo->findValidByKey('unlimited-key2');

        $this->assertNotNull($result);
    }

    public function testFindAllActiveIncludesUnlimitedKey(): void
    {
        $this->repo->create('album-uuid', 0, '', [], fn (): string => 'unlimited-key3');

        $results = $this->repo->findAll('active');

        $this->assertCount(1, $results);
        $this->assertSame('unlimited-key3', $results[0]['key_value']);
    }

    public function testFindAllExpiredExcludesUnlimitedKey(): void
    {
        $this->repo->create('album-uuid', 0, '', [], fn (): string => 'unlimited-key4');

        $results = $this->repo->findAll('expired');

        $this->assertCount(0, $results);
    }

    public function testDeleteExpiredDoesNotDeleteUnlimitedKey(): void
    {
        $this->repo->create('album-uuid', 0, '', [], fn (): string => 'unlimited-key5');
        $this->insertKey('expired', 'album-uuid', date('Y-m-d H:i:s', strtotime('-1 hour')));

        $deleted = $this->repo->deleteExpired();

        $this->assertSame(1, $deleted);
        $this->assertNotNull($this->repo->findValidByKey('unlimited-key5'));
    }
}
