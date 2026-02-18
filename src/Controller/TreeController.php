<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\LogRepository;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\Service\FileService;

/**
 * Gère l'arborescence des albums publics (arbre.php)
 * et privés (arbre-prive.php).
 *
 * Sources : arbre.php (393 lignes), arbre-prive.php (482 lignes).
 */
class TreeController
{
    public function __construct(
        private readonly Config                     $config,
        private readonly AuthService                $auth,
        private readonly AlbumService               $albumService,
        private readonly FileService                $fileService,
        private readonly LogRepository              $logRepo,
        private readonly AlbumIdentifierRepository  $albumIdentRepo,
        private readonly ShareKeyRepository         $shareKeyRepo,
    ) {}

    // -------------------------------------------------------------------------
    // arbre.php — albums publics
    // -------------------------------------------------------------------------

    public function handlePublic(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePublicPost();
            header('Location: arbre.php');
            exit;
        }

        $currentPath = realpath($_GET['path'] ?? './liste_albums') ?: realpath('./liste_albums');
        if (!$currentPath || !$this->albumService->isSecurePath($currentPath)) {
            header('Location: arbre.php');
            exit;
        }

        $siteTitle = $this->config->getSiteTitle();
        $tree      = $this->generatePublicTree('./liste_albums', $currentPath);
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
                        mkdir($newPath, 0775, true);
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
                if ($path && $this->albumService->isSecurePath($path) && $path !== './liste_albums') {
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
            exit;
        }

        // Créer le dossier racine privé si nécessaire
        if (!file_exists('./liste_albums_prives')) {
            mkdir('./liste_albums_prives', 0775, true);
            file_put_contents('./liste_albums_prives/infos.txt', "Albums privés\nVos albums photos privés\n18-\n");
        }

        // Action generate_link (prioritaire)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_link') {
            $this->handleGenerateLink();
            header('Location: arbre-prive.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePrivatePost();
            header('Location: arbre-prive.php');
            exit;
        }

        $currentPath = realpath($_GET['path'] ?? './liste_albums_prives') ?: realpath('./liste_albums_prives');
        if (!$currentPath || !$this->albumService->isSecurePrivatePath($currentPath)) {
            header('Location: arbre-prive.php');
            exit;
        }

