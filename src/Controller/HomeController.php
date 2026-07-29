<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Repository\CarouselPositionRepository;
use ICO\Service\PathService;
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
        private readonly Config                     $config,
        private readonly PathService                $pathService,
        private readonly ViewRenderer                $view,
        private readonly CarouselPositionRepository  $carouselPositionRepo,
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
        $carouselDir = $this->pathService->toAbsolute('img_carrousel');

        if (!is_dir($carouselDir)) {
            mkdir($carouselDir, 0o775, true);
            return [];
        }

        $positions = $this->carouselPositionRepo->findAll();
        $images    = [];

        foreach (new DirectoryIterator($carouselDir) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            if (in_array(strtolower($file->getExtension()), self::CAROUSEL_EXTENSIONS, true)) {
                $images[] = [
                    'url'      => $this->pathService->toUrl($file->getPathname()),
                    'ctime'    => $file->getCTime(),
                    'isTop'    => str_contains($file->getFilename(), '--top--'),
                    'position' => $positions[$file->getFilename()] ?? null,
                ];
            }
        }

        usort($images, static fn (array $a, array $b): int =>
            $b['isTop'] <=> $a['isTop']
                ?: ($b['position'] !== null) <=> ($a['position'] !== null)
                ?: ($a['position'] !== null ? $a['position'] <=> $b['position'] : $b['ctime'] - $a['ctime']));

        return array_slice(array_column($images, 'url'), 0, $limit);
    }
}
