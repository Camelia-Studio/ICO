<?php

declare(strict_types=1);

namespace ICO\Service;

/**
 * Valide la force d'un mot de passe selon les règles ICO.
 *
 * Règles : au moins 12 caractères, 1 minuscule, 1 majuscule,
 *          1 chiffre, 1 caractère spécial.
 *
 * Usage :
 *   $error = (new PasswordValidator())->validate($password);
 *   if ($error !== null) { /* afficher l'erreur *\/ }
 */
class PasswordValidator
{
    /**
     * Valide le mot de passe.
     *
     * @return string|null  null si valide, message d'erreur sinon
     */
    public function validate(string $password): ?string
    {
        if (strlen($password) < 12) {
            return 'Le mot de passe doit faire au moins 12 caractères.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'Le mot de passe doit contenir au moins une lettre minuscule.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Le mot de passe doit contenir au moins une lettre majuscule.';
        }

        if (!preg_match('/\d/', $password)) {
            return 'Le mot de passe doit contenir au moins un chiffre.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Le mot de passe doit contenir au moins un caractère spécial.';
        }

        return null;
    }
}
