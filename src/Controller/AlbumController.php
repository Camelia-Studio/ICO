<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Service\AlbumService;
use ICO\View\ViewRenderer;

/**
 * Contrôleur de navigation dans les albums (dossiers).
 *
 * Affiche la grille des sous-albums d'un dossier dans liste_albums/.
 */
class AlbumController
{
    private readonly string $albumsRoot;

    public function __construct(
        private readonly Config       $config,
        private readonly string       $projectRoot,
        private readonly string       $baseUrl,
        private readonly AlbumService $albumService,
        private readonly ViewRenderer $view,
    ) {
        $this->albumsRoot = $projectRoot . '/liste_albums';
    }

    // -------------------------------------------------------------------------
    // Action principale
    // -------------------------------------------------------------------------

    /**
     * Rend la vue de navigation dans les albums.
     * Redirige vers index.php si le chemin est invalide.
     */
    public function index(Request $request): void
    {
        $rawPath     = (string) $request->query('path', $this->albumsRoot);
        $currentPath = realpath($rawPath);

        if ($currentPath === false || !$this->albumService->isSecurePath($currentPath)) {
            Response::redirect('index.php')->send();
            throw new TerminateException();
        }

        $currentAlbumInfo = $this->albumService->getAlbumInfo($currentPath);

        // Construire la liste des sous-albums
        $tempAlbums = [];

        foreach (new DirectoryIterator($currentPath) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $albumPath = $item->getPathname();

            if (!$this->albumService->isSecurePath($albumPath)) {
                continue;
            }

            $info = $this->albumService->getAlbumInfo($albumPath);

            $images = $this->albumService->hasSubfolders($albumPath)
                ? $this->albumService->getImagesRecursively($albumPath)
                : $this->albumService->getLatestImages($albumPath);

            // Normaliser vers des URLs (getLatestImages retourne des chemins absolus)
            $images = array_map(function (mixed $image): array {
                if (is_string($image)) {
                    return ['url' => $this->pathToUrl($image), 'is_mature' => false];
                }

                return ['url' => $this->pathToUrl($image['path']), 'is_mature' => $image['is_mature']];
            }, $images);

            $tempAlbums[] = [
                'path'          => str_replace('\\', '/', $albumPath),
                'title'         => $info['title'],
                'description'   => $info['description'],
                'images'        => $images,
                'hasSubfolders' => $this->albumService->hasSubfolders($albumPath),
                'hasImages'     => $this->albumService->hasImages($albumPath),
                'mature_content' => $info['mature_content'],
            ];
        }

        usort($tempAlbums, static fn (array $a, array $b): int => strcasecmp($a['title'], $b['title']));

        // Chemin parent (bouton retour)
        $parentPath = realpath(dirname($currentPath)) ?: null;
        if ($parentPath !== null && !$this->albumService->isSecurePath($parentPath)) {
            $parentPath = null;
        }

        $this->view->render('pages/albums', [
            'albums'             => $tempAlbums,
            'current_album_info' => $currentAlbumInfo,
            'parent_path'        => $parentPath,
            'site_title'         => $this->config->getSiteTitle(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Convertit un chemin absolu filesystem en URL publique.
     * Ex : /var/www/ICO/liste_albums/foo/bar.jpg → https://host/liste_albums/foo/bar.jpg
     */
    private function pathToUrl(string $absolutePath): string
    {
        $relative = str_replace('\\', '/', substr($absolutePath, strlen($this->projectRoot) + 1));
        return $this->baseUrl . '/' . ltrim($relative, '/');
    }
}
