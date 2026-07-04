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
use ICO\Service\AuthService;
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
        private readonly AuthService        $auth,
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

        $albumInfo    = $this->albumService->getAlbumInfo($currentPath);
        $shareOptions = $this->albumService->getEffectiveShareOptions($albumInfo, $this->config->getDefaultShareOptions());
        $images       = $this->buildPublicImageList($currentPath);

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
            'allow_download'    => $shareOptions['download'],
            'allow_share'       => $shareOptions['share'],
            'allow_source'      => $shareOptions['source'],
            'allow_rss'         => $shareOptions['rss'],
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
        $adminPath = (string) $request->query('path', '');
        $shareKey = (string) $request->query('key', '');

        if ($adminPath !== '' && $shareKey === '') {
            $this->showPrivateForAdmin($adminPath);
            return;
        }

        if ($shareKey === '') {
            $this->view->render('pages/gallery-private', $this->errorData('Accès refusé', 'Aucune clé de partage fournie.', $shareKey));
            return;
        }

        $shareKeyData = $this->shareKeyRepo->findValidByKey($shareKey);

        if ($shareKeyData === null) {
            $this->view->render('pages/gallery-private', $this->errorData(
                'Lien de partage invalide',
                'Ce lien de partage a expiré ou n\'existe pas.',
                $shareKey,
            ));
            return;
        }

        $sharedRoot = (string) $shareKeyData['path'];
        $currentPath = $sharedRoot;
        if ($adminPath !== '') {
            $requestedPath = realpath($this->pathService->toAbsolute(ltrim($adminPath, '/')));
            if ($requestedPath === false || $this->shareKeyRepo->findValidForPath($shareKey, $requestedPath) === null) {
                $this->view->render('pages/gallery-private', $this->errorData(
                    'Accès refusé',
                    'Cette clé de partage ne donne pas accès à ce dossier.',
                    $shareKey,
                ));
                return;
            }

            $currentPath = $requestedPath;
        }

        if ($this->albumService->hasSubfolders($currentPath)) {
            $this->renderPrivateNavigation($currentPath, $sharedRoot, $shareKey);
            return;
        }

        $this->renderPrivateGallery($currentPath, $shareKey, $shareKeyData);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Rend une galerie privée depuis l'admin, sans créer de clé de partage.
     */
    private function showPrivateForAdmin(string $rawPath): void
    {
        if (!$this->auth->isLoggedIn()) {
            $this->view->render('pages/gallery-private', $this->errorData(
                'Accès refusé',
                'Vous devez être connecté à l\'administration pour visualiser cette galerie privée.',
                '',
            ));
            return;
        }

        $currentPath = realpath($this->pathService->toAbsolute(ltrim($rawPath, '/')));
        if ($currentPath === false || !$this->albumService->isSecurePrivatePath($currentPath)) {
            $this->view->render('pages/gallery-private', $this->errorData(
                'Galerie privée introuvable',
                'Ce dossier privé n\'existe pas ou n\'est pas accessible.',
                '',
            ));
            return;
        }

        if ($this->albumService->hasSubfolders($currentPath)) {
            $privateRoot = realpath($this->pathService->toAbsolute('liste_albums_prives')) ?: $currentPath;
            $this->renderPrivateNavigation($currentPath, $privateRoot, null);
            return;
        }

        $this->renderPrivateGallery($currentPath, '', []);
    }

    /**
     * @param array<string, mixed> $shareKeyData
     */
    private function renderPrivateGallery(string $currentPath, string $shareKey, array $shareKeyData): void
    {
        $albumData    = $this->albumService->getAlbumInfo($currentPath);
        $shareOptions = $this->albumService->getEffectiveShareOptions($albumData, $this->config->getDefaultShareOptions());
        $images       = $this->buildPrivateImageList($currentPath, $shareKey);

        $rawOptions = $shareKeyData['options'] ?? '{}';
        $decoded    = json_decode((string) $rawOptions, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $shareOptions = array_merge($shareOptions, $decoded);

        $this->view->render('pages/gallery-private', [
            'error_title'        => null,
            'error_message'      => null,
            'album_data'         => $albumData,
            'images'             => $images,
            'header_image'       => $this->firstImageUrl($images),
            'share_key'          => $shareKey,
            'site_title'         => $this->config->getSiteTitle(),
            'slideshow_interval' => $this->config->getSlideshowInterval(),
            'allow_download'     => (bool) ($shareOptions['download'] ?? true),
            'allow_share'        => (bool) ($shareOptions['share']    ?? true),
            'allow_source'       => (bool) ($shareOptions['source']   ?? true),
        ]);
    }

    /**
     * Rend la navigation des sous-dossiers privés accessibles par une clé ou depuis l'admin.
     */
    private function renderPrivateNavigation(string $currentPath, string $sharedRoot, ?string $shareKey): void
    {
        $this->view->render('pages/albums', [
            'albums'             => $this->buildPrivateAlbumList($currentPath, $shareKey),
            'current_album_info' => $this->albumService->getAlbumInfo($currentPath),
            'parent_path'        => null,
            'breadcrumbs'        => $this->buildPrivateBreadcrumbs($currentPath, $sharedRoot, $shareKey),
            'site_title'         => $this->config->getSiteTitle(),
        ]);
    }

    /**
     * @return list<array{path: string, url: string, title: string, description: string, images: list<array{url: string, is_mature: bool}>, hasSubfolders: bool, hasImages: bool, mature_content: bool}>
     */
    private function buildPrivateAlbumList(string $currentPath, ?string $shareKey): array
    {
        if (!is_dir($currentPath)) {
            return [];
        }

        $albums = [];
        $baseUrl = $this->pathService->getBaseUrl();

        foreach (new DirectoryIterator($currentPath) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $albumPath = $item->getPathname();
            if (!$this->albumService->isSecurePrivatePath($albumPath)) {
                continue;
            }

            $info          = $this->albumService->getAlbumInfo($albumPath);
            $hasSubfolders = $this->albumService->hasSubfolders($albumPath);
            $images        = $hasSubfolders
                ? $this->albumService->getImagesRecursively($albumPath)
                : $this->albumService->getLatestImages($albumPath);

            $previews = array_map(function (mixed $image) use ($baseUrl, $shareKey): array {
                if (is_string($image)) {
                    $relativePath = $this->pathService->toRelative($image);

                    return [
                        'url'       => $this->privateMediaUrl($baseUrl, 'images.php', $relativePath, $shareKey ?? ''),
                        'is_mature' => false,
                    ];
                }

                $relativePath = $this->pathService->toRelative($image['path']);

                return [
                    'url'       => $this->privateMediaUrl($baseUrl, 'images.php', $relativePath, $shareKey ?? ''),
                    'is_mature' => $image['is_mature'],
                ];
            }, $images);

            $relativePath = $this->pathService->toRelative($albumPath);
            $albums[] = [
                'path'           => $relativePath,
                'url'            => $this->privateNavigationUrl($shareKey, $relativePath),
                'title'          => $info['title'],
                'description'    => $info['description'],
                'images'         => $previews,
                'hasSubfolders'  => $hasSubfolders,
                'hasImages'      => $this->albumService->hasImages($albumPath),
                'mature_content' => $info['mature_content'],
            ];
        }

        usort($albums, static fn (array $a, array $b): int => strcasecmp($a['title'], $b['title']));

        return $albums;
    }

    /**
     * Construit la liste de médias publics (images + vidéos) avec tri top-first.
     *
     * @return list<array{url: string, filename: string, is_top: bool, aspect_ratio: float, type: string, mime: string|null}>
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
                    'filename'     => $file->getFilename(),
                    'is_top'       => $isTop,
                    'aspect_ratio' => $size ? $size['width'] / $size['height'] : 1.0,
                    'type'         => 'image',
                    'mime'         => null,
                ];
            } elseif (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
                $items[] = [
                    'url'          => $this->pathService->toUrl($file->getPathname()),
                    'filename'     => $file->getFilename(),
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
     * @return list<array{url: string, share_url: string|null, filename: string, is_top: bool, aspect_ratio: float, type: string, mime: string|null}>
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
                $proxyUrl = $this->privateMediaUrl($baseUrl, 'images.php', $relativePath, $shareKey);
                // L'URL de partage encode l'URL proxy une seule fois (rawurlencode pour ne pas ré-encoder les %)
                $shareUrl = $baseUrl . '/partage.php?image=' . rawurlencode($proxyUrl);
                $size     = $this->fileService->getSecureImageSize($file->getPathname());

                $items[] = [
                    'url'          => $proxyUrl,
                    'share_url'    => $shareUrl,
                    'filename'     => $file->getFilename(),
                    'is_top'       => $isTop,
                    'aspect_ratio' => $size ? $size['width'] / $size['height'] : 1.0,
                    'type'         => 'image',
                    'mime'         => null,
                ];
            } elseif (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
                $relativePath = $this->pathService->toRelative($file->getPathname());
                $proxyUrl     = $this->privateMediaUrl($baseUrl, 'videos.php', $relativePath, $shareKey);
                $shareUrl     = $baseUrl . '/partage.php?image=' . rawurlencode($proxyUrl);

                $items[] = [
                    'url'          => $proxyUrl,
                    'share_url'    => $shareUrl,
                    'filename'     => $file->getFilename(),
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

    private function privateMediaUrl(string $baseUrl, string $endpoint, string $relativePath, string $shareKey): string
    {
        $url = $baseUrl . '/' . $endpoint . '?path=' . rawurlencode($relativePath);

        if ($shareKey !== '') {
            $url .= '&key=' . rawurlencode($shareKey);
        }

        return $url;
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

    private function privateNavigationUrl(?string $shareKey, string $relativePath): string
    {
        if ($shareKey === null) {
            return 'galeries-privees.php?path=' . urlencode($relativePath);
        }

        return 'galeries-privees.php?key=' . urlencode($shareKey) . '&path=' . urlencode($relativePath);
    }

    /**
     * Construit le fil d'Ariane à l'intérieur du dossier privé partagé.
     *
     * @return list<array{label: string, url: string|null}>
     */
    private function buildPrivateBreadcrumbs(string $currentPath, string $sharedRoot, ?string $shareKey): array
    {
        $sharedRootReal = realpath($sharedRoot) ?: $sharedRoot;
        $currentReal    = realpath($currentPath) ?: $currentPath;
        $privateRootReal = realpath($this->pathService->toAbsolute('liste_albums_prives')) ?: $sharedRootReal;
        $privateRootInfo = $this->albumService->getAlbumInfo($privateRootReal);
        $rootInfo       = $this->albumService->getAlbumInfo($sharedRootReal);

        if ($currentReal === $sharedRootReal) {
            if ($sharedRootReal !== $privateRootReal) {
                return [
                    [
                        'label' => $privateRootInfo['title'],
                        'url'   => $shareKey === null
                            ? $this->privateNavigationUrl(null, $this->pathService->toRelative($privateRootReal))
                            : 'galeries-privees.php?key=' . urlencode($shareKey),
                    ],
                    ['label' => $rootInfo['title'], 'url' => null],
                ];
            }

            return [['label' => $rootInfo['title'], 'url' => null]];
        }

        $breadcrumbs = [];
        if ($sharedRootReal !== $privateRootReal) {
            $breadcrumbs[] = [
                'label' => $privateRootInfo['title'],
                'url'   => $shareKey === null
                    ? $this->privateNavigationUrl(null, $this->pathService->toRelative($privateRootReal))
                    : 'galeries-privees.php?key=' . urlencode($shareKey),
            ];
        }

        $breadcrumbs[] = [
            'label' => $rootInfo['title'],
            'url'   => $shareKey === null
                ? $this->privateNavigationUrl(null, $this->pathService->toRelative($sharedRootReal))
                : 'galeries-privees.php?key=' . urlencode($shareKey),
        ];

        $relativePart = ltrim(substr($currentReal, strlen($sharedRootReal)), DIRECTORY_SEPARATOR);
        $segments     = $relativePart !== '' ? explode(DIRECTORY_SEPARATOR, $relativePart) : [];
        $accum        = $sharedRootReal;

        foreach ($segments as $i => $segment) {
            $accum .= DIRECTORY_SEPARATOR . $segment;
            $info = $this->albumService->getAlbumInfo($accum);
            $isLast = $i === count($segments) - 1;
            $relPath = $this->pathService->toRelative($accum);

            $breadcrumbs[] = [
                'label' => $info['title'],
                'url'   => $isLast ? null : $this->privateNavigationUrl($shareKey, $relPath),
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
