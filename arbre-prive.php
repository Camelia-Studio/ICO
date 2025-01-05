<?php
require_once 'fonctions.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php?action=login');
    exit;
}

// Gérer la génération de lien de partage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_link') {
    $albumPath = $_POST['path'] ?? '';
    $duration = intval($_POST['duration'] ?? 24);
    $comment = $_POST['comment'] ?? '';
    
    if ($albumPath && isSecurePrivatePath($albumPath)) {
        // Récupérer ou créer l'identifiant unique de l'album
        $albumIdentifier = ensureAlbumIdentifier($albumPath);
        
        if ($albumIdentifier) {
            // Créer la clé de partage
            $shareKey = createShareKey($albumIdentifier, $duration, $comment);
            
            if ($shareKey) {
                $shareUrl = getBaseUrl() . '/galeries-privees.php?key=' . urlencode($shareKey);
                $_SESSION['success_message'] = "Lien de partage généré avec succès. URL : " . $shareUrl;
                $_SESSION['share_url'] = $shareUrl;
            } else {
                $_SESSION['error_message'] = "Erreur lors de la génération du lien de partage.";
            }
        } else {
            $_SESSION['error_message'] = "Erreur lors de la création de l'identifiant de l'album.";
        }
    } else {
        $_SESSION['error_message'] = "Chemin d'album invalide.";
    }
    
    header('Location: arbre-prive.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $path = $_POST['path'] ?? '';
    $newName = $_POST['new_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $matureContent = isset($_POST['mature_content']) ? '18+' : '18-';
    
    switch ($action) {
        case 'create_folder':
            if ($path && $newName) {
                $newPath = $path . '/' . sanitizeFilename($newName);
                if (!file_exists($newPath)) {
                    $moreInfoUrl = $_POST['more_info_url'] ?? '';
                    mkdir($newPath, 0755, true);
                    $infoContent = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl;
                    file_put_contents($newPath . '/infos.txt', $infoContent);
                    $_SESSION['success_message'] = "Dossier privé créé avec succès.";
                } else {
                    $_SESSION['error_message'] = "Ce dossier existe déjà.";
                }
            }
            break;
                
        case 'edit_folder':
            if ($path && isSecurePrivatePath($path)) {
                $moreInfoUrl = $_POST['more_info_url'] ?? '';
                $infoContent = $newName . "\n" . $description . "\n" . $matureContent . "\n" . $moreInfoUrl;
                $infoPath = $path . '/infos.txt';
                if (file_put_contents($infoPath, $infoContent) !== false) {
                    $_SESSION['success_message'] = "Dossier modifié avec succès.";
                } else {
                    $_SESSION['error_message'] = "Erreur lors de la modification du dossier.";
                }
            }
            break;
            
        case 'delete_folder':
            if ($path && isSecurePrivatePath($path) && $path !== './liste_albums_prives') {
                function rrmdir($dir) {
                    if (is_dir($dir)) {
                        $objects = scandir($dir);
                        foreach ($objects as $object) {
                            if ($object != "." && $object != "..") {
                                if (is_dir($dir . "/" . $object)) {
                                    rrmdir($dir . "/" . $object);
                                } else {
                                    unlink($dir . "/" . $object);
                                }
                            }
                        }
                        rmdir($dir);
                    }
                }
                rrmdir($path);
                $_SESSION['success_message'] = "Dossier privé supprimé avec succès.";
            }
            break;
    }
    
    header('Location: arbre-prive.php');
    exit;
}

$currentPath = isset($_GET['path']) ? $_GET['path'] : './liste_albums_prives';
$currentPath = realpath($currentPath);

if (!isSecurePrivatePath($currentPath)) {
    header('Location: arbre-prive.php');
    exit;
}

// Créer le dossier racine s'il n'existe pas
if (!file_exists('./liste_albums_prives')) {
    mkdir('./liste_albums_prives', 0755, true);
    // Créer le fichier infos.txt pour le dossier racine
    $infoContent = "Albums privés\nVos albums photos privés\n18-\n";
    file_put_contents('./liste_albums_prives/infos.txt', $infoContent);
}

function generatePrivateTree($path, $currentPath) {
    if (!is_dir($path)) return '';
    
    $output = '<ul class="tree-list">';
    
    // Si c'est le dossier racine, ajoutons-le à l'arborescence
    if ($path === './liste_albums_prives') {
        $info = getAlbumInfo($path);
        $output .= '<li class="tree-item root-folder' . ($path === $currentPath ? ' active' : '') . '">';
        $output .= '<div class="tree-item-content">';
        $output .= '<span class="tree-link">';
        $output .= '<span class="folder-icon">🔒</span> ' . htmlspecialchars($info['title']);
        if ($info['mature_content']) {
            $output .= ' <span class="mature-warning">🔞</span>';
        }
        $output .= '</span>';
        $output .= '<div class="tree-actions">';
        $output .= '<button onclick="editFolder(\'' . htmlspecialchars($path) . '\', \'' . rawurlencode($info['title']) . '\', \'' . rawurlencode($info['description']) . '\', ' . ($info['mature_content'] ? 'true' : 'false') . ', \'' . rawurlencode($info['more_info_url']) . '\', ' . (hasImages($path) ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
        $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($path) . '\')" class="tree-button">➕</button>';
        $output .= '</div></div>';
    }
    
    // Parcourir tous les sous-dossiers
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot()) continue;
        if ($item->isDir()) {
            $fullPath = $item->getPathname();
            $info = getAlbumInfo($fullPath);
            $isCurrentPath = realpath($fullPath) === $currentPath;
            $hasSubfolders = hasSubfolders($fullPath);
            $hasImages = hasImages($fullPath);
            
            $output .= '<li class="tree-item' . ($isCurrentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link">';
            $output .= '<span class="folder-icon">🔒</span> ' . htmlspecialchars($info['title']);
            if ($info['mature_content']) {
                $output .= ' <span class="mature-warning">🔞</span>';
            }
            $output .= '</span>';
            $output .= '<div class="tree-actions">';
            if (!$hasSubfolders) {
                $output .= '<a href="arbre-img-prive.php?path=' . urlencode($fullPath) . '&private=1" class="tree-button" style="text-decoration: none">🖼️</a>';
                // Ajout du bouton de génération de lien si le dossier contient des images
                if ($hasImages) {
                    $output .= '<button onclick="generateShareLink(\'' . htmlspecialchars($fullPath) . '\', \'' 
                        . htmlspecialchars($info['title']) 
                        . '\')" class="tree-button tree-button-share" title="Générer un lien de partage">🔗</button>';
                }
            }
            if (!$hasSubfolders) {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . '\', \'' 
                    . rawurlencode($info['title']) . '\', \'' 
                    . rawurlencode($info['description']) . '\', ' 
                    . ($info['mature_content'] ? 'true' : 'false') . ', \'' 
                    . rawurlencode($info['more_info_url']) . '\', ' 
                    . ($hasImages ? 'true' : 'false') 
                    . ')" class="tree-button">✏️</button>';
            } else {
                $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . '\', \'' 
                    . rawurlencode($info['title']) . '\', \'' 
                    . rawurlencode($info['description']) . '\', ' 
                    . ($info['mature_content'] ? 'true' : 'false') . ', \'\', false)" class="tree-button">✏️</button>';
            }
            if (!$hasImages) {
                $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button">➕</button>';
            }
            if ($fullPath !== './liste_albums_prives') {
                $output .= '<button onclick="deleteFolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button tree-button-danger">🗑️</button>';
            }
            $output .= '</div></div>';
            
            $output .= generatePrivateTree($fullPath, $currentPath);
            $output .= '</li>';
        }
    }
    
    $output .= '</ul>';
    return $output;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Albums privés - ICO</title>
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
            <?php if (isset($_SESSION['share_url'])): ?>
                <div class="share-url-container">
                    <input type="text" value="<?php echo htmlspecialchars($_SESSION['share_url']); ?>" 
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
            <?php echo generatePrivateTree('./liste_albums_prives', $currentPath); ?>
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

    <!-- Ajouter la nouvelle modale pour la génération de lien -->
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

    <script>
    function createSubfolder(path) {
        document.getElementById('parentPath').value = path;
        document.getElementById('create_more_info_url_field').style.display = 'none';
        document.getElementById('createFolderModal').style.display = 'block';
    }

    function editFolder(path, title, description, matureContent = false, moreInfoUrl = '', hasImages = false) {
        document.getElementById('editPath').value = path;
        document.getElementById('edit_name').value = decodeURIComponent(title);
        document.getElementById('edit_description').value = decodeURIComponent(description);
        document.getElementById('edit_mature_content').checked = matureContent;
        document.getElementById('edit_more_info_url').value = decodeURIComponent(moreInfoUrl);
        
        const moreInfoUrlField = document.getElementById('edit_more_info_url_field');
        const showUrlField = hasImages === true || hasImages === 'true';
        
        if (moreInfoUrlField) {
            moreInfoUrlField.style.display = showUrlField ? 'block' : 'none';
            if (!showUrlField) {
                document.getElementById('edit_more_info_url').value = '';
            }
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
        document.getElementById('shareLinkModal').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal();
        }
    }

    function generateShareLink(path, title) {
    document.getElementById('sharePath').value = path;
    document.getElementById('shareAlbumTitle').textContent = title;
    document.getElementById('shareLinkModal').style.display = 'block';
    }

    function copyShareUrl(button) {
    const input = button.previousElementSibling;
    input.select();
    document.execCommand('copy');
    
    // Feedback visuel
    const originalText = button.innerHTML;
    button.innerHTML = '✓';
    button.classList.add('copied');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('copied');
    }, 2000);
    }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>