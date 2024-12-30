<?php
require_once 'fonctions.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
  header('Location: admin.php?action=login');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $path = $_POST['path'] ?? '';
  $newName = $_POST['new_name'] ?? '';
  $description = $_POST['description'] ?? '';
  
  switch ($action) {
      case 'create_folder':
          if ($path && $newName) {
              $newPath = $path . '/' . sanitizeFilename($newName);
              if (!file_exists($newPath)) {
                  mkdir($newPath, 0755, true);
                  $infoContent = $newName . "\n" . $description;
                  file_put_contents($newPath . '/infos.txt', $infoContent);
                  $_SESSION['success_message'] = "Dossier créé avec succès.";
              } else {
                  $_SESSION['error_message'] = "Ce dossier existe déjà.";
              }
          }
          break;
          
      case 'edit_folder':
          if ($path && isSecurePath($path)) {
              $infoContent = $newName . "\n" . $description;
              $infoPath = $path . '/infos.txt';
              if (file_put_contents($infoPath, $infoContent) !== false) {
                  $_SESSION['success_message'] = "Dossier modifié avec succès.";
              } else {
                  $_SESSION['error_message'] = "Erreur lors de la modification du dossier.";
              }
          }
          break;
          
      case 'delete_folder':
          if ($path && isSecurePath($path) && $path !== './liste_albums') { // Empêcher la suppression du dossier racine
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
        $info = getAlbumInfo($path);
        $output .= '<li class="tree-item root-folder' . ($path === $currentPath ? ' active' : '') . '">';
        $output .= '<div class="tree-item-content">';
        $output .= '<span class="tree-link">';
        $output .= '<span class="folder-icon">📁</span> ' . htmlspecialchars($info['title']);
        $output .= '</span>';
        $output .= '<div class="tree-actions">';
        $output .= '<button onclick="editFolder(\'' . htmlspecialchars($path) . '\', \'' . htmlspecialchars($info['title']) . '\', \'' . htmlspecialchars($info['description']) . '\')" class="tree-button">✏️</button>';
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
            
            $output .= '<li class="tree-item' . ($isCurrentPath ? ' active' : '') . '">';
            $output .= '<div class="tree-item-content">';
            $output .= '<span class="tree-link">';
            $output .= '<span class="folder-icon">📁</span> ' . htmlspecialchars($info['title']);
            $output .= '</span>';
            $output .= '<div class="tree-actions">';
            if (!$hasSubfolders) {
                $output .= '<a href="arbre-img.php?path=' . urlencode($fullPath) . '" class="tree-button" style="text-decoration: none">🖼️</a>';
            }
            $output .= '<button onclick="editFolder(\'' . htmlspecialchars($fullPath) . '\', \'' . htmlspecialchars($info['title']) . '\', \'' . htmlspecialchars($info['description']) . '\')" class="tree-button">✏️</button>';
            $output .= '<button onclick="createSubfolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button">➕</button>';
            if ($fullPath !== './liste_albums') {
                $output .= '<button onclick="deleteFolder(\'' . htmlspecialchars($fullPath) . '\')" class="tree-button tree-button-danger">🗑️</button>';
            }
            $output .= '</div></div>';
            
            $output .= generateTree($fullPath, $currentPath);
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
    <title>Arborescence - ICO</title>
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
        document.getElementById('createFolderModal').style.display = 'block';
    }

    function editFolder(path, title, description) {
        document.getElementById('editPath').value = path;
        document.getElementById('edit_name').value = title;
        document.getElementById('edit_description').value = description;
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
        if (event.target.classList.contains('modal')) {
            closeModal();
        }
    }
    </script>
</body>
</html>