<?php

declare(strict_types=1);

namespace ICO\Repository;

use ICO\Enum\UserRole;
use PDO;

/**
 * Accès aux données de la table `admins`.
 */
class AdminRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Trouve un admin par son nom d'utilisateur.
     * Retourne ['id', 'username', 'password_hash', 'role', 'created_at'] ou null.
     *
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, password_hash, role, created_at FROM admins WHERE username = :username'
        );
        $stmt->execute([':username' => $username]);

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Trouve un admin par son identifiant.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, password_hash, role, created_at FROM admins WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Retourne tous les utilisateurs triés par id croissant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, username, role, created_at FROM admins ORDER BY id ASC');

        return $stmt->fetchAll();
    }

    /**
     * Retourne l'id du premier administrateur créé (admin principal).
     */
    public function findFirstAdminId(): ?int
    {
        $stmt = $this->pdo->query('SELECT MIN(id) as first_id FROM admins');
        $row = $stmt->fetch();

        return isset($row['first_id']) ? (int) $row['first_id'] : null;
    }

    public function getEffectiveRole(int $id): ?UserRole
    {
        $admin = $this->findById($id);
        if ($admin === null) {
            return null;
        }

        if ($id === $this->findFirstAdminId()) {
            return UserRole::ADMINISTRATOR;
        }

        return UserRole::tryFrom((string) $admin['role']) ?? UserRole::ADMINISTRATOR;
    }

    /**
     * Retourne le nom d'utilisateur d'un admin, ou "Inconnu" si introuvable.
     */
    public function getUsernameById(int $id): string
    {
        $row = $this->findById($id);

        return $row['username'] ?? 'Inconnu';
    }

    /**
     * Vérifie qu'un nom d'utilisateur n'est pas déjà pris (optionnellement en excluant un id).
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM admins WHERE username = :username AND id != :id'
            );
            $stmt->execute([':username' => $username, ':id' => $excludeId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM admins WHERE username = :username'
            );
            $stmt->execute([':username' => $username]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crée un nouvel admin. Retourne l'id inséré.
     */
    public function create(
        string $username,
        string $passwordHash,
        UserRole $role = UserRole::ADMINISTRATOR,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admins (username, password_hash, role) VALUES (:username, :password_hash, :role)'
        );
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => $passwordHash,
            ':role' => $role->value,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Met à jour le nom d'utilisateur et optionnellement le hash du mot de passe.
     */
    public function update(
        int $id,
        string $username,
        ?string $passwordHash = null,
        ?UserRole $role = null,
    ): bool {
        if ($passwordHash !== null && $role instanceof UserRole) {
            $stmt = $this->pdo->prepare(
                'UPDATE admins SET username = :username, password_hash = :password_hash, role = :role WHERE id = :id'
            );
            return $stmt->execute([
                ':username'      => $username,
                ':password_hash' => $passwordHash,
                ':role'          => $role->value,
                ':id'            => $id,
            ]);
        }

        if ($passwordHash !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE admins SET username = :username, password_hash = :password_hash WHERE id = :id'
            );
            return $stmt->execute([
                ':username'      => $username,
                ':password_hash' => $passwordHash,
                ':id'            => $id,
            ]);
        }

        if ($role instanceof UserRole) {
            $stmt = $this->pdo->prepare(
                'UPDATE admins SET username = :username, role = :role WHERE id = :id'
            );
            return $stmt->execute([
                ':username' => $username,
                ':role' => $role->value,
                ':id' => $id,
            ]);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE admins SET username = :username WHERE id = :id'
        );
        return $stmt->execute([':username' => $username, ':id' => $id]);
    }

    /**
     * Met à jour uniquement le hash du mot de passe.
     */
    public function updatePassword(int $id, string $passwordHash): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admins SET password_hash = :password_hash WHERE id = :id'
        );
        return $stmt->execute([':password_hash' => $passwordHash, ':id' => $id]);
    }

    /**
     * Supprime un admin, en refusant de supprimer le compte principal.
     * Retourne false si l'id correspond au premier admin.
     */
    public function delete(int $id): bool
    {
        if ($id === $this->findFirstAdminId()) {
            return false;
        }

        $stmt = $this->pdo->prepare('DELETE FROM admins WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
