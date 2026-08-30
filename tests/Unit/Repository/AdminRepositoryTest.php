<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Repository;

use ICO\Enum\UserRole;
use ICO\Repository\AdminRepository;
use ICO\Tests\Support\DatabaseTestTrait;
use PHPUnit\Framework\TestCase;

class AdminRepositoryTest extends TestCase
{
    use DatabaseTestTrait;

    private AdminRepository $repo;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repo = new AdminRepository($this->pdo);
    }

    private function insertAdmin(string $username, string $password = 'hash'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admins (username, password_hash) VALUES (:u, :p)'
        );
        $stmt->execute([':u' => $username, ':p' => $password]);
        return (int) $this->pdo->lastInsertId();
    }

    // --- findByUsername ---

    public function testFindByUsernameReturnsAdminWhenExists(): void
    {
        $this->insertAdmin('alice');

        $result = $this->repo->findByUsername('alice');

        $this->assertNotNull($result);
        $this->assertSame('alice', $result['username']);
    }

    public function testFindByUsernameReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findByUsername('nobody'));
    }

    // --- findById ---

    public function testFindByIdReturnsAdminWhenExists(): void
    {
        $id = $this->insertAdmin('bob');

        $result = $this->repo->findById($id);

        $this->assertNotNull($result);
        $this->assertSame('bob', $result['username']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    // --- findAll ---

    public function testFindAllReturnsAllAdminsOrderedById(): void
    {
        $this->insertAdmin('charlie');
        $this->insertAdmin('alice');

        $results = $this->repo->findAll();

        $this->assertCount(2, $results);
        $this->assertSame('charlie', $results[0]['username']);
        $this->assertSame('alice', $results[1]['username']);
    }

    public function testFindAllReturnsEmptyArrayWhenNoAdmins(): void
    {
        $this->assertSame([], $this->repo->findAll());
    }

    // --- findFirstAdminId ---

    public function testFindFirstAdminIdReturnsLowestId(): void
    {
        $first = $this->insertAdmin('first');
        $this->insertAdmin('second');

        $this->assertSame($first, $this->repo->findFirstAdminId());
    }

    public function testFindFirstAdminIdReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->repo->findFirstAdminId());
    }

    // --- getUsernameById ---

    public function testGetUsernameByIdReturnsUsername(): void
    {
        $id = $this->insertAdmin('diana');

        $this->assertSame('diana', $this->repo->getUsernameById($id));
    }

    public function testGetUsernameByIdReturnsInconnuWhenNotFound(): void
    {
        $this->assertSame('Inconnu', $this->repo->getUsernameById(999));
    }

    // --- usernameExists ---

    public function testUsernameExistsReturnsTrueWhenTaken(): void
    {
        $this->insertAdmin('taken');

        $this->assertTrue($this->repo->usernameExists('taken'));
    }

    public function testUsernameExistsReturnsFalseWhenFree(): void
    {
        $this->assertFalse($this->repo->usernameExists('free'));
    }

    public function testUsernameExistsExcludesGivenId(): void
    {
        $id = $this->insertAdmin('eve');

        // L'username 'eve' existe mais appartient à $id → pas un conflit pour $id lui-même
        $this->assertFalse($this->repo->usernameExists('eve', $id));
    }

    // --- create ---

    public function testCreateInsertsAndReturnsId(): void
    {
        $id = $this->repo->create('newuser', 'hashvalue', UserRole::ADMINISTRATOR);

        $this->assertGreaterThan(0, $id);
        $this->assertNotNull($this->repo->findById($id));
    }

    public function testCreateStoresSelectedRole(): void
    {
        $this->insertAdmin('main');
        $id = $this->repo->create('visitor', 'hashvalue', UserRole::VISITOR);

        $this->assertSame('visitor', $this->repo->findById($id)['role']);
        $this->assertSame(UserRole::VISITOR, $this->repo->getEffectiveRole($id));
    }

    public function testDatabaseDefaultRoleUsesLeastPrivilege(): void
    {
        $id = $this->insertAdmin('implicit-role');

        $this->assertSame('visitor', $this->repo->findById($id)['role']);
    }

    // --- update ---

    public function testUpdateChangesUsername(): void
    {
        $id = $this->insertAdmin('old');

        $this->repo->update($id, 'new');

        $this->assertSame('new', $this->repo->findById($id)['username']);
    }

    public function testUpdateChangesUsernameAndPassword(): void
    {
        $id = $this->insertAdmin('user', 'oldhash');

        $this->repo->update($id, 'user', 'newhash');

        $row = $this->repo->findById($id);
        $this->assertSame('newhash', $row['password_hash']);
    }

    public function testUpdateChangesRole(): void
    {
        $this->insertAdmin('main');
        $id = $this->insertAdmin('user');

        $this->repo->update($id, 'user', null, UserRole::MODERATOR);

        $this->assertSame('moderator', $this->repo->findById($id)['role']);
    }

    // --- updatePassword ---

    public function testUpdatePasswordChangesHash(): void
    {
        $id = $this->insertAdmin('user', 'oldhash');

        $this->repo->updatePassword($id, 'newhash');

        $this->assertSame('newhash', $this->repo->findById($id)['password_hash']);
    }

    // --- delete ---

    public function testDeleteRemovesAdmin(): void
    {
        $this->insertAdmin('first');
        $second = $this->insertAdmin('second');

        $result = $this->repo->delete($second);

        $this->assertTrue($result);
        $this->assertNull($this->repo->findById($second));
    }

    public function testDeleteRefusesToDeleteFirstAdmin(): void
    {
        $first = $this->insertAdmin('main');

        $result = $this->repo->delete($first);

        $this->assertFalse($result);
        $this->assertNotNull($this->repo->findById($first));
    }
}
