<?php

declare(strict_types=1);

namespace ICO\Repository;

use PDO;

/**
 * Accès aux données de la table `album_identifiers`.
 */
class AlbumIdentifierRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retourne l'identifiant UUID d'un album à partir de son chemin filesystem.
     * Retourne null si l'album n'a pas encore d'identifiant.
     */
    public function findIdentifierByPath(string $path): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT identifier FROM album_identifiers WHERE path = :path'
        );
        $stmt->execute([':path' => $path]);

        $row = $stmt->fetch();

        return $row !== false ? $row['identifier'] : null;
    }

    /**
     * Retourne le chemin filesystem d'un album à partir de son identifiant UUID.
     *
     * @return array<string, mixed>|null ['identifier', 'path']
     */
    public function findByIdentifier(string $identifier): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT identifier, path FROM album_identifiers WHERE identifier = :identifier'
        );
        $stmt->execute([':identifier' => $identifier]);

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Retourne tous les albums enregistrés, triés par chemin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT identifier, path FROM album_identifiers ORDER BY path ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Crée un nouvel identifiant pour un chemin donné.
     * Retourne l'identifiant inséré.
     */
    public function create(string $identifier, string $path): string
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO album_identifiers (identifier, path) VALUES (:identifier, :path)'
        );
        $stmt->execute([':identifier' => $identifier, ':path' => $path]);

        return $identifier;
    }

    /**
     * Retourne l'identifiant d'un album, en le créant s'il n'existe pas encore.
     * Equivalent de l'ancienne fonction ensureAlbumIdentifier().
     *
     * Le générateur est une callable qui produit un identifiant unique.
     * Par défaut : bin2hex(random_bytes(16)) → 32 caractères hex.
     */
    public function ensure(string $path, ?callable $generator = null): string
    {
        $existing = $this->findIdentifierByPath($path);
        if ($existing !== null) {
            return $existing;
        }

        $identifier = $generator !== null
            ? ($generator)()
            : bin2hex(random_bytes(16));

        return $this->create($identifier, $path);
    }
}
