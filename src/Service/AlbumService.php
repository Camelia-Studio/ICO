<?php

declare(strict_types=1);

namespace ICO\Service;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Gère l'accès aux albums (dossiers d'images).
 */
class AlbumService
{
    /**
     * @param string[] $allowedExtensions
     * @param string[] $videoExtensions
     */
    public function __construct(
        private readonly string $albumsRoot,
        private readonly string $privateRoot,
        private readonly string $carouselRoot = '',
        private readonly array  $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'],
        private readonly array  $videoExtensions = ['mp4', 'webm'],
    ) {
    }

    // -------------------------------------------------------------------------
    // Lecture des métadonnées
    // -------------------------------------------------------------------------

    /**
     * Lit le fichier infos.txt d'un album et retourne ses métadonnées.
     *
     * Format infos.txt (une valeur par ligne) :
     *   0 : titre
     *   1 : description
     *   2 : 18+ | 18-
     *   3 : more_info_url
     *   4 : zip_download (1 = activé, 0 = désactivé)
     *
     * @return array{title: string, description: string, mature_content: bool, more_info_url: string, zip_download: bool}
     */
    public function getAlbumInfo(string $albumPath): array
    {
        $info = [
            'title'          => basename($albumPath),
            'description'    => '',
            'mature_content' => false,
            'more_info_url'  => '',
            'zip_download'   => false,
        ];

        $infoFile = rtrim($albumPath, '/') . '/infos.txt';

        if (file_exists($infoFile)) {
            $lines = explode("\n", file_get_contents($infoFile));
            if ($lines[0] !== '') {
                $info['title'] = trim($lines[0]);
            }

            if (isset($lines[1])) {
                $info['description'] = trim($lines[1]);
            }

            if (isset($lines[2])) {
                $info['mature_content'] = trim($lines[2]) === '18+';
            }

            if (isset($lines[3])) {
                $info['more_info_url'] = trim($lines[3]);
            }

            if (isset($lines[4])) {
                $info['zip_download'] = trim($lines[4]) === '1';
            }
        }

        return $info;
    }

    // -------------------------------------------------------------------------
    // Listage d'images
    // -------------------------------------------------------------------------

    /**
     * Retourne les $limit images les plus récentes (par ctime) d'un dossier,
     * sous forme de chemins absolus.
     *
     * @return string[]
     */
    public function getLatestImages(string $albumPath, int $limit = 4): array
    {
        if (!is_dir($albumPath)) {
            return [];
        }

        $images = [];

        foreach (new DirectoryIterator($albumPath) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            if ($this->isAllowedExtension($file->getExtension())) {
                $images[] = $file->getPathname();
            }
        }

        usort($images, static fn (string $a, string $b): int => filectime($b) - filectime($a));

        return array_slice($images, 0, $limit);
    }

    /**
     * Retourne les $limit images les plus récentes de façon récursive,
     * avec le flag mature_content du dossier parent.
     *
     * @return array<int, array{path: string, is_mature: bool}>
     */
    public function getImagesRecursively(string $albumPath, int $limit = 4): array
    {
        if (!is_dir($albumPath)) {
            return [];
        }

        $images = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($albumPath),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }

            if ($this->isAllowedExtension($file->getExtension())) {
                $parentInfo = $this->getAlbumInfo(dirname($file->getPathname()));
                $images[]   = [
                    'path'      => $file->getPathname(),
                    'is_mature' => $parentInfo['mature_content'],
                ];
            }
        }

        usort(
            $images,
            static fn (array $a, array $b): int => filectime($b['path']) - filectime($a['path'])
        );

        return array_slice($images, 0, $limit);
    }

    // -------------------------------------------------------------------------
    // Vérifications de structure
    // -------------------------------------------------------------------------

    /**
     * Retourne true si le dossier contient au moins un sous-dossier.
     */
    public function hasSubfolders(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        foreach (new DirectoryIterator($path) as $item) {
            if (!$item->isDot() && $item->isDir()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne le nombre de médias autorisés (images + vidéos) dans le dossier (non récursif).
     */
    public function countImages(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $count = 0;
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isFile()) {
                continue;
            }

            if ($this->isAnyMediaExtension($item->getExtension())) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Retourne true si le dossier contient au moins une image ou une vidéo autorisée.
     */
    public function hasImages(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isFile()) {
                continue;
            }

            if ($this->isAnyMediaExtension($item->getExtension())) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Listage des albums
    // -------------------------------------------------------------------------

    /**
     * Retourne tous les albums « feuilles » (avec images, sans sous-dossiers)
     * de façon récursive sous $rootPath.
     *
     * @return array<int, array{title: string, rel_path: string, abs_path: string, more_info_url: string}>
     */
    public function getAllLeafAlbums(string $rootPath, string $projectRoot): array
    {
        $albums = [];
        $this->collectLeafAlbums($rootPath, $projectRoot, $albums);
        usort($albums, static fn (array $a, array $b): int => strcasecmp((string) $a['title'], (string) $b['title']));

        return $albums;
    }

    private function collectLeafAlbums(string $path, string $projectRoot, array &$albums): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $fullPath = $item->getPathname();

            if ($this->hasSubfolders($fullPath)) {
                $this->collectLeafAlbums($fullPath, $projectRoot, $albums);
            } elseif ($this->hasImages($fullPath)) {
                $info      = $this->getAlbumInfo($fullPath);
                $albums[]  = [
                    'title'         => $info['title'],
                    'rel_path'      => ltrim(substr($fullPath, strlen($projectRoot)), DIRECTORY_SEPARATOR),
                    'abs_path'      => $fullPath,
                    'more_info_url' => $info['more_info_url'],
                ];
            }
        }
    }

    // -------------------------------------------------------------------------
    // Sécurité des chemins
    // -------------------------------------------------------------------------

    /**
     * Vérifie que $path est sous le dossier liste_albums ou img_carrousel.
     */
    public function isSecurePath(string $path): bool
    {
        $realPath = realpath($path);

        if ($realPath === false) {
            return false;
        }

        $albumsReal = realpath($this->albumsRoot);
        if ($albumsReal !== false && str_starts_with($realPath, $albumsReal)) {
            return true;
        }

        if ($this->carouselRoot !== '') {
            $carouselReal = realpath($this->carouselRoot);
            if ($carouselReal !== false && str_starts_with($realPath, $carouselReal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie que $path est sous le dossier liste_albums_prives.
     */
    public function isSecurePrivatePath(string $path): bool
    {
        $realPath    = realpath($path);
        $privateReal = realpath($this->privateRoot);

        return $realPath !== false
            && $privateReal !== false
            && str_starts_with($realPath, $privateReal);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function isAllowedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), $this->allowedExtensions, true);
    }

    private function isVideoExtension(string $extension): bool
    {
        return in_array(strtolower($extension), $this->videoExtensions, true);
    }

    private function isAnyMediaExtension(string $extension): bool
    {
        return $this->isAllowedExtension($extension) || $this->isVideoExtension($extension);
    }
}
