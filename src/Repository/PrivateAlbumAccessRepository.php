<?php

declare(strict_types=1);

namespace ICO\Repository;

use PDO;
use Throwable;

/**
 * Gère les droits permanents des visiteurs sur les albums privés.
 */
class PrivateAlbumAccessRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<string> */
    public function findIdentifiersForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT album_identifier FROM user_private_album_access WHERE user_id = :user_id ORDER BY album_identifier'
        );
        $stmt->execute([':user_id' => $userId]);

        return array_map(strval(...), $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int, array{identifier: string, path: string}> */
    public function findAlbumsForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.identifier, a.path
             FROM user_private_album_access u
             JOIN album_identifiers a ON a.identifier = u.album_identifier
             WHERE u.user_id = :user_id
             ORDER BY a.path'
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /** @param list<string> $albumIdentifiers */
    public function replaceForUser(int $userId, array $albumIdentifiers): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM user_private_album_access WHERE user_id = :user_id');
            $delete->execute([':user_id' => $userId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO user_private_album_access (user_id, album_identifier)
                 VALUES (:user_id, :album_identifier)'
            );

            foreach (array_values(array_unique($albumIdentifiers)) as $identifier) {
                $insert->execute([
                    ':user_id' => $userId,
                    ':album_identifier' => $identifier,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    public function findGrantedRootForPath(int $userId, string $path): ?string
    {
        foreach ($this->findAlbumsForUser($userId) as $album) {
            $root = $this->normalizePath($album['path']);
            $candidate = $this->normalizePath($path);

            if ($candidate === $root || str_starts_with($candidate, $root . '/')) {
                return $album['path'];
            }
        }

        return null;
    }

    public function canAccessPath(int $userId, string $path): bool
    {
        return $this->findGrantedRootForPath($userId, $path) !== null;
    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);

        return rtrim(str_replace('\\', '/', $realPath !== false ? $realPath : $path), '/');
    }
}
