<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Repository;

use ICO\Repository\LogRepository;
use ICO\Tests\Support\DatabaseTestTrait;
use PHPUnit\Framework\TestCase;

class LogRepositoryTest extends TestCase
{
    use DatabaseTestTrait;

    private LogRepository $repo;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repo = new LogRepository($this->pdo);

        // Insère un admin de base pour les FK
        $this->pdo->exec(
            "INSERT INTO admins (id, username, password_hash) VALUES (1, 'admin', 'hash')"
        );
    }

    // --- log ---

    public function testLogInsertsEntry(): void
    {
        $result = $this->repo->log(1, 'ADD_USER', 'Création de bob', '/path');

        $this->assertTrue($result);
        $this->assertSame(1, $this->repo->count());
    }

    public function testLogWithoutTargetPath(): void
    {
        $this->repo->log(1, 'UPDATE_SETTINGS', 'Mise à jour des paramètres');

        $logs = $this->repo->findAll();
        $this->assertNull($logs[0]['target_path']);
    }

    // --- findAll ---

    public function testFindAllReturnsLogsOrderedByDateDesc(): void
    {
        // Inserts avec des dates explicites pour garantir l'ordre
        $this->pdo->exec(
            "INSERT INTO admin_logs (admin_id, action_type, action_description, created_at)
             VALUES (1, 'ADD_USER', 'premier', datetime('now', '-1 minute'))"
        );
        $this->pdo->exec(
            "INSERT INTO admin_logs (admin_id, action_type, action_description, created_at)
             VALUES (1, 'DELETE_USER', 'second', datetime('now'))"
        );

        $logs = $this->repo->findAll();

        $this->assertCount(2, $logs);
        // Le plus récent en premier
        $this->assertSame('DELETE_USER', $logs[0]['action_type']);
    }

    public function testFindAllFiltersByActionType(): void
    {
        $this->repo->log(1, 'ADD_USER', 'ajout');
        $this->repo->log(1, 'DELETE_USER', 'suppression');

        $logs = $this->repo->findAll(['action_type' => 'ADD_USER']);

        $this->assertCount(1, $logs);
        $this->assertSame('ADD_USER', $logs[0]['action_type']);
    }

    public function testFindAllFiltersByAdminId(): void
    {
        $this->pdo->exec(
            "INSERT INTO admins (id, username, password_hash) VALUES (2, 'other', 'hash')"
        );
        $this->repo->log(1, 'ADD_USER', 'par admin 1');
        $this->repo->log(2, 'ADD_USER', 'par admin 2');

        $logs = $this->repo->findAll(['admin_id' => 2]);

        $this->assertCount(1, $logs);
        $this->assertSame('other', $logs[0]['username']);
    }

    public function testFindAllRespectsPagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->repo->log(1, 'ADD_USER', "log $i");
        }

        $page1 = $this->repo->findAll([], 2, 0);
        $page2 = $this->repo->findAll([], 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
    }

    // --- count ---

    public function testCountReturnsTotal(): void
    {
        $this->repo->log(1, 'ADD_USER', 'a');
        $this->repo->log(1, 'ADD_USER', 'b');

        $this->assertSame(2, $this->repo->count());
    }

    public function testCountWithFilter(): void
    {
        $this->repo->log(1, 'ADD_USER', 'a');
        $this->repo->log(1, 'DELETE_USER', 'b');

        $this->assertSame(1, $this->repo->count(['action_type' => 'ADD_USER']));
    }

    // --- findDistinctActionTypes ---

    public function testFindDistinctActionTypesReturnsUniqueTypes(): void
    {
        $this->repo->log(1, 'ADD_USER', 'a');
        $this->repo->log(1, 'ADD_USER', 'b');
        $this->repo->log(1, 'DELETE_USER', 'c');

        $types = $this->repo->findDistinctActionTypes();

        $this->assertCount(2, $types);
        $this->assertContains('ADD_USER', $types);
        $this->assertContains('DELETE_USER', $types);
    }

    // --- deleteOlderThan ---

    public function testDeleteOlderThanRemovesOldLogs(): void
    {
        // Insère un log ancien manuellement
        $this->pdo->exec(
            "INSERT INTO admin_logs (admin_id, action_type, action_description, created_at)
             VALUES (1, 'OLD_ACTION', 'vieux log', datetime('now', '-2 months'))"
        );
        $this->repo->log(1, 'RECENT', 'recent');

        $deleted = $this->repo->deleteOlderThan('-1 month');

        $this->assertSame(1, $deleted);
        $this->assertSame(1, $this->repo->count());
    }
}
