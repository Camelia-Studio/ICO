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

    /** Extensions vidéo autorisées */
    private const array VIDEO_EXTENSIONS = ['mp4', 'webm'];

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

        $rssUrl = $this->pathService->getBaseUrl() . '/rss.php?path=' . urlencode($rawPath);
        $zipUrl = $this->pathService->getBaseUrl() . '/zip.php?path=' . urlencode($rawPath);

        $this->view->render('pages/gallery-public', [
            'album_info'        => $albumInfo,
            'images'            => $images,
            'header_image'      => $this->firstImageUrl($images),
            'parent_path'       => $this->pathService->toRelative($parentAbsolute),
            'breadcrumbs'       => $this->buildBreadcrumbs($currentPath),
            'site_title'        => $this->config->getSiteTitle(),
            'rss_url'           => $rssUrl,
            'zip_url'           => $zipUrl,
            'slideshow_interval' => $this->config->getSlideshowInterval(),
            'allow_download'    => (bool) $albumInfo['share_options']['download'],
            'allow_share'       => (bool) $albumInfo['share_options']['share'],
            'allow_source'      => (bool) $albumInfo['share_options']['source'],
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

        $rawOptions = $albumInfo['options'] ?? '{}';
        $decoded    = json_decode((string) $rawOptions, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $this->view->render('pages/gallery-private', [
            'error_title'        => null,
            'error_message'      => null,
            'album_data'         => $albumData,
            'images'             => $images,
            'header_image'       => $this->firstImageUrl($images),
            'share_key'          => $shareKey,
            'site_title'         => $this->config->getSiteTitle(),
            'slideshow_interval' => $this->config->getSlideshowInterval(),
            'allow_download'     => (bool) ($decoded['download'] ?? true),
            'allow_share'        => (bool) ($decoded['share']    ?? true),
            'allow_source'       => (bool) ($decoded['source']   ?? true),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Construit la liste de médias publics (images + vidéos) avec tri top-first.
     *
     * @return list<array{url: string, is_top: bool, aspect_ratio: float, type: string, mime: string|null}>
     */
    private function buildPublicImageList(string $path): array
    {
        $items = [];

        foreach (new DirectoryIterator($path) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            $ext   = strtolower($file->getExtension());
            $isTop = str_contains($file->getFilename(), '--top--');

            if (in_array($ext, self::EXTENSIONS, true)) {
                $url  = $this->pathService->toUrl($file->getPathname());
                $size = $this->fileService->getSecureImageSize($file->getPathname());

                $items[] = [
                    'url'          => $url,
                    'is_top'       => $isTop,
                    'aspect_ratio' => $size ? $size['width'] / $size['height'] : 1.0,
                    'type'         => 'image',
                    'mime'         => null,
                ];
            } elseif (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
                $items[] = [
                    'url'          => $this->pathService->toUrl($file->getPathname()),
                    'is_top'       => $isTop,
                    'aspect_ratio' => 16 / 9,
                    'type'         => 'video',
                    'mime'         => $this->videoMime($ext),
                ];
            }
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
     * Construit la liste de médias privés (proxy images.php / videos.php + clé).
     *
     * @return list<array{url: string, share_url: string|null, is_top: bool, aspect_ratio: float, type: string, mime: string|null}>
     */
    private function buildPrivateImageList(string $path, string $shareKey): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $items   = [];
        $baseUrl = $this->pathService->getBaseUrl();

        foreach (new DirectoryIterator($path) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            if (!file_exists($file->getPathname())) {
                continue;
            }

            $ext   = strtolower($file->getExtension());
            $isTop = str_contains($file->getFilename(), '--top--');

            if (in_array($ext, self::EXTENSIONS, true)) {
                $relativePath = $this->pathService->toRelative($file->getPathname());
                // L'URL proxy encode le path une seule fois
                $proxyUrl = $baseUrl . '/images.php?path=' . rawurlencode($relativePath) . '&key=' . rawurlencode($shareKey);
                // L'URL de partage encode l'URL proxy une seule fois (rawurlencode pour ne pas ré-encoder les %)
                $shareUrl = $baseUrl . '/partage.php?image=' . rawurlencode($proxyUrl);
                $size     = $this->fileService->getSecureImageSize($file->getPathname());

                $items[] = [
                    'url'          => $proxyUrl,
                    'share_url'    => $shareUrl,
                    'is_top'       => $isTop,
                    'aspect_ratio' => $size ? $size['width'] / $size['height'] : 1.0,
                    'type'         => 'image',
                    'mime'         => null,
                ];
            } elseif (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
                $relativePath = $this->pathService->toRelative($file->getPathname());
                $proxyUrl     = $baseUrl . '/videos.php?path=' . rawurlencode($relativePath) . '&key=' . rawurlencode($shareKey);
                $shareUrl     = $baseUrl . '/partage.php?image=' . rawurlencode($proxyUrl);

                $items[] = [
                    'url'          => $proxyUrl,
                    'share_url'    => $shareUrl,
                    'is_top'       => $isTop,
                    'aspect_ratio' => 16 / 9,
                    'type'         => 'video',
                    'mime'         => $this->videoMime($ext),
                ];
            }
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
     * Retourne l'URL de la première image (hors vidéo) pour l'en-tête de galerie.
     *
     * @param list<array{url: string, type: string}> $items
     */
    private function firstImageUrl(array $items): ?string
    {
        foreach ($items as $item) {
            if ($item['type'] === 'image') {
                return $item['url'];
            }
        }

        return null;
    }

    private function videoMime(string $ext): string
    {
        return match ($ext) {
            'webm'  => 'video/webm',
            default => 'video/mp4',
        };
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

        $breadcrumbs = [['label' => 'Accueil', 'url' => 'albums.php']];
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
