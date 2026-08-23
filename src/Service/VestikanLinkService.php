<?php

declare(strict_types=1);

namespace ICO\Service;

use PDO;
use Vestikan;
use VestikanException;

/**
 * Gère la liaison entre un vestikan_id et un compte administrateur local,
 * via la table `vestikan_links` (créée et gérée par le SDK Vestikan).
 */
class VestikanLinkService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Résout un vestikan_id vers l'id de l'admin local lié, ou null si aucune liaison.
     */
    public function resolveAdminId(string $vestikanId): ?int
    {
        $localId = Vestikan::resolveLocalUser($this->pdo, $vestikanId);

        return $localId !== null ? (int) $localId : null;
    }

    /**
     * Lie un vestikan_id à un compte admin local.
     *
     * @throws VestikanException si ce vestikan_id est déjà lié.
     */
    public function link(string $vestikanId, int $adminId): void
    {
        Vestikan::link($this->pdo, $vestikanId, (string) $adminId);
    }

    /**
     * Supprime une liaison. Retourne true si une liaison a été supprimée.
     */
    public function unlink(string $vestikanId): bool
    {
        return Vestikan::unlink($this->pdo, $vestikanId);
    }

    /**
     * Retourne le vestikan_id lié à un compte admin local, ou null si non lié.
     */
    public function findLinkedVestikanId(int $adminId): ?string
    {
        Vestikan::setupLinkTable($this->pdo);

        $stmt = $this->pdo->prepare('SELECT vestikan_id FROM vestikan_links WHERE local_user_id = :id');
        $stmt->execute([':id' => (string) $adminId]);

        $row = $stmt->fetch();

        return $row !== false ? (string) $row['vestikan_id'] : null;
    }
}
