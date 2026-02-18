<?php

declare(strict_types=1);

namespace ICO\Service;

use ICO\Repository\AdminRepository;

/**
 * Gère l'authentification et la session administrateur.
 *
 * Remplace checkAdminSession() et la logique de login/logout
 * dispersée dans admin.php et fonctions.php.
 */
class AuthService
{
    /** Durée de vie de session en secondes (24h). */
    private const SESSION_TIMEOUT = 86400;

    public function __construct(private readonly AdminRepository $adminRepository) {}

    /**
     * Tente d'authentifier un admin par son username/mot de passe en clair.
     * En cas de succès, initialise la session et retourne true.
     * En cas d'échec, retourne false.
     */
    public function login(string $username, string $password): bool
    {
        $admin = $this->adminRepository->findByUsername($username);

        if ($admin === null) {
            return false;
        }

        if (!password_verify($password, $admin['password_hash'])) {
            return false;
        }

        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['last_activity']  = time();

        return true;
    }

    /**
     * Détruit la session courante (logout).
     */
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Vérifie que la session admin est active et non expirée.
     *
     * Retourne true si la session est valide.
     * Retourne false si l'admin n'est pas connecté ou si la session a expiré
     * (dans ce cas la session est détruite).
     */
    public function isLoggedIn(): bool
    {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }

        if (isset($_SESSION['last_activity'])
            && (time() - $_SESSION['last_activity']) > self::SESSION_TIMEOUT
        ) {
            session_destroy();
            return false;
        }

        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Retourne l'id de l'admin connecté, ou null si non connecté.
     */
    public function getLoggedInAdminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    /**
     * Retourne le nom d'utilisateur de l'admin connecté, ou null si non connecté.
     */
    public function getLoggedInUsername(): ?string
    {
        return $_SESSION['admin_username'] ?? null;
    }

    /**
     * Vérifie si un mot de passe en clair correspond au hash stocké pour un admin donné.
     */
    public function verifyPassword(int $adminId, string $plainPassword): bool
    {
        $admin = $this->adminRepository->findById($adminId);

        if ($admin === null) {
            return false;
        }

        return password_verify($plainPassword, $admin['password_hash']);
    }

    /**
     * Génère un hash sécurisé pour un mot de passe en clair.
     */
    public function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }
}
