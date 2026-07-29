<?php

declare(strict_types=1);

namespace ICO\Repository;

use PDO;

/**
 * Accès aux données de la table `social_links`.
 */
class SocialLinkRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retourne tous les liens, triés par ordre d'affichage puis par id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM social_links ORDER BY display_order ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Retourne les liens actifs (pour affichage public).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM social_links WHERE is_active = 1 ORDER BY display_order ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM social_links WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function create(string $label, string $url, int $displayOrder, bool $isActive): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO social_links (label, url, display_order, is_active)
             VALUES (:label, :url, :display_order, :is_active)'
        );
        $stmt->execute([
            ':label'         => $label,
            ':url'           => $url,
            ':display_order' => $displayOrder,
            ':is_active'     => $isActive ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $label, string $url, int $displayOrder, bool $isActive): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE social_links
             SET label = :label, url = :url,
                 display_order = :display_order, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            ':id'            => $id,
            ':label'         => $label,
            ':url'           => $url,
            ':display_order' => $displayOrder,
            ':is_active'     => $isActive ? 1 : 0,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM social_links WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Réordonne les liens selon l'ordre reçu (glisser/déposer).
     *
     * @param int[] $orderedIds identifiants des liens dans l'ordre souhaité
     */
    public function reorder(array $orderedIds): void
    {
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare('UPDATE social_links SET display_order = :display_order WHERE id = :id');
        foreach (array_values($orderedIds) as $displayOrder => $id) {
            $stmt->execute([':display_order' => $displayOrder, ':id' => $id]);
        }

        $this->pdo->commit();
    }

    /**
     * Retourne le prochain ordre d'affichage disponible (fin de liste).
     */
    public function nextDisplayOrder(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(display_order), -1) + 1 FROM social_links');

        return (int) $stmt->fetchColumn();
    }
}
