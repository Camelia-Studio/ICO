<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Database;

use ICO\Database\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        Database::reset();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    // -------------------------------------------------------------------------
    // getInstance / singleton
    // -------------------------------------------------------------------------

    public function testGetInstanceReturnsSameInstance(): void
    {
        $a = Database::getInstance(':memory:');
        $b = Database::getInstance(':memory:');

        $this->assertSame($a, $b);
    }

    public function testGetInstanceIgnoresSecondPathArgument(): void
    {
        $first  = Database::getInstance(':memory:');
        $second = Database::getInstance('/nonexistent/path.sqlite');

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // getPdo
    // -------------------------------------------------------------------------

    public function testGetPdoReturnsPdoInstance(): void
    {
        $db = Database::getInstance(':memory:');

        $this->assertInstanceOf(PDO::class, $db->getPdo());
    }

    public function testGetPdoHasForeignKeysEnabled(): void
    {
        $db  = Database::getInstance(':memory:');
        $pdo = $db->getPdo();

        $result = $pdo->query('PRAGMA foreign_keys')->fetchColumn();

        $this->assertSame('1', (string) $result);
    }

    public function testGetPdoFetchModeIsAssoc(): void
    {
        $db  = Database::getInstance(':memory:');
        $pdo = $db->getPdo();

        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $pdo->exec('INSERT INTO t VALUES (42)');

        $row = $pdo->query('SELECT id FROM t')->fetch();

        $this->assertIsArray($row);
        $this->assertArrayHasKey('id', $row);
    }

    // -------------------------------------------------------------------------
    // reset
    // -------------------------------------------------------------------------

    public function testResetAllowsNewInstance(): void
    {
        $first = Database::getInstance(':memory:');
        Database::reset();
        $second = Database::getInstance(':memory:');

        $this->assertNotSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // Chemin invalide → RuntimeException
    // -------------------------------------------------------------------------

    public function testInvalidPathThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/Impossible d'ouvrir/");

        // Un chemin dans un dossier inexistant force PDO à lever une exception
        Database::getInstance('/nonexistent_directory_xyz/db.sqlite');
    }
}
