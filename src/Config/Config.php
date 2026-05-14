<?php

declare(strict_types=1);

namespace ICO\Config;

/**
 * Centralise la lecture et l'accès à la configuration du projet.
 *
 * Instanciation via Config::fromFile() — lecture unique en mémoire.
 */
final readonly class Config
{
    /** Extensions d'images autorisées */
    private const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    /** Extensions vidéo autorisées */
    private const array VIDEO_EXTENSIONS = ['mp4', 'webm'];

    /** Durée de vie de session en secondes (24h) */
    private const int SESSION_LIFETIME = 86400;

    private function __construct(
        private string $siteTitle,
        private string $siteDescription,
        private string $basePath,
        private string $version,
    ) {
    }

    /**
     * Construit l'instance depuis les fichiers config.txt et version.txt.
     *
     * @param string $configFile  Chemin absolu vers config.txt
     * @param string $versionFile Chemin absolu vers version.txt
     */
    public static function fromFile(string $configFile, string $versionFile): self
    {
        $siteTitle       = 'ICO';
        $siteDescription = '';
        $basePath        = '';

        if (file_exists($configFile)) {
            $lines = explode("\n", file_get_contents($configFile));
            $siteTitle       = trim($lines[0]);
            $siteDescription = trim($lines[1] ?? '');
            $basePath        = trim($lines[2] ?? '');
        }

        $version = 'inconnue';
        if (file_exists($versionFile)) {
            $version = trim(file_get_contents($versionFile));
        }

        return new self($siteTitle, $siteDescription, $basePath, $version);
    }

    /**
     * Titre du site (ligne 1 de config.txt).
     */
    public function getSiteTitle(): string
    {
        return $this->siteTitle;
    }

    /**
     * Description du site (ligne 2 de config.txt).
     */
    public function getSiteDescription(): string
    {
        return $this->siteDescription;
    }

    /**
     * Sous-dossier d'installation, sans slash (ligne 3 de config.txt).
     * Ex : "mon-ico" pour domain.com/mon-ico, ou "" pour la racine.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Version courante du projet (contenu de version.txt).
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Extensions d'images autorisées.
     *
     * @return string[]
     */
    public function getAllowedExtensions(): array
    {
        return self::EXTENSIONS;
    }

    /**
     * Extensions vidéo autorisées.
     *
     * @return string[]
     */
    public function getVideoExtensions(): array
    {
        return self::VIDEO_EXTENSIONS;
    }

    /**
     * Configure la durée de vie de session PHP.
     * Doit être appelée avant session_start().
     */
    public function configureSession(): void
    {
        ini_set('session.gc_maxlifetime', (string) self::SESSION_LIFETIME);
        session_set_cookie_params(self::SESSION_LIFETIME);
    }
}
