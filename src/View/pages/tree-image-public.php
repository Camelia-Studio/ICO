<?php
/**
 * Vue : gestion des images d'un album public (arbre-img.php).
 *
 * Variables :
 *   string   $siteTitle      Titre du site
 *   string   $backUrl        URL du bouton "Retour" (parent dans l'arbre)
 *   string[] $images         Noms des fichiers images
 *   string   $pageTitle      Titre H1 de la page
 *   string   $folderOptions  HTML des <option> pour la modale de déplacement
 *   bool     $isCarousel     Le dossier courant est le carrousel
 *   string   $version        Version de l'application (pour le footer)
 *
 * Note : buildPublicImageUrl() est appelée directement sur $this dans la vue
 * car $renderer n'expose que renderLayout(). L'URL est donc pré-calculée
 * dans le controller et passée via $imageData.
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string $siteTitle */
/** @var string $backUrl */
/** @var array<int, array{name: string, url: string, isTop: bool}> $imageData */
/** @var string $pageTitle */
/** @var string $folderOptions */
/** @var bool $isCarousel */
/** @var string|null $galleryUrl */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Gestion des images - ' . $siteTitle,
    'dataPage'  => $isCarousel ? 'carrousel' : 'default',
]); ?>
    <div class="admin-header">
        <h1><?php echo $pageTitle; ?></h1>
        <div class="admin-actions">
            <button onclick="document.getElementById('imageUploadForm').click()" class="action-button action-button-success">
                Ajouter des images
            </button>
            <button onclick="deleteSelected()" id="deleteSelectedBtn" class="action-button action-button-danger" style="display:none">
                Supprimer la sélection
            </button>
            <button onclick="moveSelected()" id="moveSelectedBtn" class="action-button action-button-warning" style="display:none">
                Déplacer la sélection
            </button>
            <button onclick="toggleSelectAll()" id="selectAllBtn" class="action-button">
                Tout sélectionner
            </button>
            <?php if ($galleryUrl !== null): ?>
                <a href="<?php echo htmlspecialchars($galleryUrl); ?>" class="action-button action-button-secondary" target="_blank">
                    Voir en mode public
                </a>
            <?php endif; ?>
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
                            <img src="<?php echo htmlspecialchars($item['url']); ?>"
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" loading="lazy">
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
                        <?php echo $folderOptions; ?>
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

    <!-- Modale de téléversement -->
    <div id="uploadModal" class="modal-upload">
        <div class="modal-content">
            <div class="spinner"></div>
            <p>Téléversement en cours... veuillez patienter...</p>
        </div>
    </div>

    <button class="scroll-top" title="Retour en haut">↑</button>
    <script src="js/tree-image.js"></script>
    <script src="js/tree-image-move.js"></script>
    <script src="js/scroll-top.js"></script>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
