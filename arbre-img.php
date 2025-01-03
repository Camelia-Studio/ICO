<?php
require_once 'fonctions.php';

// Vérifier l'authentification
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php?action=login');
    exit;
}

// Récupérer le chemin courant
$currentPath = isset($_GET['path']) ? $_GET['path'] : './liste_albums';
$currentPath = realpath($currentPath);

// Vérification de sécurité
if (!isSecurePath($currentPath)) {
    header('Location: arbre.php');
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
                        if (isSecurePath($imagePath) && file_exists($imagePath)) {
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
                    if (isSecurePath($imagePath) && file_exists($imagePath)) {
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
    header('Location: arbre-img.php?path=' . urlencode($currentPath));
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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des images - ICO</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page" data-page="<?php echo strpos($currentPath, 'img_carrousel') !== false ? 'carrousel' : 'default'; ?>">
    <div class="admin-header">
    <h1>
            <?php
            if (strpos($currentPath, 'img_carrousel') !== false) {
                echo 'Images du carrousel';
            } else {
                $currentAlbumInfo = getAlbumInfo($currentPath);
                echo 'Images de : ' . htmlspecialchars($currentAlbumInfo['title']);
            }
            ?></h1>
        <div class="admin-actions">
            <button onclick="document.getElementById('imageUploadForm').click()" class="action-button action-button-success">
                Ajouter des images
            </button>
            <button onclick="deleteSelected()" id="deleteSelectedBtn" style="display: none;" class="action-button action-button-danger">
                Supprimer la sélection
            </button>
            <a href="arbre.php?path=<?php echo urlencode($currentPath); ?>" class="action-button action-button-secondary">
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

        <form method="post" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <div class="images-grid">
                <?php foreach($images as $image): 
                    $isCarousel = strpos($currentPath, 'img_carrousel') !== false;
                    
                    if ($isCarousel) {
                        $imageUrl = getBaseUrl() . '/img_carrousel/' . $image;
                    } else {
                        $imageUrl = getBaseUrl() . '/liste_albums/' . substr($currentPath, strpos($currentPath, '/liste_albums/') + strlen('/liste_albums/')) . '/' . $image;
                    }
                ?>
                    <div class="image-item">
                        <input type="checkbox" name="images[]" value="<?php echo htmlspecialchars($image); ?>" 
                            class="image-checkbox" onchange="updateDeleteButton()">
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

    <script>
        // Gestion du drag & drop
        const dropZone = document.getElementById('dropZone');
        const uploadForm = document.getElementById('uploadForm');
        const imageUploadForm = document.getElementById('imageUploadForm');

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
        // Créer un objet DataTransfer
        const dataTransfer = new DataTransfer();
        // Ajouter les fichiers
        for (let file of files) {
            dataTransfer.items.add(file);
        }
        // Mettre à jour l'input
        imageUploadForm.files = dataTransfer.files;
        // Soumettre le formulaire
        uploadForm.submit();
    }
});
        // Fonction de mise en top
        function toggleTop(imageName) {
            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_top">
                <input type="hidden" name="image" value="${imageName}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Gestion de la suppression
        function deleteImage(imageName) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
                const form = document.getElementById('deleteForm');
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="images[]" value="${imageName}">
                `;
                form.submit();
            }
        }

        function updateDeleteButton() {
            const checkboxes = document.querySelectorAll('.image-checkbox:checked');
            const deleteBtn = document.getElementById('deleteSelectedBtn');
            deleteBtn.style.display = checkboxes.length > 0 ? 'inline-flex' : 'none';
        }

        function deleteSelected() {
            const checkboxes = document.querySelectorAll('.image-checkbox:checked');
            if (checkboxes.length > 0 && confirm('Êtes-vous sûr de vouloir supprimer les images sélectionnées ?')) {
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
