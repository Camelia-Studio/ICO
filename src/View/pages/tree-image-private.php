<?php
/**
 * Vue : gestion des images d'un album privé (arbre-img-prive.php).
 *
 * Variables :
 *   string                      $siteTitle    Titre du site
 *   string                      $backUrl      URL du bouton "Retour" (parent dans l'arbre)
 *   array<string,mixed>         $albumInfo    Infos de l'album (title, description, …)
 *   array<int, array{name: string, url: string, isTop: bool}> $imageData  Images pré-calculées
 *   string                      $version      Version de l'application (pour le footer)
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string $siteTitle */
/** @var string $backUrl */
/** @var array<string,mixed> $albumInfo */
/** @var array<int, array{name: string, url: string, isTop: bool, shareUrl: string}> $imageData */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Gestion des images privées - ' . $siteTitle,
]); ?>
    <div class="admin-header">
        <h1>
            Images de : <?php echo htmlspecialchars($albumInfo['title']); ?>
            <span class="private-badge">Privé</span>
            <span class="image-count-total"><?php echo count($imageData); ?> illustration<?php echo count($imageData) !== 1 ? 's' : ''; ?></span>
        </h1>
        <div class="admin-actions">
            <button onclick="document.getElementById('imageUploadForm').click()" class="action-button action-button-success">
                Ajouter des images
            </button>
            <button onclick="deleteSelected()" id="deleteSelectedBtn" class="action-button action-button-danger" style="display:none">
                Supprimer la sélection
            </button>
            <button onclick="toggleSelectAll()" id="selectAllBtn" class="action-button">
                Tout sélectionner
            </button>
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="action-button action-button-secondary">
                Retour
            </a>
        </div>
    </div>

    <div class="admin-content">
        <?php $renderer->renderLayout('partials/messages', ['nlBr' => true]); ?>

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
                <?php foreach ($imageData as $item): ?>
                    <div class="image-item">
                        <input type="checkbox" name="images[]" value="<?php echo htmlspecialchars($item['name']); ?>"
                               class="image-checkbox" onchange="updateActionButtons()">
                        <div class="image-wrapper">
                            <a href="<?php echo htmlspecialchars($item['shareUrl']); ?>" target="_blank" class="image-share-link">
                                <img src="<?php echo htmlspecialchars($item['url']); ?>"
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" loading="lazy">
                            </a>
                            <div class="image-actions">
                                <button type="button" onclick="toggleTop('<?php echo htmlspecialchars($item['name']); ?>')"
                                    class="tree-button <?php echo $item['isTop'] ? 'tree-button-top' : ''; ?>"
                                    title="<?php echo $item['isTop'] ? 'Retirer des tops' : 'Mettre en top'; ?>">
                                    ⭐
                                </button>
                                <button type="button" onclick="deleteImage('<?php echo htmlspecialchars($item['name']); ?>')"
                                    class="tree-button tree-button-danger">🗑️</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <!-- Modale de téléversement -->
    <div id="uploadModal" class="modal-upload">
        <div class="modal-content">
            <div class="spinner"></div>
            <p>Téléversement en cours... veuillez patienter...</p>
        </div>
    </div>

    <button class="scroll-top" title="Retour en haut">↑</button>
    <script src="js/tree-image.js"></script>
    <script src="js/scroll-top.js"></script>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