        $siteTitle = $this->config->getSiteTitle();
        $shareUrl  = $_SESSION['share_url'] ?? null;
        $tree      = $this->generatePrivateTree('./liste_albums_prives', $currentPath);
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
        if ($key === null) {
            $_SESSION['error_message'] = 'Erreur lors de la génération du lien de partage.';
            return;
        }

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
                        mkdir($newPath, 0775, true);
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
                if ($path && $this->albumService->isSecurePrivatePath($path) && $path !== './liste_albums_prives') {
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
        if ($path === './liste_albums') {
            $carouselPath = './img_carrousel';
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
            $output .= '<button onclick="editFolder(\'' . htmlspecialchars($path) . '\', \'' . rawurlencode($info['title']) . '\', \'' . rawurlencode($info['description']) . '\', ' . ($info['mature_content'] ? 'true' : 'false') . ', \'' . rawurlencode($info['more_info_url']) . '\', ' . ($this->albumService->hasImages($path) ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($path) . '\')" class="tree-button">➕</button>';
            $output .= '</div></div>';
        }

        // Sous-dossiers triés alphabétiquement
        $dirs = [];
        foreach (new \DirectoryIterator($path) as $item) {
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
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . '\', \'' . rawurlencode($info['title']) . '\', \'' . rawurlencode($info['description']) . '\', ' . ($info['mature_content'] ? 'true' : 'false') . ', \'' . rawurlencode($info['more_info_url']) . '\', ' . ($hasImages ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            } else {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . '\', \'' . rawurlencode($info['title']) . '\', \'' . rawurlencode($info['description']) . '\', ' . ($info['mature_content'] ? 'true' : 'false') . ', \'\', false)" class="tree-button">✏️</button>';
            }
            if (!$hasImages) {
                $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button">➕</button>';
            }
            $output .= '<button onclick="deleteFolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button tree-button-danger">🗑️</button>';
            $output .= '</div></div>';
            $output .= $this->generatePublicTree($fullPath, $currentPath);
            $output .= '</li>';
        }

        $output .= '</ul>';
        return $output;
    }

    private function generatePrivateTree(string $path, string $currentPath): string
    {
        if (!is_dir($path)) {
            return '';
        }

        $output = '<ul class="tree-list">';

        // Dossier racine
        if ($path === './liste_albums_prives') {
            $info = $this->albumService->getAlbumInfo($path);
            $output .= '<li class="tree-item root-folder' . ($path === $currentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link"><span class="folder-icon">🔒</span> ' . htmlspecialchars($info['title']);
            if ($info['mature_content']) {
                $output .= ' <span class="mature-warning">🔞</span>';
            }
            $output .= '</span>';
            $output .= '<div class="tree-actions">';
            $output .= '<button onclick="editFolder(\'' . htmlspecialchars($path) . '\', \'' . rawurlencode($info['title']) . '\', \'' . rawurlencode($info['description']) . '\', ' . ($info['mature_content'] ? 'true' : 'false') . ', \'' . rawurlencode($info['more_info_url']) . '\', ' . ($this->albumService->hasImages($path) ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($path) . '\')" class="tree-button">➕</button>';
            $output .= '</div></div>';
        }

        $dirs = [];
        foreach (new \DirectoryIterator($path) as $item) {
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
                    $output .= '<button onclick="generateShareLink(\'' . $encodedPath . '\', \'' . $encodedTitle . '\')" class="tree-button tree-button-share" title="Générer un lien de partage">🔗</button>';
                }
            }
            if (!$hasSubfolders) {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . '\', \'' . rawurlencode($info['title']) . '\', \'' . rawurlencode($info['description']) . '\', ' . ($info['mature_content'] ? 'true' : 'false') . ', \'' . rawurlencode($info['more_info_url']) . '\', ' . ($hasImages ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
            } else {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . '\', \'' . rawurlencode($info['title']) . '\', \'' . rawurlencode($info['description']) . '\', ' . ($info['mature_content'] ? 'true' : 'false') . ', \'\', false)" class="tree-button">✏️</button>';
            }
            if (!$hasImages) {
                $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button">➕</button>';
            }
            $output .= '<button onclick="deleteFolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button tree-button-danger">🗑️</button>';
            $output .= '</div></div>';
            $output .= $this->generatePrivateTree($fullPath, $currentPath);
            $output .= '</li>';
        }

        $output .= '</ul>';
        return $output;
    }

    // -------------------------------------------------------------------------
    // Rendus HTML
    // -------------------------------------------------------------------------

    private function renderPublic(string $siteTitle, string $tree): void
    {
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arborescence - <?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
    <div class="admin-header">
        <h1>Gestion de l'arborescence</h1>
        <div class="admin-actions">
            <button onclick="createSubfolder('./liste_albums')" class="action-button">Nouveau dossier</button>
            <a href="admin.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success-message"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="tree-container">
            <?php echo $tree; ?>
        </div>
    </div>

    <!-- Modal de création de dossier -->
    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <h2>Créer un nouveau dossier</h2>
            <form method="post" action="arbre.php">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="path" id="parentPath">
                <div class="form-group">
                    <label for="new_name">Nom du dossier :</label>
                    <input type="text" id="new_name" name="new_name" required>
                </div>
                <div class="form-group">
                    <label for="description">Description :</label>
                    <textarea id="description" name="description" rows="4" class="form-textarea"></textarea>
                </div>
                <div class="form-group">
                    <label for="more_info_url">Lien "En savoir plus" (optionnel) :</label>
                    <input type="url" id="more_info_url" name="more_info_url" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label class="toggle-label">
                        <input type="checkbox" name="mature_content" id="mature_content">
                        <span class="toggle-text">Contenu réservé aux plus de 18 ans</span>
                        <span class="toggle-warning">⚠️</span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal d'édition de dossier -->
    <div id="editFolderModal" class="modal">
        <div class="modal-content">
            <h2>Modifier le dossier</h2>
            <form method="post" action="arbre.php">
                <input type="hidden" name="action" value="edit_folder">
                <input type="hidden" name="path" id="editPath">
                <div class="form-group">
                    <label for="edit_name">Nom du dossier :</label>
                    <input type="text" id="edit_name" name="new_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description :</label>
                    <textarea id="edit_description" name="description" rows="4" class="form-textarea"></textarea>
                </div>
                <div class="form-group">
                    <label class="toggle-label">
                        <input type="checkbox" name="mature_content" id="edit_mature_content">
                        <span class="toggle-text">Contenu réservé aux plus de 18 ans</span>
                        <span class="toggle-warning">⚠️</span>
                    </label>
                </div>
                <div class="form-group" id="edit_more_info_url_field">
                    <label for="edit_more_info_url">Lien "En savoir plus" (optionnel) :</label>
                    <input type="url" id="edit_more_info_url" name="more_info_url" placeholder="https://...">
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteFolderModal" class="modal">
        <div class="modal-content">
            <h2>Confirmer la suppression</h2>
            <p>Êtes-vous sûr de vouloir supprimer ce dossier et tout son contenu ?</p>
            <form method="post" action="arbre.php">
                <input type="hidden" name="action" value="delete_folder">
                <input type="hidden" name="path" id="deletePath">
                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button action-button-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>

    <?php echo $this->renderTreeScripts(); ?>
    <?php include 'footer.php'; ?>
</body>
</html>
        <?php
    }

    private function renderPrivate(string $siteTitle, string $tree, ?string $shareUrl): void
    {
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Albums privés - <?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
    <div class="admin-header">
        <h1>Gestion des albums privés</h1>
        <div class="admin-actions">
            <button onclick="createSubfolder('./liste_albums_prives')" class="action-button">Nouveau dossier privé</button>
            <a href="admin.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success-message">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <?php if ($shareUrl !== null): ?>
                <div class="share-url-container">
                    <input type="text" value="<?php echo htmlspecialchars($shareUrl); ?>"
                        class="share-url-input" readonly onclick="this.select()">
                    <button onclick="copyShareUrl(this)" class="tree-button" title="Copier">📋</button>
                </div>
                <?php unset($_SESSION['share_url']); ?>
            <?php endif; ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="tree-container">
            <?php echo $tree; ?>
        </div>
    </div>

    <!-- Modal de création de dossier -->
    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <h2>Créer un nouveau dossier privé</h2>
            <form method="post" action="arbre-prive.php">
                <input type="hidden" name="action" value="create_folder">
                <input type="hidden" name="path" id="parentPath">
                <div class="form-group">
                    <label for="new_name">Nom du dossier :</label>
                    <input type="text" id="new_name" name="new_name" required>
                </div>
                <div class="form-group">
                    <label for="description">Description :</label>
                    <textarea id="description" name="description" rows="4" class="form-textarea"></textarea>
                </div>
                <div class="form-group">
                    <label class="toggle-label">
                        <input type="checkbox" name="mature_content" id="mature_content">
                        <span class="toggle-text">Contenu réservé aux plus de 18 ans</span>
                        <span class="toggle-warning">⚠️</span>
                    </label>
                </div>
                <div class="form-group" id="create_more_info_url_field" style="display: none;">
                    <label for="more_info_url">Lien "En savoir plus" (optionnel) :</label>
                    <input type="url" id="more_info_url" name="more_info_url" placeholder="https://...">
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal d'édition de dossier -->
    <div id="editFolderModal" class="modal">
        <div class="modal-content">
            <h2>Modifier le dossier privé</h2>
            <form method="post" action="arbre-prive.php">
                <input type="hidden" name="action" value="edit_folder">
                <input type="hidden" name="path" id="editPath">
                <div class="form-group">
                    <label for="edit_name">Nom du dossier :</label>
                    <input type="text" id="edit_name" name="new_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description :</label>
                    <textarea id="edit_description" name="description" rows="4" class="form-textarea"></textarea>
                </div>
                <div class="form-group">
                    <label class="toggle-label">
                        <input type="checkbox" name="mature_content" id="edit_mature_content">
                        <span class="toggle-text">Contenu réservé aux plus de 18 ans</span>
                        <span class="toggle-warning">⚠️</span>
                    </label>
                </div>
                <div class="form-group" id="edit_more_info_url_field">
                    <label for="edit_more_info_url">Lien "En savoir plus" (optionnel) :</label>
                    <input type="url" id="edit_more_info_url" name="more_info_url" placeholder="https://...">
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteFolderModal" class="modal">
        <div class="modal-content">
            <h2>Confirmer la suppression</h2>
            <p>Êtes-vous sûr de vouloir supprimer ce dossier privé et tout son contenu ?</p>
            <form method="post" action="arbre-prive.php">
                <input type="hidden" name="action" value="delete_folder">
                <input type="hidden" name="path" id="deletePath">
                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button action-button-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal génération de lien de partage -->
    <div id="shareLinkModal" class="modal">
        <div class="modal-content">
            <h2>Générer un lien de partage</h2>
            <p class="share-info">Générer un lien de partage temporaire pour : <strong id="shareAlbumTitle"></strong></p>
            <form method="post" action="arbre-prive.php">
                <input type="hidden" name="action" value="generate_link">
                <input type="hidden" name="path" id="sharePath">
                <div class="form-group">
                    <label for="duration">Durée de validité :</label>
                    <select name="duration" id="duration" class="form-select" required>
                        <option value="1">1 heure</option>
                        <option value="6">6 heures</option>
                        <option value="12">12 heures</option>
                        <option value="24" selected>24 heures</option>
                        <option value="48">48 heures</option>
                        <option value="72">72 heures</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="comment">Commentaire (optionnel) :</label>
                    <textarea name="comment" id="comment" rows="3" class="form-textarea"
                        placeholder="Ex: Partage avec le client X"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button action-button-share">Générer le lien</button>
                </div>
            </form>
        </div>
    </div>

    <?php echo $this->renderTreeScripts(); ?>
    <script>
    function generateShareLink(path, title) {
        document.getElementById('sharePath').value = path;
        document.getElementById('shareAlbumTitle').textContent = title;
        document.getElementById('shareLinkModal').style.display = 'block';
    }

    function copyShareUrl(button) {
        const input = button.previousElementSibling;
        input.select();
        document.execCommand('copy');
        const originalText = button.innerHTML;
        button.innerHTML = '✓';
        button.classList.add('copied');
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('copied');
        }, 2000);
    }

    // Étendre closeModal pour la modale de partage
    const _closeModal = closeModal;
    function closeModal() {
        _closeModal();
        document.getElementById('shareLinkModal').style.display = 'none';
    }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
        <?php
    }

