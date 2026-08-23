<?php

declare(strict_types=1);

namespace ICO\Config;

/**
 * Configuration du client SSO Vestikan (fichier vestikan-config.php, non versionné).
 *
 * Instanciation via VestikanConfig::fromFile() — si le fichier est absent ou
 * incomplet, l'instance retournée est simplement "non configurée" : le SSO
 * Vestikan reste désactivé sans erreur (voir vestikan-config.sample.php).
 */
final readonly class VestikanConfig
{
    private function __construct(
        private bool   $configured,
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
    ) {
    }

    /**
     * Construit l'instance depuis vestikan-config.php.
     *
     * @param string $configFile Chemin absolu vers vestikan-config.php
     */
    public static function fromFile(string $configFile): self
    {
        if (!file_exists($configFile)) {
            return self::unconfigured();
        }

        $data = require $configFile;

        if (!is_array($data)) {
            return self::unconfigured();
        }

        foreach (['base_url', 'client_id', 'client_secret', 'redirect_uri'] as $key) {
            if (empty($data[$key]) || !is_string($data[$key])) {
                return self::unconfigured();
            }
        }

        return new self(
            true,
            $data['base_url'],
            $data['client_id'],
            $data['client_secret'],
            $data['redirect_uri'],
        );
    }

    private static function unconfigured(): self
    {
        return new self(false, '', '', '', '');
    }

    /**
     * True si vestikan-config.php est présent et complet — le SSO Vestikan
     * peut alors être proposé aux administrateurs.
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Format attendu par le SDK Vestikan (Vestikan::__construct()).
     *
     * @return array{base_url: string, client_id: string, client_secret: string, redirect_uri: string}
     */
    public function toArray(): array
    {
        return [
            'base_url'      => $this->baseUrl,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
        ];
    }
}
