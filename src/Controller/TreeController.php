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

    /**
     * @return array{download: bool, source: bool, share: bool}
     */
    private function buildShareOptionsFromPost(): array
    {
        return [
            'download' => isset($_POST['opt_share_download']),
            'source'   => isset($_POST['opt_share_source']),
            'share'    => isset($_POST['opt_share_share']),
        ];
    }

    private function buildShareOptionsModeFromPost(): string
    {
        return ($_POST['share_options_mode'] ?? 'global') === 'custom' ? 'custom' : 'global';
    }

    /**
     * @param array{download: bool, source: bool, share: bool} $shareOptions
     *
     * @return array<string, bool|string>
     */
    private function buildShareOptionsPayload(string $shareOptionsMode, array $shareOptions): array
    {
        if ($shareOptionsMode !== 'custom') {
            return ['mode' => 'global'];
        }

        return [
            'mode'     => 'custom',
            'download' => $shareOptions['download'],
            'source'   => $shareOptions['source'],
            'share'    => $shareOptions['share'],
        ];
    }

    /**
     * @param array{download: bool, source: bool, share: bool} $shareOptions
     */
    private function buildInfoContent(
        string $title,
        string $description,
        string $matureContent,
        string $moreInfoUrl,
        string $zipDownload,
        string $shareOptionsMode,
        array $shareOptions,
    ): string {
        $encodedShareOptions = json_encode($this->buildShareOptionsPayload($shareOptionsMode, $shareOptions));
        if ($encodedShareOptions === false) {
            $encodedShareOptions = '{}';
        }

        return implode("\n", [$title, $description, $matureContent, $moreInfoUrl, $zipDownload, $encodedShareOptions]);
    }

    /**
     * @param array{download: bool, source: bool, share: bool} $shareOptions
     */
    private function writeAlbumInfo(
        string $path,
        string $title,
        string $description,
        string $matureContent,
        string $moreInfoUrl,
        string $zipDownload,
        string $shareOptionsMode,
        array $shareOptions,
    ): bool {
        return file_put_contents(
            $path . '/infos.txt',
            $this->buildInfoContent($title, $description, $matureContent, $moreInfoUrl, $zipDownload, $shareOptionsMode, $shareOptions)
        ) !== false;
    }

    /**
     * @param array{download: bool, source: bool, share: bool} $shareOptions
     */
    private function applyShareOptionsToSubfolders(string $path, string $shareOptionsMode, array $shareOptions): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $subfolder = $item->getPathname();
            $info      = $this->albumService->getAlbumInfo($subfolder);
            $this->writeAlbumInfo(
                $subfolder,
                $info['title'],
                $info['description'],
                $info['mature_content'] ? '18+' : '18-',
                $info['more_info_url'],
                $info['zip_download'] ? '1' : '0',
                $shareOptionsMode,
                $shareOptions,
            );
            $this->applyShareOptionsToSubfolders($subfolder, $shareOptionsMode, $shareOptions);
        }
    }

    private function jsBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * @param array{download: bool, source: bool, share: bool} $shareOptions
     */
    private function shareOptionsJsArgs(array $shareOptions): string
    {
        return $this->jsBool($shareOptions['download'])
            . ', ' . $this->jsBool($shareOptions['source'])
            . ', ' . $this->jsBool($shareOptions['share']);
    }

    /**
     * @param array{share_options_mode?: string} $info
     */
    private function shareOptionsMode(array $info): string
    {
        return ($info['share_options_mode'] ?? 'global') === 'custom' ? 'custom' : 'global';
    }

    /**
     * @param array{share_options_mode?: string, share_options?: array{download?: bool, source?: bool, share?: bool}} $info
     *
     * @return array{download: bool, source: bool, share: bool}
     */
    private function effectiveShareOptions(array $info): array
    {
        return $this->albumService->getEffectiveShareOptions($info, $this->config->getDefaultShareOptions());
    }

    /**
     * @param array{title: string, description: string, mature_content: bool, more_info_url: string, zip_download: bool, share_options_mode?: string, share_options: array{download: bool, source: bool, share: bool}} $info
     */
    private function editFolderButton(string $relativePath, array $info, bool $hasImages, bool $hasSubfolders): string
    {
        $shareOptions = $this->effectiveShareOptions($info);

        return sprintf(
            '<button onclick="editFolder(\'%s\', \'%s\', \'%s\', %s, \'%s\', %s, %s, %s, \'%s\', %s)" class="tree-button">✏️</button>',
            htmlspecialchars($relativePath),
            rawurlencode($info['title']),
            rawurlencode($info['description']),
            $this->jsBool($info['mature_content']),
            rawurlencode($info['more_info_url']),
            $this->jsBool($hasImages),
            $this->jsBool($info['zip_download']),
            $this->shareOptionsJsArgs($shareOptions),
            $this->shareOptionsMode($info),
            $this->jsBool($hasSubfolders),
        );
    }

    /**
     * @param array{download: bool, source: bool, share: bool} $shareOptions
     */
    private function createSubfolderButton(string $relativePath, string $shareOptionsMode, array $shareOptions): string
    {
        return sprintf(
            '<button onclick="createSubfolder(\'%s\', \'%s\', %s)" class="tree-button">➕</button>',
            htmlspecialchars($relativePath),
            $shareOptionsMode,
            $this->shareOptionsJsArgs($shareOptions),
        );
    }

    /**
     * @param array{title: string, description: string, mature_content: bool, more_info_url: string, zip_download: bool, share_options_mode?: string, share_options: array{download: bool, source: bool, share: bool}} $info
     */
    private function treeTitle(string $icon, array $info, int $imageCount = 0): string
    {
        $parts = [
            '<span class="tree-link"><span class="folder-icon">' . $icon . '</span> ' . htmlspecialchars($info['title']),
        ];
        if ($info['mature_content']) {
            $parts[] = ' <span class="mature-warning">🔞</span>';
        }

        if ($imageCount > 0) {
            $parts[] = sprintf(' <span class="image-count">%d</span>', $imageCount);
        }

        $parts[] = '</span>';

        return implode('', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function sortedSubfolderPathsByTitle(string $path): array
    {
        $dirs = [];
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $fullPath             = $item->getPathname();
            $info                 = $this->albumService->getAlbumInfo($fullPath);
            $dirs[$info['title']] = $fullPath;
        }

        ksort($dirs, SORT_STRING | SORT_FLAG_CASE);

        return $dirs;
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
                        $moreInfoUrl      = $_POST['more_info_url'] ?? '';
                        $shareOptionsMode = $this->buildShareOptionsModeFromPost();
                        mkdir($newPath, 0o775, true);
                        $this->writeAlbumInfo(
                            $newPath,
                            $newName,
                            $description,
                            $matureContent,
                            $moreInfoUrl,
                            '0',
                            $shareOptionsMode,
                            $this->buildShareOptionsFromPost(),
                        );
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
                    $moreInfoUrl      = $_POST['more_info_url'] ?? '';
                    $zipDownload      = isset($_POST['zip_download']) ? '1' : '0';
                    $shareOptionsMode = $this->buildShareOptionsModeFromPost();
                    $shareOptions     = $this->buildShareOptionsFromPost();
                    if ($this->writeAlbumInfo($path, $newName, $description, $matureContent, $moreInfoUrl, $zipDownload, $shareOptionsMode, $shareOptions)) {
                        if (isset($_POST['apply_share_options_to_subfolders'])) {
                            $this->applyShareOptionsToSubfolders($path, $shareOptionsMode, $shareOptions);
                        }

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
                        $moreInfoUrl      = $_POST['more_info_url'] ?? '';
                        $shareOptionsMode = $this->buildShareOptionsModeFromPost();
                        mkdir($newPath, 0o775, true);
                        $this->writeAlbumInfo(
                            $newPath,
                            $newName,
                            $description,
                            $matureContent,
                            $moreInfoUrl,
                            '0',
                            $shareOptionsMode,
                            $this->buildShareOptionsFromPost(),
                        );
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
                    $moreInfoUrl      = $_POST['more_info_url'] ?? '';
                    $zipDownload      = isset($_POST['zip_download']) ? '1' : '0';
                    $shareOptionsMode = $this->buildShareOptionsModeFromPost();
                    $shareOptions     = $this->buildShareOptionsFromPost();
                    if ($this->writeAlbumInfo($path, $newName, $description, $matureContent, $moreInfoUrl, $zipDownload, $shareOptionsMode, $shareOptions)) {
                        if (isset($_POST['apply_share_options_to_subfolders'])) {
                            $this->applyShareOptionsToSubfolders($path, $shareOptionsMode, $shareOptions);
                        }

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

        $items = [];

        // Dossier racine : affiche également le dossier carrousel
        if (realpath($path) === realpath($this->albumsRoot)) {
            $carouselPath = $this->carouselRoot;
            if (is_dir($carouselPath)) {
                $items[] = $this->carouselTreeItem($carouselPath, $currentPath);
            }

            $items[] = $this->publicRootTreeItem($path, $currentPath);
        }

        foreach ($this->sortedSubfolderPathsByTitle($path) as $fullPath) {
            $items[] = $this->publicFolderTreeItem($fullPath, $currentPath);
        }

        return '<ul class="tree-list">' . implode('', $items) . '</ul>';
    }

    private function carouselTreeItem(string $carouselPath, string $currentPath): string
    {
        return '<li class="tree-item carousel-folder' . ($carouselPath === $currentPath ? ' active' : '') . '">'
            . '<div class="tree-item-content">'
            . '<span class="tree-link"><span class="folder-icon">🎞️</span> Images du carrousel</span>'
            . '<div class="tree-actions">'
            . '<a href="arbre-img.php?path=' . urlencode($this->relPath($carouselPath)) . '" class="tree-button carousel-button" title="Gérer les images">🖼️</a>'
            . '</div></div></li>';
    }

    private function publicRootTreeItem(string $path, string $currentPath): string
    {
        $info              = $this->albumService->getAlbumInfo($path);
        $effectiveOptions  = $this->effectiveShareOptions($info);
        $rootRelPath       = $this->relPath($path);
        $actions           = [
            '<a href="albums.php?path=' . urlencode($rootRelPath) . '" class="tree-button" target="_blank" title="Voir en mode public" style="text-decoration: none">👁️</a>',
            $this->editFolderButton(
                $rootRelPath,
                $info,
                $this->albumService->hasImages($path),
                $this->albumService->hasSubfolders($path),
            ),
            $this->createSubfolderButton($rootRelPath, $this->shareOptionsMode($info), $effectiveOptions),
        ];

        return '<li class="tree-item root-folder' . ($path === $currentPath ? ' active' : '') . '">'
            . '<div class="tree-item-content">'
            . $this->treeTitle('📁', $info)
            . '<div class="tree-actions">' . implode('', $actions) . '</div></div>';
    }

    private function publicFolderTreeItem(string $fullPath, string $currentPath): string
    {
        $info          = $this->albumService->getAlbumInfo($fullPath);
        $isCurrentPath = realpath($fullPath) === $currentPath;
        $hasSubfolders = $this->albumService->hasSubfolders($fullPath);
        $hasImages     = $this->albumService->hasImages($fullPath);
        $imageCount    = $hasImages ? $this->albumService->countImages($fullPath) : 0;
        $relativePath  = $this->relPath($fullPath);
        $actions       = [];

        if ($hasSubfolders) {
            $actions[] = '<a href="albums.php?path=' . urlencode($relativePath) . '" class="tree-button" target="_blank" title="Voir en mode public" style="text-decoration: none">👁️</a>';
        } elseif ($hasImages) {
            $actions[] = '<a href="galeries.php?path=' . urlencode($relativePath) . '" class="tree-button" target="_blank" title="Voir en mode public" style="text-decoration: none">👁️</a>';
        }

        if (!$hasSubfolders) {
            $actions[] = '<a href="arbre-img.php?path=' . urlencode($relativePath) . '" class="tree-button" style="text-decoration: none">🖼️</a>';
        }

        $actions[] = $this->editFolderButton($relativePath, $info, !$hasSubfolders && $hasImages, $hasSubfolders);

        if (!$hasImages) {
            $actions[] = $this->createSubfolderButton($relativePath, $this->shareOptionsMode($info), $this->effectiveShareOptions($info));
        }

        $actions[] = '<button onclick="moveFolder(\'' . htmlspecialchars($relativePath) . "', '" . rawurlencode($info['title']) . '\')" class="tree-button" title="Déplacer">↪</button>';
        $actions[] = '<button onclick="deleteFolder(\'' . htmlspecialchars($relativePath) . '\')" class="tree-button tree-button-danger">🗑️</button>';

        return '<li class="tree-item' . ($isCurrentPath ? ' active' : '') . '">'
            . '<div class="tree-item-content">'
            . $this->treeTitle('📁', $info, $imageCount)
            . '<div class="tree-actions">' . implode('', $actions) . '</div></div>'
            . $this->generatePublicTree($fullPath, $currentPath)
            . '</li>';
    }

    private function generatePrivateTree(string $path, string $currentPath): string
    {
        if (!is_dir($path)) {
            return '';
        }

        $items = [];

        // Dossier racine
        if (realpath($path) === realpath($this->albumsPrivateRoot)) {
            $items[] = $this->privateRootTreeItem($path, $currentPath);
        }

        foreach ($this->sortedSubfolderPathsByTitle($path) as $fullPath) {
            $items[] = $this->privateFolderTreeItem($fullPath, $currentPath);
        }

        return '<ul class="tree-list">' . implode('', $items) . '</ul>';
    }

    private function privateRootTreeItem(string $path, string $currentPath): string
    {
        $info               = $this->albumService->getAlbumInfo($path);
        $effectiveOptions   = $this->effectiveShareOptions($info);
        $privateRootRelPath = $this->relPath($path);
        $actions            = [
            $this->editFolderButton(
                $privateRootRelPath,
                $info,
                $this->albumService->hasImages($path),
                $this->albumService->hasSubfolders($path),
            ),
            $this->createSubfolderButton($privateRootRelPath, $this->shareOptionsMode($info), $effectiveOptions),
        ];

        return '<li class="tree-item root-folder' . ($path === $currentPath ? ' active' : '') . '">'
            . '<div class="tree-item-content">'
            . $this->treeTitle('🔒', $info)
            . '<div class="tree-actions">' . implode('', $actions) . '</div></div>';
    }

    private function privateFolderTreeItem(string $fullPath, string $currentPath): string
    {
        $info          = $this->albumService->getAlbumInfo($fullPath);
        $isCurrentPath = realpath($fullPath) === $currentPath;
        $hasSubfolders = $this->albumService->hasSubfolders($fullPath);
        $hasImages     = $this->albumService->hasImages($fullPath);
        $imageCount    = $hasImages ? $this->albumService->countImages($fullPath) : 0;
        $relPath       = $this->relPath($fullPath);
        $actions       = [];

        if ($hasSubfolders) {
            $actions[] = $this->privateGalleryButton($relPath);
            $actions[] = $this->generateShareLinkButton($relPath, $info);
        }

        if (!$hasSubfolders) {
            if ($hasImages) {
                $actions[] = $this->privateGalleryButton($relPath);
            }

            $actions[] = '<a href="arbre-img-prive.php?path=' . urlencode($relPath) . '&private=1" class="tree-button" style="text-decoration: none">🖼️</a>';
            if ($hasImages) {
                $actions[] = $this->generateShareLinkButton($relPath, $info);
            }
        }

        $actions[] = $this->editFolderButton($relPath, $info, !$hasSubfolders && $hasImages, $hasSubfolders);

        if (!$hasImages) {
            $actions[] = $this->createSubfolderButton($relPath, $this->shareOptionsMode($info), $this->effectiveShareOptions($info));
        }

        $actions[] = '<button onclick="moveFolder(\'' . htmlspecialchars($relPath) . "', '" . rawurlencode($info['title']) . '\')" class="tree-button" title="Déplacer">↪</button>';
        $actions[] = '<button onclick="deleteFolder(\'' . htmlspecialchars($relPath) . '\')" class="tree-button tree-button-danger">🗑️</button>';

        return '<li class="tree-item' . ($isCurrentPath ? ' active' : '') . '">'
            . '<div class="tree-item-content">'
            . $this->treeTitle('🔒', $info, $imageCount)
            . '<div class="tree-actions">' . implode('', $actions) . '</div></div>'
            . $this->generatePrivateTree($fullPath, $currentPath)
            . '</li>';
    }

    private function privateGalleryButton(string $relPath): string
    {
        return '<a href="galeries-privees.php?path='
            . urlencode($relPath)
            . '" class="tree-button" target="_blank" title="Accéder à la galerie privée" style="text-decoration: none">👁️</a>';
    }

    /**
     * @param array{title: string, description: string, mature_content: bool, more_info_url: string, zip_download: bool, share_options_mode?: string, share_options: array{download: bool, source: bool, share: bool}} $info
     */
    private function generateShareLinkButton(string $relPath, array $info): string
    {
        return '<button onclick="generateShareLink(\''
            . htmlspecialchars(addslashes($relPath))
            . "', '"
            . htmlspecialchars(addslashes($info['title']))
            . "', "
            . $this->shareOptionsJsArgs($this->effectiveShareOptions($info))
            . ')" class="tree-button tree-button-share" title="Générer un lien de partage">🔗</button>';
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
