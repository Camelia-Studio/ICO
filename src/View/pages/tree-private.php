<?php
/**
 * Vue : arborescence des albums privés (arbre-prive.php).
 *
 * Variables :
 *   string      $siteTitle   Titre du site
 *   string      $tree        HTML de l'arborescence (généré par TreeController)
 *   string|null $shareUrl    URL de partage générée (null si aucune)
 *   string      $treeScripts Bloc <script> commun aux pages arbre
 *   string      $version     Version de l'application (pour le footer)
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string $siteTitle */
/** @var string $tree */
/** @var string|null $shareUrl */
/** @var string $treeScripts */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Albums privés - ' . $siteTitle,
]); ?>
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

    <?php echo $treeScripts; ?>
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

    function closeModal() {
        document.getElementById('createFolderModal').style.display = 'none';
        document.getElementById('editFolderModal').style.display = 'none';
        document.getElementById('deleteFolderModal').style.display = 'none';
        document.getElementById('shareLinkModal').style.display = 'none';
    }
    </script>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
