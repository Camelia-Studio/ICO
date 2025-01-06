<?php
require_once 'fonctions.php';

// Vérifier l'authentification
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php?action=login');
    exit;
}

// Récupérer le chemin courant
$currentPath = isset($_GET['path']) ? $_GET['path'] : './liste_albums_prives';
$currentPath = realpath($currentPath);

// Vérification de sécurité
if (!isSecurePrivatePath($currentPath)) {
    header('Location: arbre-prive.php');
    exit;
}

// Gérer l'upload des images
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload':
                $uploadedFiles = $_FILES['images'] ?? [];
                $successCount = 0;
                $errors = [];

                // Gérer les uploads multiples
                for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                    if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $uploadedFiles['tmp_name'][$i];
                        $fileName = sanitizeFilename($uploadedFiles['name'][$i]);
                        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                        // Vérifier l'extension
                        if (in_array($extension, ALLOWED_EXTENSIONS)) {
                            $destination = $currentPath . '/' . $fileName;
                            
                            // Vérifier si le fichier existe déjà
                            if (file_exists($destination)) {
                                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                                $counter = 1;
                                while (file_exists($destination)) {
                                    $fileName = $baseName . '_' . $counter . '.' . $extension;
                                    $destination = $currentPath . '/' . $fileName;
                                    $counter++;
                                }
                            }

                            if (move_uploaded_file($tmpName, $destination)) {
                                $successCount++;
                            } else {
                                $errors[] = "Erreur lors du déplacement de $fileName";
                            }
                        } else {
                            $errors[] = "Extension non autorisée pour $fileName";
                        }
                    }
                }

                if ($successCount > 0) {
                    $_SESSION['success_message'] = "$successCount image(s) téléversée(s) avec succès.";
                }
                if (!empty($errors)) {
                    $_SESSION['error_message'] = implode("\n", $errors);
                }
                break;
            
                case 'toggle_top':
                    $image = $_POST['image'] ?? '';
                    if ($image) {
                        $imagePath = $currentPath . '/' . basename($image);
                        if (isSecurePrivatePath($imagePath) && file_exists($imagePath)) {
                            $info = pathinfo($imagePath);
                            $isTop = strpos($info['filename'], '--top--') !== false;
                            
                            if ($isTop) {
                                // Enlever le tag top
                                $newName = str_replace('--top--', '', $info['filename']) . '.' . $info['extension'];
                            } else {
                                // Ajouter le tag top
                                $newName = $info['filename'] . '--top--.' . $info['extension'];
                            }
                            
                            $newPath = $currentPath . '/' . $newName;
                            if (rename($imagePath, $newPath)) {
                                $_SESSION['success_message'] = $isTop ? "Image retirée des tops." : "Image mise en top.";
                            } else {
                                $_SESSION['error_message'] = "Erreur lors de la modification du statut top.";
                            }
                        }
                    }
                    break;
            
            case 'delete':
                $images = $_POST['images'] ?? [];
                $deleteCount = 0;

                foreach ($images as $image) {
                    $imagePath = $currentPath . '/' . basename($image);
                    if (isSecurePrivatePath($imagePath) && file_exists($imagePath)) {
                        if (unlink($imagePath)) {
                            $deleteCount++;
                        }
                    }
                }

                if ($deleteCount > 0) {
                    $_SESSION['success_message'] = "$deleteCount image(s) supprimée(s).";
                }
                break;
        }
    }
    header('Location: arbre-img-prive.php?path=' . urlencode($currentPath));
    exit;
}

// Récupérer les images du dossier courant
$images = [];
$tempImages = [];
foreach (new DirectoryIterator($currentPath) as $file) {
    if ($file->isDot()) continue;
    if ($file->isFile()) {
        $extension = strtolower($file->getExtension());
        if (in_array($extension, ALLOWED_EXTENSIONS)) {
            $tempImages[] = [
                'name' => $file->getFilename(),
                'time' => $file->getCTime()
            ];
        }
    }
}

// Trier par date de création décroissante
usort($tempImages, function($a, $b) {
    return $b['time'] - $a['time'];
});

