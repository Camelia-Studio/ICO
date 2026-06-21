<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Config\Config;
use ICO\Http\TerminateException;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\InfoPageRepository;
use ICO\Repository\LogRepository;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\Service\FileService;
use ICO\View\ViewRenderer;

/**
 * Gère l'arborescence des albums publics et privés (avec upload, suppression, déplacement).
 */
class TreeController
{
    private readonly string $albumsRoot;

    private readonly string $albumsPrivateRoot;

    private readonly string $carouselRoot;

    public function __construct(
        private readonly Config                     $config,
        private readonly string                     $projectRoot,
        private readonly AuthService                $auth,
        private readonly AlbumService               $albumService,
        private readonly FileService                $fileService,
        private readonly LogRepository              $logRepo,
        private readonly AlbumIdentifierRepository  $albumIdentRepo,
        private readonly ShareKeyRepository         $shareKeyRepo,
        private readonly ViewRenderer               $view,
        private readonly InfoPageRepository         $infoPageRepo,
    ) {
        $this->albumsRoot        = $projectRoot . '/liste_albums';
        $this->albumsPrivateRoot = $projectRoot . '/liste_albums_prives';
        $this->carouselRoot      = $projectRoot . '/img_carrousel';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function relPath(string $absPath): string
    {
        return substr($absPath, strlen($this->projectRoot) + 1);
    }

    private function absPath(string $relPath): string
    {
        return realpath($this->projectRoot . '/' . ltrim($relPath, '/')) ?: '';
    }

    // -------------------------------------------------------------------------
    // arbre.php — albums publics
    // -------------------------------------------------------------------------

    public function handlePublic(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePublicPost();
            header('Location: arbre.php');
            throw new TerminateException();
        }

        $rawPath     = $_GET['path'] ?? '';
        $currentPath = ($rawPath !== '' ? $this->absPath($rawPath) : '') ?: (realpath($this->albumsRoot) ?: '');
        if (!$currentPath || !$this->albumService->isSecurePath($currentPath)) {
            header('Location: arbre.php');
            throw new TerminateException();
        }

        $siteTitle      = $this->config->getSiteTitle();
        $tree           = $this->generatePublicTree($this->albumsRoot, $currentPath);
        $allFoldersJson = json_encode($this->collectFoldersList($this->albumsRoot), JSON_HEX_TAG) ?: '[]';
        $this->renderPublic($siteTitle, $tree, $allFoldersJson);
    }

    private function handlePublicPost(): void
    {
        $action        = $_POST['action']      ?? '';
        $path          = $this->absPath($_POST['path'] ?? '');
        $newName       = $_POST['new_name']    ?? '';
        $description   = $_POST['description'] ?? '';
        $matureContent = isset($_POST['mature_content']) ? '18+' : '18-';

        switch ($action) {
            case 'create_folder':
                if ($path && $newName) {
                    $newPath = $path . '/' . $this->fileService->sanitizeFilename($newName);
                    if (!file_exists($newPath)) {
                        $moreInfoUrl  = $_POST['more_info_url'] ?? '';
                        mkdir($newPath, 0o775, true);
                        $shareOptions = json_encode([
                            'download' => isset($_POST['opt_share_download']),
                            'source'   => isset($_POST['opt_share_source']),
                            'share'    => isset($_POST['opt_share_share']),
                        ]);
                        $infoContent = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl . "\n0\n" . $shareOptions;
                        file_put_contents($newPath . '/infos.txt', $infoContent);
                        $_SESSION['success_message'] = 'Dossier « ' . $newName . ' » créé avec succès.';
                    } else {
                        $_SESSION['error_message'] = 'Ce dossier existe déjà.';
                    }

                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'CREATE_FOLDER',
                        'Création du dossier : ' . $newName,
                        $newPath,
                    );
                }

                break;

            case 'edit_folder':
                if ($path && $this->albumService->isSecurePath($path)) {
                    $moreInfoUrl  = $_POST['more_info_url'] ?? '';
                    $zipDownload  = isset($_POST['zip_download']) ? '1' : '0';
                    $shareOptions = json_encode([
                        'download' => isset($_POST['opt_share_download']),
                        'source'   => isset($_POST['opt_share_source']),
                        'share'    => isset($_POST['opt_share_share']),
                    ]);
                    $infoContent  = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl . "\n" . $zipDownload . "\n" . $shareOptions;
                    if (file_put_contents($path . '/infos.txt', $infoContent) !== false) {
                        $_SESSION['success_message'] = 'Dossier « ' . $newName . ' » modifié avec succès.';
                    } else {
                        $_SESSION['error_message'] = 'Erreur lors de la modification du dossier.';
                    }

                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'EDIT_FOLDER',
                        'Modification du dossier : ' . $newName,
                        $path,
                    );
                }

