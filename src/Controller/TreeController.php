<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Config\Config;
use ICO\Http\TerminateException;
use ICO\Repository\AlbumIdentifierRepository;
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
        string                     $projectRoot,
        private readonly AuthService                $auth,
        private readonly AlbumService               $albumService,
        private readonly FileService                $fileService,
        private readonly LogRepository              $logRepo,
        private readonly AlbumIdentifierRepository  $albumIdentRepo,
        private readonly ShareKeyRepository         $shareKeyRepo,
        private readonly ViewRenderer               $view,
    ) {
        $this->albumsRoot        = $projectRoot . '/liste_albums';
        $this->albumsPrivateRoot = $projectRoot . '/liste_albums_prives';
        $this->carouselRoot      = $projectRoot . '/img_carrousel';
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

        $currentPath = realpath($_GET['path'] ?? $this->albumsRoot) ?: realpath($this->albumsRoot);
        if (!$currentPath || !$this->albumService->isSecurePath($currentPath)) {
            header('Location: arbre.php');
            throw new TerminateException();
        }

        $siteTitle = $this->config->getSiteTitle();
        $tree      = $this->generatePublicTree($this->albumsRoot, $currentPath);
        $this->renderPublic($siteTitle, $tree);
    }

    private function handlePublicPost(): void
    {
        $action      = $_POST['action']      ?? '';
        $path        = $_POST['path']        ?? '';
        $newName     = $_POST['new_name']    ?? '';
        $description = $_POST['description'] ?? '';
        $matureContent = isset($_POST['mature_content']) ? '18+' : '18-';

        switch ($action) {
            case 'create_folder':
                if ($path && $newName) {
                    $newPath = $path . '/' . $this->fileService->sanitizeFilename($newName);
                    if (!file_exists($newPath)) {
                        $moreInfoUrl  = $_POST['more_info_url'] ?? '';
                        mkdir($newPath, 0o775, true);
                        $infoContent = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl;
                        file_put_contents($newPath . '/infos.txt', $infoContent);
                        $_SESSION['success_message'] = 'Dossier créé avec succès.';
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
                    $infoContent  = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl;
                    if (file_put_contents($path . '/infos.txt', $infoContent) !== false) {
                        $_SESSION['success_message'] = 'Dossier modifié avec succès.';
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
                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'DELETE_FOLDER',
                        'Suppression du dossier',
                        $path,
                    );
                    $this->fileService->deleteDirectoryRecursively($path);
                    $_SESSION['success_message'] = 'Dossier supprimé avec succès.';
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

        $currentPath = realpath($_GET['path'] ?? $this->albumsPrivateRoot) ?: realpath($this->albumsPrivateRoot);
        if (!$currentPath || !$this->albumService->isSecurePrivatePath($currentPath)) {
            header('Location: arbre-prive.php');
            throw new TerminateException();
        }

        $siteTitle = $this->config->getSiteTitle();
        $shareUrl  = $_SESSION['share_url'] ?? null;
        $tree      = $this->generatePrivateTree($this->albumsPrivateRoot, $currentPath);
        $this->renderPrivate($siteTitle, $tree, $shareUrl);
    }

    private function handleGenerateLink(): void
    {
        $albumPath = $_POST['path']    ?? '';
        $duration  = intval($_POST['duration'] ?? 24);
        $comment   = $_POST['comment'] ?? '';

        if (!$albumPath || !$this->albumService->isSecurePrivatePath($albumPath)) {
            $_SESSION['error_message'] = "Chemin d'album invalide.";
            return;
        }

        $albumIdentifier = $this->albumIdentRepo->ensure($albumPath);

        $key = $this->shareKeyRepo->create($albumIdentifier, $duration, $comment);

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $basePath  = $this->config->getBasePath();
        $baseUrl   = $protocol . $_SERVER['HTTP_HOST'] . ($basePath !== '' ? '/' . $basePath : '');
        $shareUrl  = $baseUrl . '/galeries-privees.php?key=' . urlencode($key);
        $this->logRepo->log(
            (int) $_SESSION['admin_id'],
            'GENERATE_SHARE_LINK',
            "Création d'un lien de partage valide " . $duration . ' heures',
            $albumPath,
        );
        $_SESSION['success_message'] = 'Lien de partage généré avec succès.';
        $_SESSION['share_url']       = $shareUrl;
    }

    private function handlePrivatePost(): void
    {
        $action      = $_POST['action']      ?? '';
        $path        = $_POST['path']        ?? '';
        $newName     = $_POST['new_name']    ?? '';
        $description = $_POST['description'] ?? '';
        $matureContent = isset($_POST['mature_content']) ? '18+' : '18-';

        switch ($action) {
            case 'create_folder':
                if ($path && $newName) {
                    $newPath = $path . '/' . $this->fileService->sanitizeFilename($newName);
                    if (!file_exists($newPath)) {
                        $moreInfoUrl  = $_POST['more_info_url'] ?? '';
                        mkdir($newPath, 0o775, true);
                        $infoContent = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl;
                        file_put_contents($newPath . '/infos.txt', $infoContent);
                        $_SESSION['success_message'] = 'Dossier privé créé avec succès.';
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
                    $infoContent  = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl;
                    if (file_put_contents($path . '/infos.txt', $infoContent) !== false) {
                        $_SESSION['success_message'] = 'Dossier modifié avec succès.';
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
                    $this->logRepo->log(
                        (int) $_SESSION['admin_id'],
                        'DELETE_PRIVATE_FOLDER',
                        'Suppression du dossier privé',
                        $path,
                    );
                    $this->fileService->deleteDirectoryRecursively($path);
                    $_SESSION['success_message'] = 'Dossier privé supprimé avec succès.';
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
                $output .= '<a href="arbre-img.php?path=' . urlencode($carouselPath) . '" class="tree-button carousel-button" title="Gérer les images">🖼️</a>';
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
            $output .= '<div class="tree-actions">';
            $output .= '<button onclick="editFolder(\'' . htmlspecialchars($path) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ", '" . rawurlencode($info['more_info_url']) . "', " . ($this->albumService->hasImages($path) ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($path) . '\')" class="tree-button">➕</button>';
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

            $output .= '<li class="tree-item' . ($isCurrentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link"><span class="folder-icon">📁</span> ' . htmlspecialchars($info['title']);
            if ($info['mature_content']) {
                $output .= ' <span class="mature-warning">🔞</span>';
            }

            $output .= '</span>';
            $output .= '<div class="tree-actions">';
            if (!$hasSubfolders) {
                $output .= '<a href="arbre-img.php?path=' . urlencode($fullPath) . '" class="tree-button" style="text-decoration: none">🖼️</a>';
            }

            if (!$hasSubfolders) {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ", '" . rawurlencode($info['more_info_url']) . "', " . ($hasImages ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            } else {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ', \'\', false)" class="tree-button">✏️</button>';
            }

            if (!$hasImages) {
                $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button">➕</button>';
            }

            $output .= '<button onclick="deleteFolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button tree-button-danger">🗑️</button>';
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
            $output .= '<div class="tree-actions">';
            $output .= '<button onclick="editFolder(\'' . htmlspecialchars($path) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ", '" . rawurlencode($info['more_info_url']) . "', " . ($this->albumService->hasImages($path) ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($path) . '\')" class="tree-button">➕</button>';
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

            $output .= '<li class="tree-item' . ($isCurrentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link"><span class="folder-icon">🔒</span> ' . htmlspecialchars($info['title']);
            if ($info['mature_content']) {
                $output .= ' <span class="mature-warning">🔞</span>';
            }

            $output .= '</span>';
            $output .= '<div class="tree-actions">';
            if (!$hasSubfolders) {
                $output .= '<a href="arbre-img-prive.php?path=' . urlencode($fullPath) . '&private=1" class="tree-button" style="text-decoration: none">🖼️</a>';
                if ($hasImages) {
                    $encodedPath  = htmlspecialchars(addslashes($fullPath));
                    $encodedTitle = htmlspecialchars(addslashes($info['title']));
                    $output .= '<button onclick="generateShareLink(\'' . $encodedPath . "', '" . $encodedTitle . '\')" class="tree-button tree-button-share" title="Générer un lien de partage">🔗</button>';
                }
            }

            if (!$hasSubfolders) {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ", '" . rawurlencode($info['more_info_url']) . "', " . ($hasImages ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            } else {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . "', '" . rawurlencode($info['title']) . "', '" . rawurlencode($info['description']) . "', " . ($info['mature_content'] ? 'true' : 'false') . ', \'\', false)" class="tree-button">✏️</button>';
            }

            if (!$hasImages) {
                $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button">➕</button>';
            }

            $output .= '<button onclick="deleteFolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button tree-button-danger">🗑️</button>';
            $output .= '</div></div>';
            $output .= $this->generatePrivateTree($fullPath, $currentPath);
            $output .= '</li>';
        }

        return $output . '</ul>';
    }

    // -------------------------------------------------------------------------
    // Rendus HTML
    // -------------------------------------------------------------------------

    private function renderPublic(string $siteTitle, string $tree): void
    {
        $this->view->render('pages/tree-public', [
            'siteTitle' => $siteTitle,
            'tree'      => $tree,
            'version'   => $this->config->getVersion(),
        ]);
    }

    private function renderPrivate(string $siteTitle, string $tree, ?string $shareUrl): void
    {
        $this->view->render('pages/tree-private', [
            'siteTitle' => $siteTitle,
            'tree'      => $tree,
            'shareUrl'  => $shareUrl,
            'version'   => $this->config->getVersion(),
        ]);
    }
}
