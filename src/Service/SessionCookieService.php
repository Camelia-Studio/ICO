<?php

declare(strict_types=1);

namespace ICO\Service;

/**
 * Encapsule l'envoi du cookie de session PHP (setcookie()).
 *
 * Isolé dans son propre service car setcookie() est un no-op silencieux
 * sous le SAPI CLI : impossible à observer directement dans les tests
 * unitaires d'AuthService, qui mockent ce service à la place.
 */
class SessionCookieService
{
    /**
     * Repousse l'expiration du cookie de session courant (comportement glissant).
     */
    public function refresh(int $expiresAt): void
    {
        $this->send($expiresAt);
    }

    /**
     * Invalide immédiatement le cookie de session côté navigateur.
     */
    public function expire(): void
    {
        $this->send(time() - 3600);
    }

    private function send(int $expiresAt): void
    {
        setcookie(session_name(), session_id(), [
            'expires'  => $expiresAt,
            'path'     => ini_get('session.cookie_path') ?: '/',
            'domain'   => ini_get('session.cookie_domain') ?: '',
            'secure'   => (bool) ini_get('session.cookie_secure'),
            'httponly' => (bool) ini_get('session.cookie_httponly'),
            'samesite' => ini_get('session.cookie_samesite') ?: 'Lax',
        ]);
    }
}