                break;

            case 'delete_folder':
                if ($path && $this->albumService->isSecurePath($path) && $path !== $this->albumsRoot) {
                    $folderTitle = $this->albumService->getAlbumInfo($path)['title'];
                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'DELETE_FOLDER',
                        'Suppression du dossier',
                        $path,
                    );
                    $this->fileService->deleteDirectoryRecursively($path);
                    $_SESSION['success_message'] = 'Dossier « ' . $folderTitle . ' » supprimé avec succès.';
                }

                break;

            case 'move_folder':
                $destPath    = $this->absPath($_POST['dest_path'] ?? '');
                $albumsReal  = realpath($this->albumsRoot) ?: '';
                if (
                    !$path || !$destPath || !$albumsReal ||
                    !str_starts_with($path, $albumsReal . '/') ||
                    !str_starts_with($destPath, $albumsReal)
                ) {
                    $_SESSION['error_message'] = 'Chemin invalide.';
                    break;
                }

                if ($destPath === $path || str_starts_with($destPath, $path . '/')) {
                    $_SESSION['error_message'] = 'La destination ne peut pas être à l\'intérieur du dossier source.';
                    break;
                }

                if (dirname($path) === $destPath) {
                    $_SESSION['error_message'] = 'Le dossier est déjà dans ce dossier.';
                    break;
                }

                $newPath = $destPath . '/' . basename($path);
                if (file_exists($newPath)) {
                    $_SESSION['error_message'] = 'Un dossier avec ce nom existe déjà dans la destination.';
                    break;
                }

                $folderTitle = $this->albumService->getAlbumInfo($path)['title'];
                $destTitle   = $this->albumService->getAlbumInfo($destPath)['title'];
                if (rename($path, $newPath)) {
                    $this->albumIdentRepo->updatePathsAfterMove($path, $newPath);
                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'MOVE_FOLDER',
                        'Déplacement de « ' . $folderTitle . ' » vers « ' . $destTitle . ' »',
                        $newPath,
                    );
                    $_SESSION['success_message'] = 'Dossier « ' . $folderTitle . ' » déplacé vers « ' . $destTitle . ' » avec succès.';
                } else {
                    $_SESSION['error_message'] = 'Erreur lors du déplacement du dossier.';
                }

                break;
        }
    }

    // -------------------------------------------------------------------------
    // arbre-prive.php — albums privés
    // -------------------------------------------------------------------------

    public function handlePrivate(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        // Créer le dossier racine privé si nécessaire
        if (!file_exists($this->albumsPrivateRoot)) {
            mkdir($this->albumsPrivateRoot, 0o775, true);
            file_put_contents($this->albumsPrivateRoot . '/infos.txt', "Albums privés\nVos albums photos privés\n18-\n");
        }

        // Action generate_link (prioritaire)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_link') {
            $this->handleGenerateLink();
            header('Location: arbre-prive.php');
            throw new TerminateException();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePrivatePost();
            header('Location: arbre-prive.php');
            throw new TerminateException();
        }

        $rawPath     = $_GET['path'] ?? '';
        $currentPath = ($rawPath !== '' ? $this->absPath($rawPath) : '') ?: (realpath($this->albumsPrivateRoot) ?: '');
        if (!$currentPath || !$this->albumService->isSecurePrivatePath($currentPath)) {
            header('Location: arbre-prive.php');
            throw new TerminateException();
        }

        $siteTitle      = $this->config->getSiteTitle();
        $shareUrl       = $_SESSION['share_url'] ?? null;
        $tree           = $this->generatePrivateTree($this->albumsPrivateRoot, $currentPath);
        $allFoldersJson = json_encode($this->collectFoldersList($this->albumsPrivateRoot), JSON_HEX_TAG) ?: '[]';
        $this->renderPrivate($siteTitle, $tree, $shareUrl, $allFoldersJson);
    }

    private function handleGenerateLink(): void
    {
        $albumPath = $this->absPath($_POST['path'] ?? '');
        $duration  = intval($_POST['duration'] ?? 24);
        $comment   = $_POST['comment'] ?? '';

        if (!$albumPath || !$this->albumService->isSecurePrivatePath($albumPath)) {
            $_SESSION['error_message'] = "Chemin d'album invalide.";
            return;
        }

        $albumIdentifier = $this->albumIdentRepo->ensure($albumPath);

        $options = [
            'download' => isset($_POST['opt_download']),
            'source'   => isset($_POST['opt_source']),
            'share'    => isset($_POST['opt_share']),
        ];

        $key = $this->shareKeyRepo->create($albumIdentifier, $duration, $comment, $options);

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $basePath  = $this->config->getBasePath();
        $baseUrl   = $protocol . $_SERVER['HTTP_HOST'] . ($basePath !== '' ? '/' . $basePath : '');
        $shareUrl  = $baseUrl . '/galeries-privees.php?key=' . urlencode($key);
        $durationLabel = $duration === 0 ? 'illimité' : ($duration . ' heures');
        $this->logRepo->log(
            (int) $_SESSION['admin_id'],
            'GENERATE_SHARE_LINK',
            "Création d'un lien de partage valide " . $durationLabel,
            $albumPath,
        );
        $_SESSION['success_message'] = 'Lien de partage généré avec succès.';
        $_SESSION['share_url']       = $shareUrl;
    }

    private function handlePrivatePost(): void
    {
        $action        = $_POST['action']      ?? '';
        $path          = $this->absPath($_POST['path'] ?? '');
        $newName       = $_POST['new_name']    ?? '';
        $description   = $_POST['description'] ?? '';
        $matureContent = isset($_POST['mature_content']) ? '18+' : '18-';

        switch ($action) {
            case 'create_folder':
                if ($path && $newName) {
                    $newPath = $path . '/' . $this->fileService->sanitizeFilename($newName);
                    if (!file_exists($newPath)) {
                        $moreInfoUrl  = $_POST['more_info_url'] ?? '';
                        mkdir($newPath, 0o775, true);
                        $shareOptions = json_encode([
                            'download' => isset($_POST['opt_share_download']),
                            'source'   => isset($_POST['opt_share_source']),
                            'share'    => isset($_POST['opt_share_share']),
                        ]);
                        $infoContent = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl . "\n0\n" . $shareOptions;
                        file_put_contents($newPath . '/infos.txt', $infoContent);
                        $_SESSION['success_message'] = 'Dossier privé « ' . $newName . ' » créé avec succès.';
                    } else {
                        $_SESSION['error_message'] = 'Ce dossier existe déjà.';
                    }

                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'CREATE_PRIVATE_FOLDER',
                        'Création du dossier privé : ' . $newName,
                        $newPath,
                    );
                }

                break;

            case 'edit_folder':
                if ($path && $this->albumService->isSecurePrivatePath($path)) {
                    $moreInfoUrl  = $_POST['more_info_url'] ?? '';
                    $zipDownload  = isset($_POST['zip_download']) ? '1' : '0';
                    $shareOptions = json_encode([
                        'download' => isset($_POST['opt_share_download']),
                        'source'   => isset($_POST['opt_share_source']),
                        'share'    => isset($_POST['opt_share_share']),
                    ]);
                    $infoContent  = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl . "\n" . $zipDownload . "\n" . $shareOptions;
                    if (file_put_contents($path . '/infos.txt', $infoContent) !== false) {
                        $_SESSION['success_message'] = 'Dossier privé « ' . $newName . ' » modifié avec succès.';
                    } else {
                        $_SESSION['error_message'] = 'Erreur lors de la modification du dossier.';
                    }

                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'EDIT_PRIVATE_FOLDER',
                        'Modification du dossier privé : ' . $newName,
                        $path,
                    );
                }

                break;

            case 'delete_folder':
                if ($path && $this->albumService->isSecurePrivatePath($path) && $path !== $this->albumsPrivateRoot) {
                    $folderTitle = $this->albumService->getAlbumInfo($path)['title'];
                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'DELETE_PRIVATE_FOLDER',
                        'Suppression du dossier privé',
                        $path,
                    );
                    $this->fileService->deleteDirectoryRecursively($path);
                    $_SESSION['success_message'] = 'Dossier privé « ' . $folderTitle . ' » supprimé avec succès.';
                }

                break;

            case 'move_folder':
                $destPath       = $this->absPath($_POST['dest_path'] ?? '');
                $privateReal    = realpath($this->albumsPrivateRoot) ?: '';
                if (
                    !$path || !$destPath || !$privateReal ||
                    !str_starts_with($path, $privateReal . '/') ||
                    !str_starts_with($destPath, $privateReal)
                ) {
                    $_SESSION['error_message'] = 'Chemin invalide.';
                    break;
                }

                if ($destPath === $path || str_starts_with($destPath, $path . '/')) {
                    $_SESSION['error_message'] = 'La destination ne peut pas être à l\'intérieur du dossier source.';
                    break;
                }

                if (dirname($path) === $destPath) {
                    $_SESSION['error_message'] = 'Le dossier est déjà dans ce dossier.';
                    break;
                }

                $newPath = $destPath . '/' . basename($path);
                if (file_exists($newPath)) {
                    $_SESSION['error_message'] = 'Un dossier avec ce nom existe déjà dans la destination.';
                    break;
                }

                $folderTitle = $this->albumService->getAlbumInfo($path)['title'];
                $destTitle   = $this->albumService->getAlbumInfo($destPath)['title'];
                if (rename($path, $newPath)) {
                    $this->albumIdentRepo->updatePathsAfterMove($path, $newPath);
                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'MOVE_PRIVATE_FOLDER',
                        'Déplacement de « ' . $folderTitle . ' » vers « ' . $destTitle . ' »',
                        $newPath,
                    );
                    $_SESSION['success_message'] = 'Dossier privé « ' . $folderTitle . ' » déplacé vers « ' . $destTitle . ' » avec succès.';
                } else {
                    $_SESSION['error_message'] = 'Erreur lors du déplacement du dossier.';
                }

                break;
        }
    }

    // -------------------------------------------------------------------------
    // Génération de l'arborescence (HTML)
    // -------------------------------------------------------------------------

    private function generatePublicTree(string $path, string $currentPath): string
    {
        if (!is_dir($path)) {
            return '';
        }

        $output = '<ul class="tree-list">';

        // Dossier racine : affiche également le dossier carrousel
        if (realpath($path) === realpath($this->albumsRoot)) {
            $carouselPath = $this->carouselRoot;
            if (is_dir($carouselPath)) {
                $output .= '<li class="tree-item carousel-folder' . ($carouselPath === $currentPath ? ' active' : '') . '">';
                $output .= '<div class="tree-item-content">';
                $output .= '<span class="tree-link"><span class="folder-icon">🎞️</span> Images du carrousel</span>';
                $output .= '<div class="tree-actions">';
                $output .= '<a href="arbre-img.php?path=' . urlencode($this->relPath($carouselPath)) . '" class="tree-button carousel-button" title="Gérer les images">🖼️</a>';
                $output .= '</div></div></li>';
            }

            $info = $this->albumService->getAlbumInfo($path);
            $output .= '<li class="tree-item root-folder' . ($path === $currentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link"><span class="folder-icon">📁</span> ' . htmlspecialchars($info['title']);
            if ($info['mature_content']) {
                $output .= ' <span class="mature-warning">🔞</span>';
            }

            $output .= '</span>';
            $rootRelPath = $this->relPath($path);
            $output .= '<div class="tree-actions">';
            $output .= '<a href="albums.php?path=' . urlencode($rootRelPath) . '" class="tree-button" target="_blank" title="Voir en mode public" style="text-decoration: none">👁️</a>';
            $output .= '<button onclick="editFolder(\'' . htmlspecialchars($rootRelPath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ", '" . rawurlencode($info['more_info_url']) . "', " . ($this->albumService->hasImages($path) ? 'true' : 'false') . ', ' . ($info['zip_download'] ? 'true' : 'false') . ', ' . ($info['share_options']['download'] ? 'true' : 'false') . ', ' . ($info['share_options']['source'] ? 'true' : 'false') . ', ' . ($info['share_options']['share'] ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($rootRelPath) . '\')" class="tree-button">➕</button>';
            $output .= '</div></div>';
        }

        // Sous-dossiers triés alphabétiquement
        $dirs = [];
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $fullPath = $item->getPathname();
            $info     = $this->albumService->getAlbumInfo($fullPath);
            $dirs[$info['title']] = $fullPath;
        }

        ksort($dirs, SORT_STRING | SORT_FLAG_CASE);

        foreach ($dirs as $fullPath) {
            $info          = $this->albumService->getAlbumInfo($fullPath);
            $isCurrentPath = realpath($fullPath) === $currentPath;
            $hasSubfolders = $this->albumService->hasSubfolders($fullPath);
            $hasImages     = $this->albumService->hasImages($fullPath);
            $imageCount    = $hasImages ? $this->albumService->countImages($fullPath) : 0;

            $output .= '<li class="tree-item' . ($isCurrentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link"><span class="folder-icon">📁</span> ' . htmlspecialchars($info['title']);
            if ($info['mature_content']) {
                $output .= ' <span class="mature-warning">🔞</span>';
            }

            if ($imageCount > 0) {
                $output .= ' <span class="image-count">' . $imageCount . '</span>';
            }

            $output .= '</span>';
            $relativePath = substr($fullPath, strlen($this->projectRoot) + 1);
            $output .= '<div class="tree-actions">';
            if ($hasSubfolders) {
                $output .= '<a href="albums.php?path=' . urlencode($relativePath) . '" class="tree-button" target="_blank" title="Voir en mode public" style="text-decoration: none">👁️</a>';
            } elseif ($hasImages) {
                $output .= '<a href="galeries.php?path=' . urlencode($relativePath) . '" class="tree-button" target="_blank" title="Voir en mode public" style="text-decoration: none">👁️</a>';
            }

            if (!$hasSubfolders) {
                $output .= '<a href="arbre-img.php?path=' . urlencode($relativePath) . '" class="tree-button" style="text-decoration: none">🖼️</a>';
            }

            if (!$hasSubfolders) {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($relativePath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ", '" . rawurlencode($info['more_info_url']) . "', " . ($hasImages ? 'true' : 'false') . ', ' . ($info['zip_download'] ? 'true' : 'false') . ', ' . ($info['share_options']['download'] ? 'true' : 'false') . ', ' . ($info['share_options']['source'] ? 'true' : 'false') . ', ' . ($info['share_options']['share'] ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            } else {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($relativePath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ', \'\', false, false, true, true, true)" class="tree-button">✏️</button>';
            }

            if (!$hasImages) {
                $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($relativePath) . '\')" class="tree-button">➕</button>';
            }

            $output .= '<button onclick="moveFolder(\'' . htmlspecialchars($relativePath) . "', '" . rawurlencode($info['title']) . '\')" class="tree-button" title="Déplacer">↪</button>';
            $output .= '<button onclick="deleteFolder(\'' . htmlspecialchars($relativePath) . '\')" class="tree-button tree-button-danger">🗑️</button>';
            $output .= '</div></div>';
            $output .= $this->generatePublicTree($fullPath, $currentPath);
            $output .= '</li>';
        }

        return $output . '</ul>';
    }

    private function generatePrivateTree(string $path, string $currentPath): string
    {
        if (!is_dir($path)) {
            return '';
        }

        $output = '<ul class="tree-list">';

        // Dossier racine
        if (realpath($path) === realpath($this->albumsPrivateRoot)) {
            $info = $this->albumService->getAlbumInfo($path);
            $output .= '<li class="tree-item root-folder' . ($path === $currentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link"><span class="folder-icon">🔒</span> ' . htmlspecialchars($info['title']);
            if ($info['mature_content']) {
                $output .= ' <span class="mature-warning">🔞</span>';
            }

            $output .= '</span>';
            $privateRootRelPath = $this->relPath($path);
            $output .= '<div class="tree-actions">';
            $output .= '<button onclick="editFolder(\'' . htmlspecialchars($privateRootRelPath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ", '" . rawurlencode($info['more_info_url']) . "', " . ($this->albumService->hasImages($path) ? 'true' : 'false') . ', ' . ($info['zip_download'] ? 'true' : 'false') . ', ' . ($info['share_options']['download'] ? 'true' : 'false') . ', ' . ($info['share_options']['source'] ? 'true' : 'false') . ', ' . ($info['share_options']['share'] ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($privateRootRelPath) . '\')" class="tree-button">➕</button>';
            $output .= '</div></div>';
        }

        $dirs = [];
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $fullPath = $item->getPathname();
            $info     = $this->albumService->getAlbumInfo($fullPath);
            $dirs[$info['title']] = $fullPath;
        }

        ksort($dirs, SORT_STRING | SORT_FLAG_CASE);

        foreach ($dirs as $fullPath) {
            $info          = $this->albumService->getAlbumInfo($fullPath);
            $isCurrentPath = realpath($fullPath) === $currentPath;
            $hasSubfolders = $this->albumService->hasSubfolders($fullPath);
            $hasImages     = $this->albumService->hasImages($fullPath);
            $imageCount    = $hasImages ? $this->albumService->countImages($fullPath) : 0;
            $relPath       = $this->relPath($fullPath);

            $output .= '<li class="tree-item' . ($isCurrentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link"><span class="folder-icon">🔒</span> ' . htmlspecialchars($info['title']);
            if ($info['mature_content']) {
                $output .= ' <span class="mature-warning">🔞</span>';
            }

            if ($imageCount > 0) {
                $output .= ' <span class="image-count">' . $imageCount . '</span>';
            }

            $output .= '</span>';
            $output .= '<div class="tree-actions">';
            if (!$hasSubfolders) {
                $output .= '<a href="arbre-img-prive.php?path=' . urlencode($relPath) . '&private=1" class="tree-button" style="text-decoration: none">🖼️</a>';
                if ($hasImages) {
                    $encodedPath  = htmlspecialchars(addslashes($relPath));
                    $encodedTitle = htmlspecialchars(addslashes($info['title']));
                    $output .= '<button onclick="generateShareLink(\'' . $encodedPath . "', '" . $encodedTitle . "', " . ($info['share_options']['download'] ? 'true' : 'false') . ', ' . ($info['share_options']['source'] ? 'true' : 'false') . ', ' . ($info['share_options']['share'] ? 'true' : 'false') . ')" class="tree-button tree-button-share" title="Générer un lien de partage">🔗</button>';
                }
            }

            if (!$hasSubfolders) {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($relPath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ", '" . rawurlencode($info['more_info_url']) . "', " . ($hasImages ? 'true' : 'false') . ', ' . ($info['zip_download'] ? 'true' : 'false') . ', ' . ($info['share_options']['download'] ? 'true' : 'false') . ', ' . ($info['share_options']['source'] ? 'true' : 'false') . ', ' . ($info['share_options']['share'] ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            } else {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($relPath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ', \'\', false, false, true, true, true)" class="tree-button">✏️</button>';
            }

            if (!$hasImages) {
                $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($relPath) . '\')" class="tree-button">➕</button>';
            }

            $output .= '<button onclick="moveFolder(\'' . htmlspecialchars($relPath) . "', '" . rawurlencode($info['title']) . '\')" class="tree-button" title="Déplacer">↪</button>';
            $output .= '<button onclick="deleteFolder(\'' . htmlspecialchars($relPath) . '\')" class="tree-button tree-button-danger">🗑️</button>';
            $output .= '</div></div>';
            $output .= $this->generatePrivateTree($fullPath, $currentPath);
            $output .= '</li>';
        }

        return $output . '</ul>';
    }

    // -------------------------------------------------------------------------
    // Rendus HTML
    // -------------------------------------------------------------------------

    private function renderPublic(string $siteTitle, string $tree, string $allFoldersJson): void
    {
        $this->view->render('pages/tree-public', [
            'siteTitle'             => $siteTitle,
            'tree'                  => $tree,
            'version'               => $this->config->getVersion(),
            'info_pages'            => $this->infoPageRepo->findPublished(),
            'allFoldersJson'        => $allFoldersJson,
            'default_share_options' => $this->config->getDefaultShareOptions(),
        ]);
    }

    private function renderPrivate(string $siteTitle, string $tree, ?string $shareUrl, string $allFoldersJson): void
    {
        $this->view->render('pages/tree-private', [
            'siteTitle'             => $siteTitle,
            'tree'                  => $tree,
            'shareUrl'              => $shareUrl,
            'version'               => $this->config->getVersion(),
            'info_pages'            => $this->infoPageRepo->findPublished(),
            'allFoldersJson'        => $allFoldersJson,
            'default_share_options' => $this->config->getDefaultShareOptions(),
        ]);
    }

    /**
     * Collecte récursivement tous les dossiers sous $path pour le dropdown de déplacement.
     *
     * @return list<array{path: string, title: string, depth: int}>
     */
    private function collectFoldersList(string $path, int $depth = 0): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $info   = $this->albumService->getAlbumInfo($path);
        $result = [['path' => $this->relPath($path), 'title' => $info['title'], 'depth' => $depth]];

        $dirs = [];
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $subInfo                = $this->albumService->getAlbumInfo($item->getPathname());
            $dirs[$subInfo['title']] = $item->getPathname();
        }

        ksort($dirs, SORT_STRING | SORT_FLAG_CASE);

        foreach ($dirs as $subPath) {
            $result = array_merge($result, $this->collectFoldersList($subPath, $depth + 1));
        }

        return $result;
    }
}
