<?php

declare(strict_types=1);

namespace ICO\Repository;

use PDO;

/**
 * Accès aux données de la table `carousel_positions`.
 *
 * Stocke l'ordre manuel (glisser/déposer) des images du carrousel de la page d'accueil,
 * indexé par nom de fichier. Les images sans position enregistrée gardent le tri par
 * défaut (date de création décroissante).
 */
class CarouselPositionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retourne les positions enregistrées, indexées par nom de fichier.
     *
     * @return array<string, int>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT filename, position FROM carousel_positions');

        $positions = [];
        foreach ($stmt->fetchAll() as $row) {
            $positions[(string) $row['filename']] = (int) $row['position'];
        }

        return $positions;
    }

    /**
     * Enregistre l'ordre manuel complet, en remplaçant les positions existantes.
     *
     * @param string[] $orderedFilenames noms de fichiers dans l'ordre souhaité
     */
    public function saveOrder(array $orderedFilenames): void
    {
        $this->pdo->beginTransaction();

        $this->pdo->exec('DELETE FROM carousel_positions');

        $stmt = $this->pdo->prepare(
            'INSERT INTO carousel_positions (filename, position) VALUES (:filename, :position)'
        );
        foreach (array_values($orderedFilenames) as $position => $filename) {
            $stmt->execute([':filename' => $filename, ':position' => $position]);
        }

        $this->pdo->commit();
    }
}
