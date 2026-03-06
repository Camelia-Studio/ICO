<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Config\Config;
use ICO\Http\Request;
use ICO\View\ViewRenderer;

/**
 * Contrôleur de la page d'accueil.
 *
 * Prépare les données pour le carousel (images récentes) et l'overlay titre/description.
 */
class HomeController
{
    /** Extensions acceptées pour le carousel */
    private const array CAROUSEL_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    public function __construct(
        private readonly Config       $config,
        private readonly string       $projectRoot,
        private readonly ViewRenderer $view,
    ) {
    }

    // -------------------------------------------------------------------------
    // Action principale
    // -------------------------------------------------------------------------

    /**
     * Rend la vue d'accueil.
     */
    public function index(Request $request): void
    {
        $this->view->render('pages/home', [
            'carousel_images'  => $this->getCarouselImages(),
            'site_title'       => $this->config->getSiteTitle(),
            'site_description' => $this->config->getSiteDescription(),
            'version'          => $this->config->getVersion(),
        ]);
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
            mkdir($carouselDir, 0o775, true);
            return [];
        }

        $images = [];

        foreach (new DirectoryIterator($carouselDir) as $file) {
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