    private function renderTreeScripts(): string
    {
        return <<<'JS'
    <script>
    function createSubfolder(path) {
        document.getElementById('parentPath').value = path;
        document.getElementById('createFolderModal').style.display = 'block';
    }

    function editFolder(path, title, description, matureContent, moreInfoUrl, hasImages) {
        document.getElementById('editPath').value = path;
        document.getElementById('edit_name').value = decodeURIComponent(title);
        document.getElementById('edit_description').value = decodeURIComponent(description);
        document.getElementById('edit_mature_content').checked = matureContent;
        document.getElementById('edit_more_info_url').value = decodeURIComponent(moreInfoUrl);
        const field = document.getElementById('edit_more_info_url_field');
        const show  = hasImages === true || hasImages === 'true';
        if (field) {
            field.style.display = show ? 'block' : 'none';
            if (!show) document.getElementById('edit_more_info_url').value = '';
        }
        document.getElementById('editFolderModal').style.display = 'block';
    }

    function deleteFolder(path) {
        document.getElementById('deletePath').value = path;
        document.getElementById('deleteFolderModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('createFolderModal').style.display = 'none';
        document.getElementById('editFolderModal').style.display = 'none';
        document.getElementById('deleteFolderModal').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) closeModal();
    }
    </script>
    <button class="scroll-top" title="Retour en haut">↑</button>
    <script>
    const scrollBtn = document.querySelector('.scroll-top');
    window.addEventListener('scroll', () => {
        scrollBtn.style.display = window.scrollY > 500 ? 'flex' : 'none';
    });
    scrollBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    </script>
JS;
    }
}
