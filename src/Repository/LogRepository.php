<?php

declare(strict_types=1);

namespace ICO\Repository;

use PDO;

/**
 * Accès aux données de la table `admin_logs`.
 *
 * Couvre les requêtes précédemment dans logAdminAction() dans fonctions.php
 * et toute la logique de lecture/filtrage dans logs.php.
 */
class LogRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Insère une entrée de log.
     */
    public function log(
        int $adminId,
        string $actionType,
        string $description,
        ?string $targetPath = null
    ): bool {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_logs (admin_id, action_type, action_description, target_path)
             VALUES (:admin_id, :action_type, :description, :target_path)'
        );
        return $stmt->execute([
            ':admin_id'    => $adminId,
            ':action_type' => $actionType,
            ':description' => $description,
            ':target_path' => $targetPath,
        ]);
    }

    /**
     * Retourne les logs avec filtres et pagination.
     * Joint la table admins pour exposer le nom d'utilisateur.
     *
     * @param array<string, mixed> $filters  ['action_type' => '', 'admin_id' => 0, 'date_range' => '']
     * @return array<int, array<string, mixed>>
     */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhereClause($filters);

        $stmt = $this->pdo->prepare(
            "SELECT l.*, a.username
             FROM admin_logs l
             LEFT JOIN admins a ON l.admin_id = a.id
             {$where}
             ORDER BY l.created_at DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Compte le nombre total de logs correspondant aux filtres (pour la pagination).
     *
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhereClause($filters);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_logs l {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Retourne la liste des types d'actions distincts (pour le filtre UI).
     *
     * @return string[]
     */
    public function findDistinctActionTypes(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT action_type FROM admin_logs ORDER BY action_type ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Supprime les logs antérieurs à une durée donnée.
     * Par défaut : supprime les logs de plus d'un mois.
     */
    public function deleteOlderThan(string $interval = '-1 month'): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM admin_logs WHERE created_at < datetime("now", :interval)'
        );
        $stmt->execute([':interval' => $interval]);

        return $stmt->rowCount();
    }

    /**
     * Construit la clause WHERE et les paramètres PDO à partir des filtres.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhereClause(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['action_type'])) {
            $conditions[] = 'l.action_type = :action_type';
            $params[':action_type'] = $filters['action_type'];
        }

        if (!empty($filters['admin_id'])) {
            $conditions[] = 'l.admin_id = :admin_id';
            $params[':admin_id'] = (int) $filters['admin_id'];
        }

        if (!empty($filters['date_range'])) {
            $interval = match ($filters['date_range']) {
                '24h'   => '-1 day',
                '48h'   => '-2 days',
                '72h'   => '-3 days',
                '1week' => '-7 days',
                default => null,
            };
            if ($interval !== null) {
                $conditions[] = 'l.created_at >= datetime("now", :date_interval)';
                $params[':date_interval'] = $interval;
            }
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $params];
    }
}
