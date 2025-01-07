<?php
require_once 'fonctions.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php?action=login');
    exit;
}
checkAdminSession();

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
                    $_SESSION['success_message'] = "Dossier créé avec succès.";
                } else {
                    $_SESSION['error_message'] = "Ce dossier existe déjà.";
                }
            }
            break;
                
        case 'edit_folder':
            if ($path && isSecurePath($path)) {
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
            if ($path && isSecurePath($path) && $path !== './liste_albums') {
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
                $_SESSION['success_message'] = "Dossier supprimé avec succès.";
            }
            break;
    }
    
    header('Location: arbre.php');
    exit;
}

$currentPath = isset($_GET['path']) ? $_GET['path'] : './liste_albums';
$currentPath = realpath($currentPath);

if (!isSecurePath($currentPath)) {
    header('Location: arbre.php');
    exit;
}

function generateTree($path, $currentPath) {
    if (!is_dir($path)) return '';
    
    $output = '<ul class="tree-list">';
    
    // Si c'est le dossier racine, ajoutons-le à l'arborescence
    if ($path === './liste_albums') {
        $carouselPath = './img_carrousel';
        if (is_dir($carouselPath)) {
            $output .= '<li class="tree-item carousel-folder' . ($carouselPath === $currentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link">';
            $output .= '<span class="folder-icon">🎞️</span> Images du carrousel';
            $output .= '</span>';
            $output .= '<div class="tree-actions">';
            $output .= '<a href="arbre-img.php?path=' . urlencode($carouselPath) . '" class="tree-button carousel-button" title="Gérer les images">🖼️</a>';
            $output .= '</div></div></li>';
        }
        $info = getAlbumInfo($path);
        $output .= '<li class="tree-item root-folder' . ($path === $currentPath ? ' active' : '') . '">';
        $output .= '<div class="tree-item-content">';
        $output .= '<span class="tree-link">';
        $output .= '<span class="folder-icon">📁</span> ' . htmlspecialchars($info['title']);
        if ($info['mature_content']) {
            $output .= ' <span class="mature-warning">🔞</span>';
        }
        $output .= '</span>';
        $output .= '<div class="tree-actions">';
        $output .= '<button onclick="editFolder(\'' . htmlspecialchars($path) . '\', \'' . rawurlencode($info['title']) . '\', \'' . rawurlencode($info['description']) . '\', ' . ($info['mature_content'] ? 'true' : 'false') . ', \'' . rawurlencode($info['more_info_url']) . '\', ' . (hasImages($path) ? 'true' : 'false') . ')" class="tree-button">✏️</button>';
        $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($path) . '\')" class="tree-button">➕</button>';
        $output .= '</div></div>';
    }
    
    // Récupérer et trier les sous-dossiers
    $dirs = array();
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot()) continue;
        if ($item->isDir()) {
            $fullPath = $item->getPathname();
            $info = getAlbumInfo($fullPath);
            $dirs[$info['title']] = $fullPath;
        }
    }

    // Tri alphabétique par titre
    ksort($dirs, SORT_STRING | SORT_FLAG_CASE);

    // Parcourir les dossiers triés
    foreach ($dirs as $title => $fullPath) {
        $info = getAlbumInfo($fullPath);
        $isCurrentPath = realpath($fullPath) === $currentPath;
        $hasSubfolders = hasSubfolders($fullPath);
        
        $output .= '<li class="tree-item' . ($isCurrentPath ? ' active' : '') . '">';
        $output .= '<div class="tree-item-content">';
        $output .= '<span class="tree-link">';
        $output .= '<span class="folder-icon">📁</span> ' . htmlspecialchars($info['title']);
        if ($info['mature_content']) {
            $output .= ' <span class="mature-warning">🔞</span>';
        }
        $output .= '</span>';
        $output .= '<div class="tree-actions">';
        if (!$hasSubfolders) {
            $output .= '<a href="arbre-img.php?path=' . urlencode($fullPath) . '" class="tree-button" style="text-decoration: none">🖼️</a>';
        }
        if (!$hasSubfolders) {
            $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . '\', \'' 
                . rawurlencode($info['title']) . '\', \'' 
                . rawurlencode($info['description']) . '\', ' 
                . ($info['mature_content'] ? 'true' : 'false') . ', \'' 
                . rawurlencode($info['more_info_url']) . '\', ' 
                . (hasImages($fullPath) ? 'true' : 'false') 
                . ')" class="tree-button">✏️</button>';
        } else {
            $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . '\', \'' 
                . rawurlencode($info['title']) . '\', \'' 
                . rawurlencode($info['description']) . '\', ' 
                . ($info['mature_content'] ? 'true' : 'false') . ', \'\', false)" class="tree-button">✏️</button>';
        }
        if (!hasImages($fullPath)) {
            $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button">➕</button>';
        }
        if ($fullPath !== './liste_albums') {
            $output .= '<button onclick="deleteFolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button tree-button-danger">🗑️</button>';
        }
        $output .= '</div></div>';
        
        $output .= generateTree($fullPath, $currentPath);
        $output .= '</li>';
    }
    
    $output .= '</ul>';
    return $output;
}

$config = getSiteConfig();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arborescence - <?php echo htmlspecialchars($config['site_title']); ?></title>
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
            <?php echo generateTree('./liste_albums', $currentPath); ?>
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
        
        // Récupérer le champ URL
        const moreInfoUrlField = document.getElementById('edit_more_info_url_field');
        
        // Convertir hasImages en booléen explicitement
        const showUrlField = hasImages === true || hasImages === 'true';
        
        // Afficher ou masquer le champ URL
        if (moreInfoUrlField) {
            console.log('Found field, setting display to:', showUrlField ? 'block' : 'none');
            moreInfoUrlField.style.display = showUrlField ? 'block' : 'none';
            
            // Si le champ est masqué, on vide aussi sa valeur
            if (!showUrlField) {
                document.getElementById('edit_more_info_url').value = '';
            }
        } else {
            console.log('Field not found');
        }
        
        document.getElementById('editFolderModal').style.display = 'block';
        
        // Debug
        console.log('Edit folder:', {
            path,
            hasImages,
            showUrlField,
            fieldFound: !!moreInfoUrlField
        });
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
        if (event.target.classList.contains('modal')) {
            closeModal();
        }
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
    <?php include 'footer.php'; ?>
</body>
</html>