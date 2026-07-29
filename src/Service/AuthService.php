<?php

declare(strict_types=1);

namespace ICO\Service;

use ICO\Config\Config;
use ICO\Repository\AdminRepository;

/**
 * Gère l'authentification et la session administrateur.
 */
class AuthService
{
    public function __construct(
        private readonly AdminRepository     $adminRepository,
        private readonly Config              $config,
        private readonly SessionCookieService $sessionCookie,
    ) {
    }

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

        if (!password_verify($password, (string) $admin['password_hash'])) {
            return false;
        }

        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['last_activity']  = time();

        return true;
    }

    /**
     * Détruit la session courante (logout) et invalide le cookie côté navigateur.
     */
    public function logout(): void
    {
        $this->sessionCookie->expire();
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Vérifie que la session admin est active et non expirée.
     *
     * Retourne true si la session est valide. La fenêtre glissante est
     * rafraîchie côté serveur (last_activity) ET côté navigateur (expiration
     * du cookie repoussée), pour une vraie persistance de plusieurs jours.
     * Retourne false si l'admin n'est pas connecté ou si la session a expiré
     * (dans ce cas la session est détruite).
     */
    public function isLoggedIn(): bool
    {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }

        $sessionLifetime = $this->config->getSessionLifetime();

        if (isset($_SESSION['last_activity'])
            && (time() - $_SESSION['last_activity']) > $sessionLifetime
        ) {
            session_destroy();
            return false;
        }

        $_SESSION['last_activity'] = time();
        $this->sessionCookie->refresh(time() + $sessionLifetime);

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

        return password_verify($plainPassword, (string) $admin['password_hash']);
    }

    /**
     * Génère un hash sécurisé pour un mot de passe en clair.
     */
    public function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }
}
