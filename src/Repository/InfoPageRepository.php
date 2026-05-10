<?php

declare(strict_types=1);

namespace ICO\Repository;

use PDO;

/**
 * Accès aux données de la table `info_pages`.
 */
class InfoPageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retourne toutes les pages, triées par date de création décroissante.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM info_pages ORDER BY created_at DESC');

        return $stmt->fetchAll();
    }

    /**
     * Retourne toutes les pages publiées (pour affichage public).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findPublished(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM info_pages WHERE is_published = 1 ORDER BY created_at ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM info_pages WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Retourne une page publiée par son slug.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM info_pages WHERE slug = :slug AND is_published = 1'
        );
        $stmt->execute([':slug' => $slug]);

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Vérifie si un slug est déjà utilisé (en excluant optionnellement un id pour l'édition).
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM info_pages WHERE slug = :slug';
        $params = [':slug' => $slug];

        if ($excludeId !== null) {
            $sql           .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crée une nouvelle page. Retourne l'id inséré.
     */
    public function create(string $title, string $slug, string $content, bool $isPublished): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO info_pages (title, slug, content, is_published)
             VALUES (:title, :slug, :content, :is_published)'
        );
        $stmt->execute([
            ':title'        => $title,
            ':slug'         => $slug,
            ':content'      => $content,
            ':is_published' => $isPublished ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Met à jour une page existante. Retourne true si une ligne a été modifiée.
     */
    public function update(int $id, string $title, string $slug, string $content, bool $isPublished): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE info_pages
             SET title = :title, slug = :slug, content = :content,
                 is_published = :is_published, updated_at = datetime("now")
             WHERE id = :id'
        );
        $stmt->execute([
            ':id'           => $id,
            ':title'        => $title,
            ':slug'         => $slug,
            ':content'      => $content,
            ':is_published' => $isPublished ? 1 : 0,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime une page par son id. Retourne true si une ligne a été supprimée.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM info_pages WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
