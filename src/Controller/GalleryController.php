<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\FileService;
use ICO\Service\PathService;
use ICO\View\ViewRenderer;

/**
 * Contrôleur des galeries d'images.
 *
 * Mode public  : show()        — galerie publique, accès libre via chemin sécurisé
 * Mode privé   : showPrivate() — galerie privée, accès par clé de partage valide
 */
class GalleryController
{
    /** Extensions d'images autorisées */
    private const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    private readonly string $albumsRoot;

    public function __construct(
        private readonly Config             $config,
        private readonly AlbumService       $albumService,
        private readonly FileService        $fileService,
        private readonly ShareKeyRepository $shareKeyRepo,
        private readonly PathService        $pathService,
        private readonly ViewRenderer       $view,
    ) {
        $this->albumsRoot = $pathService->toAbsolute('liste_albums');
    }

    // -------------------------------------------------------------------------
    // Galerie publique (galeries.php)
    // -------------------------------------------------------------------------

    /**
     * Rend la vue galerie publique.
     * Redirige vers index.php si le chemin est invalide.
     */
    public function show(Request $request): void
    {
        $rawPath     = (string) $request->query('path', 'liste_albums');
        $currentPath = realpath($this->pathService->toAbsolute(ltrim($rawPath, '/')));

        // Fallback : si le path brut est déjà absolu (rétrocompatibilité), on le prend tel quel
        if ($currentPath === false) {
            $currentPath = realpath($rawPath);
        }

        if ($currentPath === false || !$this->albumService->isSecurePath($currentPath)) {
            Response::redirect('index.php')->send();
            throw new TerminateException();
        }

        $albumInfo = $this->albumService->getAlbumInfo($currentPath);
        $images    = $this->buildPublicImageList($currentPath);

        $parentAbsolute = realpath(dirname($currentPath)) ?: $this->albumsRoot;
        if (!$this->albumService->isSecurePath($parentAbsolute)) {
            $parentAbsolute = $this->albumsRoot;
        }

        $this->view->render('pages/gallery-public', [
            'album_info'   => $albumInfo,
            'images'       => $images,
            'header_image' => $images === [] ? null : $images[0]['url'],
            'parent_path'  => $this->pathService->toRelative($parentAbsolute),
            'breadcrumbs'  => $this->buildBreadcrumbs($currentPath),
            'site_title'   => $this->config->getSiteTitle(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Galerie privée (galeries-privees.php)
    // -------------------------------------------------------------------------

    /**
     * Rend la vue galerie privée.
     * Rend une vue d'erreur si la clé est invalide.
     */
    public function showPrivate(Request $request): void
    {
        $shareKey = (string) $request->query('key', '');

        if ($shareKey === '') {
            $this->view->render('pages/gallery-private', $this->errorData('Accès refusé', 'Aucune clé de partage fournie.', $shareKey));
            return;
        }

        $albumInfo = $this->shareKeyRepo->findValidByKey($shareKey);

        if ($albumInfo === null) {
            $this->view->render('pages/gallery-private', $this->errorData(
                'Lien de partage invalide',
                'Ce lien de partage a expiré ou n\'existe pas.',
                $shareKey,
            ));
            return;
        }

        $currentPath = $albumInfo['path'];
        $albumData   = $this->albumService->getAlbumInfo($currentPath);
        $images      = $this->buildPrivateImageList($currentPath, $shareKey);

        $this->view->render('pages/gallery-private', [
            'error_title'   => null,
            'error_message' => null,
            'album_data'    => $albumData,
            'images'        => $images,
            'header_image'  => $images === [] ? null : $images[0]['url'],
            'share_key'     => $shareKey,
            'site_title'    => $this->config->getSiteTitle(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Construit la liste d'images publiques avec tri top-first puis par date.
     *
     * @return list<array{url: string, is_top: bool, aspect_ratio: float}>
     */
    private function buildPublicImageList(string $path): array
    {
        $items = [];

        foreach (new DirectoryIterator($path) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, self::EXTENSIONS, true)) {
                continue;
            }

            $url   = $this->pathService->toUrl($file->getPathname());
            $isTop = str_contains(basename($url), '--top--');
            $size  = $this->fileService->getSecureImageSize($file->getPathname());

            $items[] = [
                'url'          => $url,
                'is_top'       => $isTop,
                'aspect_ratio' => $size ? $size['width'] / $size['height'] : 1.0,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            if ($a['is_top'] && !$b['is_top']) {
                return -1;
            }

            if (!$a['is_top'] && $b['is_top']) {
                return 1;
            }

            return 0;
        });

        return $items;
    }

    /**
     * Construit la liste d'images privées (proxy images.php + clé).
     *
     * @return list<array{url: string, is_top: bool, aspect_ratio: float}>
     */
    private function buildPrivateImageList(string $path, string $shareKey): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $items = [];

        foreach (new DirectoryIterator($path) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, self::EXTENSIONS, true)) {
                continue;
            }

            if (!file_exists($file->getPathname())) {
                continue;
            }

            $relativePath = $this->pathService->toRelative($file->getPathname());
            // L'URL proxy encode le path une seule fois
            $baseUrl  = $this->pathService->getBaseUrl();
            $proxyUrl = $baseUrl . '/images.php?path=' . rawurlencode($relativePath) . '&key=' . rawurlencode($shareKey);
            // L'URL de partage encode l'URL proxy une seule fois (rawurlencode pour ne pas ré-encoder les %)
            $shareUrl = $baseUrl . '/partage.php?image=' . rawurlencode($proxyUrl);
            $isTop = str_contains($file->getFilename(), '--top--');
            $size  = $this->fileService->getSecureImageSize($file->getPathname());

            $items[] = [
                'url'          => $proxyUrl,
                'share_url'    => $shareUrl,
                'is_top'       => $isTop,
                'aspect_ratio' => $size ? $size['width'] / $size['height'] : 1.0,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            if ($a['is_top'] && !$b['is_top']) {
                return -1;
            }

            if (!$a['is_top'] && $b['is_top']) {
                return 1;
            }

            return 0;
        });

        return $items;
    }

    /**
     * Construit le fil d'Ariane depuis la racine des albums jusqu'au chemin courant.
     *
     * @return list<array{label: string, url: string|null}>
     */
    private function buildBreadcrumbs(string $currentAbsolutePath): array
    {
        $relativePart = ltrim(substr($currentAbsolutePath, strlen($this->albumsRoot)), DIRECTORY_SEPARATOR);

        if ($relativePart === '') {
            return [['label' => 'Accueil', 'url' => null]];
        }

        $breadcrumbs = [['label' => 'Accueil', 'url' => 'index.php']];
        $segments    = explode(DIRECTORY_SEPARATOR, $relativePart);
        $accum       = $this->albumsRoot;

        foreach ($segments as $i => $segment) {
            $accum  .= DIRECTORY_SEPARATOR . $segment;
            $info    = $this->albumService->getAlbumInfo($accum);
            $isLast  = $i === count($segments) - 1;
            $relPath = $this->pathService->toRelative($accum);

            $breadcrumbs[] = [
                'label' => $info['title'],
                'url'   => $isLast ? null : 'albums.php?path=' . urlencode($relPath),
            ];
        }

        return $breadcrumbs;
    }

    /**
     * @return array{
     *   error_title: string,
     *   error_message: string,
     *   album_data: null,
     *   images: list<never>,
     *   header_image: null,
     *   share_key: string,
     *   site_title: string,
     * }
     */
    private function errorData(string $title, string $message, string $shareKey): array
    {
        return [
            'error_title'   => $title,
            'error_message' => $message,
            'album_data'    => null,
            'images'        => [],
            'header_image'  => null,
            'share_key'     => $shareKey,
            'site_title'    => $this->config->getSiteTitle(),
        ];
    }
}
