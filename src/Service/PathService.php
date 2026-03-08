<?php

declare(strict_types=1);

namespace ICO\Service;

/**
 * Utilitaires de conversion entre chemins absolus filesystem, chemins relatifs et URLs publiques.
 *
 * - toRelative() : /var/www/ICO/liste_albums/foo  → liste_albums/foo
 * - toUrl()      : /var/www/ICO/liste_albums/foo/bar.jpg → https://host/base/liste_albums/foo/bar.jpg
 * - toAbsolute() : liste_albums/foo → /var/www/ICO/liste_albums/foo
 */
final readonly class PathService
{
    public function __construct(
        /** Chemin absolu vers la racine du projet, sans slash final */
        private string $projectRoot,
        /** URL publique de base, sans slash final (ex: https://host ou https://host/base) */
        private string $baseUrl,
    ) {
    }

    /**
     * Convertit un chemin absolu filesystem en chemin relatif au projectRoot.
     *
     * @param string $absolutePath Chemin absolu, ex: /var/www/ICO/liste_albums/foo
     * @return string              Chemin relatif, ex: liste_albums/foo
     */
    public function toRelative(string $absolutePath): string
    {
        return ltrim(str_replace('\\', '/', substr($absolutePath, strlen($this->projectRoot))), '/');
    }

    /**
     * Convertit un chemin absolu filesystem en URL publique.
     *
     * @param string $absolutePath Chemin absolu, ex: /var/www/ICO/liste_albums/foo/bar.jpg
     * @return string              URL publique, ex: https://host/base/liste_albums/foo/bar.jpg
     */
    public function toUrl(string $absolutePath): string
    {
        $relative = str_replace('\\', '/', substr($absolutePath, strlen($this->projectRoot) + 1));
        return $this->baseUrl . '/' . ltrim($relative, '/');
    }

    /**
     * Convertit un chemin relatif au projectRoot en chemin absolu filesystem.
     *
     * @param string $relativePath Chemin relatif, ex: liste_albums/foo
     * @return string              Chemin absolu, ex: /var/www/ICO/liste_albums/foo
     */
    public function toAbsolute(string $relativePath): string
    {
        return $this->projectRoot . '/' . ltrim($relativePath, '/');
    }

    /**
     * Retourne l'URL publique de base (sans slash final).
     *
     * @return string Ex: https://host ou https://host/base
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
