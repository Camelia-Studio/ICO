<?php

declare(strict_types=1);

namespace ICO\Repository;

use PDO;

/**
 * Accès aux données de la table `share_keys`.
 *
 * Couvre les requêtes précédemment dans createShareKey(), validateShareKey(),
 * cleanExpiredShareKeys() dans fonctions.php, ainsi que clefs.php et arbre-prive.php.
 */
class ShareKeyRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Retourne les clés de partage avec filtres optionnels.
     * Joint la table album_identifiers pour exposer le chemin de l'album.
     *
     * @param string $filter       'active' | 'expired' | 'all'
     * @param string $albumFilter  Identifiant UUID de l'album, ou '' pour tous
     * @return array<int, array<string, mixed>>
     */
    public function findAll(string $filter = 'all', string $albumFilter = ''): array
    {
        $conditions = ['1=1'];
        $params = [];

        if ($filter === 'active') {
            $conditions[] = 's.expires_at > datetime("now")';
        } elseif ($filter === 'expired') {
            $conditions[] = 's.expires_at <= datetime("now")';
        }

        if ($albumFilter !== '') {
            $conditions[] = 'a.identifier = :album_identifier';
            $params[':album_identifier'] = $albumFilter;
        }

        $where = implode(' AND ', $conditions);

        $stmt = $this->pdo->prepare(
            "SELECT s.*, a.path, a.identifier as album_identifier
             FROM share_keys s
             JOIN album_identifiers a ON s.album_identifier = a.identifier
             WHERE {$where}
             ORDER BY s.created_at DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Valide une clé de partage et retourne les infos de l'album associé.
     * Retourne null si la clé est invalide ou expirée.
     *
     * @return array<string, mixed>|null ['path', 'identifier']
     */
    public function findValidByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.path, a.identifier
             FROM share_keys s
             JOIN album_identifiers a ON s.album_identifier = a.identifier
             WHERE s.key_value = :key
             AND s.expires_at > datetime("now")'
        );
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Crée une nouvelle clé de partage. Retourne la valeur de la clé générée.
     *
     * @param int    $durationHours Durée de validité en heures
     */
    public function create(
        string $albumIdentifier,
        int $durationHours,
        string $comment = '',
        ?callable $keyGenerator = null
    ): string {
        $key = $keyGenerator !== null
            ? ($keyGenerator)()
            : bin2hex(random_bytes(32));

        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$durationHours} hours"));

        $stmt = $this->pdo->prepare(
            'INSERT INTO share_keys (key_value, album_identifier, expires_at, comment)
             VALUES (:key, :identifier, :expires, :comment)'
        );
        $stmt->execute([
            ':key'        => $key,
            ':identifier' => $albumIdentifier,
            ':expires'    => $expiresAt,
            ':comment'    => $comment,
        ]);

        return $key;
    }

    /**
     * Supprime une clé par son id. Retourne true si une ligne a été supprimée.
     */
    public function deleteById(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM share_keys WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime toutes les clés expirées. Retourne le nombre de suppressions.
     */
    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM share_keys WHERE expires_at <= datetime("now")'
        );
        $stmt->execute();

        return $stmt->rowCount();
    }
}