// Extraire uniquement les noms de fichiers
$images = array_map(function($img) {
    return $img['name'];
}, $tempImages);

$currentAlbumInfo = getAlbumInfo($currentPath);
$config = getSiteConfig();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des images privées - <?php echo htmlspecialchars($config['site_title']); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
    <div class="admin-header">
        <h1>
            Images de : <?php echo htmlspecialchars($currentAlbumInfo['title']); ?>
            <span class="private-badge">Privé</span>
        </h1>
        <div class="admin-actions">
            <button onclick="document.getElementById('imageUploadForm').click()" class="action-button action-button-success">
                Ajouter des images
            </button>
            <button onclick="deleteSelected()" id="deleteSelectedBtn" class="action-button action-button-danger">
                Supprimer la sélection
            </button>
            <a href="arbre-prive.php?path=<?php echo urlencode($currentPath); ?>" class="action-button action-button-secondary">
                Retour
            </a>
        </div>
    </div>

    <div class="admin-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success-message"><?php echo nl2br(htmlspecialchars($_SESSION['success_message'])); ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message"><?php echo nl2br(htmlspecialchars($_SESSION['error_message'])); ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="upload-zone" id="dropZone">
            <p>Glissez-déposez vos images ici ou cliquez sur "Ajouter des images"</p>
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                <input type="file" name="images[]" id="imageUploadForm" multiple accept=".jpg,.jpeg,.png,.gif">
            </form>
        </div>

        <form method="post" id="imagesForm">
            <input type="hidden" name="action" id="formAction" value="">
            <div class="images-grid">
                <?php foreach($images as $image):
                    $imagePath = str_replace('\\', '/', substr($currentPath, strpos($currentPath, '/liste_albums_prives/') + strlen('/liste_albums_prives/')));
                    $imageUrl = getBaseUrl() . '/liste_albums_prives/' . ($imagePath ? $imagePath . '/' : '') . $image;
                ?>
                    <div class="image-item">
                        <input type="checkbox" name="images[]" value="<?php echo htmlspecialchars($image); ?>" 
                               class="image-checkbox" onchange="updateActionButtons()">
                        <div class="image-wrapper">
                            <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                 alt="<?php echo htmlspecialchars($image); ?>" loading="lazy">
                            <div class="image-actions">
                                <?php 
                                $isTop = strpos($image, '--top--') !== false;
                                ?>
                                <button type="button" onclick="toggleTop('<?php echo htmlspecialchars($image); ?>')" 
                                    class="tree-button <?php echo $isTop ? 'tree-button-top' : ''; ?>" 
                                    title="<?php echo $isTop ? 'Retirer des tops' : 'Mettre en top'; ?>">
                                    ⭐
                                </button>
                                <button type="button" onclick="deleteImage('<?php echo htmlspecialchars($image); ?>')" 
                                    class="tree-button tree-button-danger">🗑️</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <!-- Modale d'indication de téléversement -->
    <div id="uploadModal" class="modal-upload">
        <div class="modal-content">
            <div class="spinner"></div>
            <p>Téléversement en cours... veuillez patienter...</p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('uploadModal');
        const dropZone = document.getElementById('dropZone');
        const uploadForm = document.getElementById('uploadForm');
        const imageUploadForm = document.getElementById('imageUploadForm');

        // Gestion du formulaire d'upload
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                const fileInput = this.querySelector('input[type="file"]');
                if (fileInput && fileInput.files && fileInput.files.length > 0) {
                    modal.style.display = 'block';
                }
            });
        }

        // Gestion du drag & drop
        if (dropZone) {
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('drag-over');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const dataTransfer = new DataTransfer();
                    for (let file of files) {
                        dataTransfer.items.add(file);
                    }
                    imageUploadForm.files = dataTransfer.files;
                    modal.style.display = 'block';  // Afficher la modale avant la soumission
                    uploadForm.submit();
                }
            });
        }

    // Gestion du click sur "Ajouter des images"
        const imageUploadInput = document.getElementById('imageUploadForm');
        if (imageUploadInput) {
            imageUploadInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    modal.style.display = 'block';
                    uploadForm.submit();
                }
            });
        }
    });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>