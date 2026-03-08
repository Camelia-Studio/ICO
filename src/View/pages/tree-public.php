<?php
/**
 * Vue : arborescence des albums publics (arbre.php).
 *
 * Variables :
 *   string $siteTitle   Titre du site
 *   string $tree        HTML de l'arborescence (généré par TreeController)
 *   string $treeScripts Bloc <script> commun aux pages arbre
 *   string $version     Version de l'application (pour le footer)
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string $siteTitle */
/** @var string $tree */
/** @var string $treeScripts */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Arborescence - ' . $siteTitle,
]); ?>
    <div class="admin-header">
        <h1>Gestion de l'arborescence</h1>
        <div class="admin-actions">
            <button onclick="createSubfolder('./liste_albums')" class="action-button">Nouveau dossier</button>
            <a href="admin.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <?php $renderer->renderLayout('partials/messages'); ?>

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

    <?php echo $treeScripts; ?>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
