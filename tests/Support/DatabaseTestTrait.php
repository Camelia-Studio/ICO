<?php

declare(strict_types=1);

namespace ICO\Tests\Support;

use PDO;

/**
 * Trait partagé entre tous les tests de Repository.
 * Fournit une base SQLite en mémoire avec le schéma complet du projet.
 */
trait DatabaseTestTrait
{
    private PDO $pdo;

    protected function setUpDatabase(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->exec('CREATE TABLE admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        $this->pdo->exec('CREATE TABLE album_identifiers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT UNIQUE NOT NULL,
            path TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        $this->pdo->exec("CREATE TABLE share_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_value TEXT UNIQUE NOT NULL,
            album_identifier TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            comment TEXT,
            options TEXT NOT NULL DEFAULT '{\"download\":true,\"source\":true,\"share\":true}',
            FOREIGN KEY (album_identifier) REFERENCES album_identifiers(identifier)
        )");

        $this->pdo->exec('CREATE TABLE admin_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            action_type TEXT NOT NULL,
            action_description TEXT NOT NULL,
            target_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admins(id)
        )');
    }
}
