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
        private int    $slideshowInterval,
        /** @var array{download: bool, source: bool, share: bool} */
        private array  $defaultShareOptions,
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
        $siteTitle        = 'ICO';
        $siteDescription  = '';
        $basePath         = '';
        $slideshowInterval = 5;
        $defaultShareOpts  = ['download' => true, 'source' => true, 'share' => true];

        if (file_exists($configFile)) {
            $lines             = explode("\n", file_get_contents($configFile));
            $siteTitle         = trim($lines[0]);
            $siteDescription   = trim($lines[1] ?? '');
            $basePath          = trim($lines[2] ?? '');
            $rawInterval       = (int) trim($lines[3] ?? '');
            $slideshowInterval = $rawInterval >= 1 ? $rawInterval : 5;

            if (isset($lines[4])) {
                $decoded = json_decode(trim($lines[4]), true);
                if (is_array($decoded)) {
                    $defaultShareOpts = [
                        'download' => (bool) ($decoded['download'] ?? true),
                        'source'   => (bool) ($decoded['source']   ?? true),
                        'share'    => (bool) ($decoded['share']     ?? true),
                    ];
                }
            }
        }

        $version = 'inconnue';
        if (file_exists($versionFile)) {
            $version = trim(file_get_contents($versionFile));
        }

        return new self($siteTitle, $siteDescription, $basePath, $version, $slideshowInterval, $defaultShareOpts);
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
     * Intervalle du diaporama en secondes (ligne 4 de config.txt, défaut 5).
     */
    public function getSlideshowInterval(): int
    {
        return $this->slideshowInterval;
    }

    /**
     * Options de partage globales par défaut (ligne 4 de config.txt).
     *
     * @return array{download: bool, source: bool, share: bool}
     */
    public function getDefaultShareOptions(): array
    {
        return $this->defaultShareOptions;
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
