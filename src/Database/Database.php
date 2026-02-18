<?php

declare(strict_types=1);

namespace ICO\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton PDO pour l'accès à la base SQLite.
 *
 * Remplace les `new SQLite3('database.sqlite')` instanciés à la volée
 * dans chaque fonction de fonctions.php.
 *
 * Usage :
 *   $pdo = Database::getInstance()->getPdo();
 */
final class Database
{
    private static ?self $instance = null;

    private PDO $pdo;

    private function __construct(string $dbPath)
    {
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Active les clés étrangères (désactivées par défaut dans SQLite)
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Impossible d\'ouvrir la base de données : ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Retourne l'instance unique, en la créant si nécessaire.
     *
     * @param string $dbPath Chemin absolu ou relatif vers le fichier .sqlite.
     *                       Ignoré si l'instance est déjà créée.
     */
    public static function getInstance(string $dbPath = 'database.sqlite'): self
    {
        if (self::$instance === null) {
            self::$instance = new self($dbPath);
        }

        return self::$instance;
    }

    /**
     * Retourne la connexion PDO sous-jacente.
     * Les Repositories l'injecteront via ce getter.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Réinitialise le singleton (utile pour les tests).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
