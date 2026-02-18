<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Service\AlbumService;

/**
 * Contrôleur de navigation dans les albums (dossiers).
 *
 * Affiche la grille des sous-albums d'un dossier dans liste_albums/.
 */
class AlbumController
{
    public function __construct(
        private readonly Config      $config,
        private readonly AlbumService $albumService,
    ) {}

    // -------------------------------------------------------------------------
    // Action principale
    // -------------------------------------------------------------------------

    /**
     * Prépare les données pour la vue de navigation dans les albums.
     *
     * Retourne null si le chemin est invalide (= redirection vers index).
     *
     * @return array{
     *   albums: list<array{
     *     path: string,
     *     title: string,
     *     description: string,
     *     images: array<mixed>,
     *     hasSubfolders: bool,
     *     hasImages: bool,
     *     mature_content: bool,
     *   }>,
     *   current_album_info: array{title: string, description: string, mature_content: bool, more_info_url: string},
     *   parent_path: string|null,
     *   site_title: string,
     * }|null
     */
    public function index(Request $request): ?array
    {
        $rawPath     = (string) $request->query('path', './liste_albums');
        $currentPath = realpath($rawPath);

        if ($currentPath === false || !$this->albumService->isSecurePath($currentPath)) {
            return null;
        }

        $currentAlbumInfo = $this->albumService->getAlbumInfo($currentPath);

        // Construire la liste des sous-albums
        $tempAlbums = [];

        foreach (new \DirectoryIterator($currentPath) as $item) {
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

        return [
            'albums'             => $tempAlbums,
            'current_album_info' => $currentAlbumInfo,
            'parent_path'        => $parentPath,
            'site_title'         => $this->config->getSiteTitle(),
        ];
    }
}
