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

                case 'move':
                    $images = $_POST['images'] ?? [];
                    $destinationPath = $_POST['destination_path'] ?? '';
                    $moveCount = 0;
                    $errors = [];
    
                    // Vérifier que le dossier de destination existe et est valide
                    if (!empty($destinationPath) && is_dir($destinationPath) && isSecurePath($destinationPath)) {
                        foreach ($images as $image) {
                            $sourcePath = $currentPath . '/' . basename($image);
                            $destPath = $destinationPath . '/' . basename($image);
                            
                            // Vérifier que le fichier source existe et est dans un chemin sécurisé
                            if (file_exists($sourcePath) && isSecurePath($sourcePath)) {
                                // Vérifier si un fichier du même nom existe déjà dans la destination
                                if (file_exists($destPath)) {
                                    $info = pathinfo($destPath);
                                    $i = 1;
                                    while (file_exists($destPath)) {
                                        $destPath = $destinationPath . '/' . $info['filename'] . '_' . $i . '.' . $info['extension'];
                                        $i++;
                                    }
                                }
                                
                                if (rename($sourcePath, $destPath)) {
                                    $moveCount++;
                                } else {
                                    $errors[] = "Erreur lors du déplacement de " . basename($image);
                                }
                            }
                        }
    
                        if ($moveCount > 0) {
                            $_SESSION['success_message'] = "$moveCount image(s) déplacée(s) avec succès.";
                        }
                        if (!empty($errors)) {
                            $_SESSION['error_message'] = implode("\n", $errors);
                        }
                    } else {
                        $_SESSION['error_message'] = "Dossier de destination invalide.";
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
            ?>
        </h1>
        <div class="admin-actions">
            <button onclick="document.getElementById('imageUploadForm').click()" class="action-button action-button-success">
                Ajouter des images
            </button>
            <button onclick="deleteSelected()" id="deleteSelectedBtn" class="action-button action-button-danger">
                Supprimer la sélection
            </button>
            <button onclick="moveSelected()" id="moveSelectedBtn" class="action-button action-button-warning">
                Déplacer la sélection
            </button>
            <a href="arbre.php?path=<?php echo urlencode($currentPath); ?>" class="action-button action-button-secondary">
                Retour
            </a>
        </div>
    </div>

            <!-- Scripts -->
        <script>
            // Fonctions de gestion des sélections
            function updateActionButtons() {
                const checkboxes = document.querySelectorAll('.image-checkbox:checked');
                const count = checkboxes.length;
                
                const deleteBtn = document.getElementById('deleteSelectedBtn');
                const moveBtn = document.getElementById('moveSelectedBtn');
                
                if (count > 0) {
                    deleteBtn.style.display = 'inline-flex';
                    moveBtn.style.display = 'inline-flex';
                } else {
                    deleteBtn.style.display = 'none';
                    moveBtn.style.display = 'none';
                }
            }

            // Fonction de suppression multiple
            function deleteSelected() {
                const checkboxes = document.querySelectorAll('.image-checkbox:checked');
                if (checkboxes.length > 0 && confirm('Êtes-vous sûr de vouloir supprimer les images sélectionnées ?')) {
                    document.getElementById('formAction').value = 'delete';
                    document.getElementById('imagesForm').submit();
                }
            }

            // Fonction de déplacement
            function moveSelected() {
                const checkboxes = document.querySelectorAll('.image-checkbox:checked');
                if (checkboxes.length === 0) return;
                
                const container = document.getElementById('selected-images-container');
                container.innerHTML = '';
                
                checkboxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'images[]';
                    input.value = checkbox.value;
                    container.appendChild(input);
                });
                
                document.getElementById('moveFolderModal').style.display = 'block';
            }

            // Fonction de suppression d'une seule image
            function deleteImage(imageName) {
                if (confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
                    const form = document.getElementById('imagesForm');
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="images[]" value="${imageName}">
                    `;
                    form.submit();
                }
            }

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

            // Gestion des modales
            function closeModal(modalId) {
                document.getElementById(modalId).style.display = 'none';
            }

            // Gestion des clics sur les modales
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                }
            }

            // Initialisation
            document.addEventListener('DOMContentLoaded', function() {
                // Masquer les boutons au démarrage
                updateActionButtons();

                // Gestion du drag & drop
                const dropZone = document.getElementById('dropZone');
                const uploadForm = document.getElementById('uploadForm');
                const imageUploadForm = document.getElementById('imageUploadForm');

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
                            uploadForm.submit();
                        }
                    });
                }
            });
        </script>

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
                    $isCarousel = strpos($currentPath, 'img_carrousel') !== false;
                    
                    if ($isCarousel) {
                        $imageUrl = getBaseUrl() . '/img_carrousel/' . $image;
                    } else {
                        $imageUrl = getBaseUrl() . '/liste_albums/' . substr($currentPath, strpos($currentPath, '/liste_albums/') + strlen('/liste_albums/')) . '/' . $image;
                    }
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

    <!-- Modal de déplacement -->
    <div id="moveFolderModal" class="modal">
        <div class="modal-content">
            <h2>Déplacer les images</h2>
            <form method="post" id="moveForm">
                <input type="hidden" name="action" value="move">
                <div class="form-group">
                    <label for="destination_path">Choisir le dossier de destination :</label>
                    <select name="destination_path" id="destination_path" class="form-select" required>
                        <option value="">Sélectionner un dossier...</option>
                        <?php echo generateFolderList('./liste_albums', $currentPath); ?>
                    </select>
                </div>
                <div id="selected-images-container"></div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('moveFolderModal')" 
                            class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button action-button-warning">Déplacer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Gestion des boutons d'action
        console.log("Définition de updateActionButtons");
        function updateActionButtons() {
            console.log("updateActionButtons appelé");
            const checkboxes = document.querySelectorAll('.image-checkbox:checked');
            const count = checkboxes.length;
            console.log("Nombre d'images sélectionnées:", count);
            
            const deleteBtn = document.getElementById('deleteSelectedBtn');
            const moveBtn = document.getElementById('moveSelectedBtn');
            
            if (!deleteBtn || !moveBtn) {
                console.log("Boutons non trouvés");
                return;
            }

            if (count > 0) {
                deleteBtn.style.display = 'inline-flex';
                moveBtn.style.display = 'inline-flex';
                console.log("Affichage des boutons");
            } else {
                deleteBtn.style.display = 'none';
                moveBtn.style.display = 'none';
                console.log("Masquage des boutons");
            }
        }

        // Fonction de suppression d'une seule image
        function deleteImage(imageName) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
                const form = document.getElementById('imagesForm');
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="images[]" value="${imageName}">
                `;
                form.submit();
            }
        }

        // Fonction de suppression multiple
        function deleteSelected() {
            const checkboxes = document.querySelectorAll('.image-checkbox:checked');
            if (checkboxes.length > 0 && confirm('Êtes-vous sûr de vouloir supprimer les images sélectionnées ?')) {
                document.getElementById('formAction').value = 'delete';
                document.getElementById('imagesForm').submit();
            }
        }

        // Fonction de déplacement
        function moveSelected() {
            const checkboxes = document.querySelectorAll('.image-checkbox:checked');
            if (checkboxes.length === 0) return;
            
            const container = document.getElementById('selected-images-container');
            container.innerHTML = '';
            
            checkboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'images[]';
                input.value = checkbox.value;
                container.appendChild(input);
            });
            
            document.getElementById('moveFolderModal').style.display = 'block';
        }

        // Gestion des modales
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

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
                const dataTransfer = new DataTransfer();
                for (let file of files) {
                    dataTransfer.items.add(file);
                }
                imageUploadForm.files = dataTransfer.files;
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

        // Initialisation : masquer les boutons au chargement
        document.addEventListener('DOMContentLoaded', updateActionButtons);
    </script>
    <!-- Modal de déplacement -->
    <div id="moveFolderModal" class="modal">
        <div class="modal-content">
            <h2>Déplacer les images</h2>
            <form method="post" id="moveForm">
                <input type="hidden" name="action" value="move">
                <div class="form-group">
                    <label for="destination_path">Choisir le dossier de destination :</label>
                    <select name="destination_path" id="destination_path" class="form-select" required>
                        <option value="">Sélectionner un dossier...</option>
                        <?php 
                        function generateFolderList($path, $currentPath, $level = 0) {
                            if (!is_dir($path)) return '';
                            
                            $output = '';
                            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                            
                            foreach (new DirectoryIterator($path) as $item) {
                                if ($item->isDot()) continue;
                                if ($item->isDir()) {
                                    $fullPath = $item->getPathname();
                                    // Ne pas afficher le dossier courant ni ses sous-dossiers
                                    if (strpos($fullPath, $currentPath) === 0) continue;
                                    // Ne pas afficher le dossier du carrousel
                                    if (strpos($fullPath, './img_carrousel') === 0) continue;
                                    
                                    if (!hasSubfolders($fullPath)) {
                                        $info = getAlbumInfo($fullPath);
                                        $output .= '<option value="' . htmlspecialchars($fullPath) . '">' 
                                            . $indent . htmlspecialchars($info['title']) 
                                            . '</option>';
                                    }
                                    $output .= generateFolderList($fullPath, $currentPath, $level + 1);
                                }
                            }
                            
                            return $output;
                        }
                        echo generateFolderList('./liste_albums', $currentPath); 
                        ?>
                    </select>
                </div>
                <div id="selected-images-container"></div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('moveFolderModal')" 
                            class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button">Déplacer</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
