<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;

/**
 * Contrôleur de la page d'accueil.
 *
 * Remplace index.php (carousel + overlay titre/description).
 * La vue reste le fichier PHP racine existant tant que la Phase 6 (vues natives) n'est pas activée.
 *
 * Responsabilités extraites :
 *  - getCarouselImages() locale → méthode privée
 *  - appel à getSiteConfig()   → Config injectable
 */
class HomeController
{
    /** Extensions acceptées pour le carousel */
    private const CAROUSEL_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    public function __construct(
        private readonly Config $config,
        private readonly string $projectRoot,
    ) {}

    // -------------------------------------------------------------------------
    // Action principale
    // -------------------------------------------------------------------------

    /**
     * Prépare les données pour la vue d'accueil et les retourne sous forme de tableau.
     *
     * @return array{
     *   carousel_images: string[],
     *   site_title: string,
     *   site_description: string,
     * }
     */
    public function index(Request $request): array
    {
        return [
            'carousel_images'  => $this->getCarouselImages(),
            'site_title'       => $this->config->getSiteTitle(),
            'site_description' => $this->config->getSiteDescription(),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Retourne les 5 images les plus récentes du dossier img_carrousel.
     * Crée le dossier s'il n'existe pas.
     *
     * @return string[]
     */
    private function getCarouselImages(int $limit = 5): array
    {
        $carouselDir = $this->projectRoot . '/img_carrousel';

        if (!is_dir($carouselDir)) {
            mkdir($carouselDir, 0775, true);
            return [];
        }

        $images = [];

        foreach (new \DirectoryIterator($carouselDir) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }
            if (in_array(strtolower($file->getExtension()), self::CAROUSEL_EXTENSIONS, true)) {
                $images[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        usort($images, static fn (string $a, string $b): int => filectime($b) - filectime($a));

        return array_slice($images, 0, $limit);
    }
}
