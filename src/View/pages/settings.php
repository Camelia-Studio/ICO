<?php
/**
 * Vue : formulaire de personnalisation du site (admin).
 *
 * Variables :
 *   string $site_title        Valeur actuelle du titre
 *   string $site_description  Valeur actuelle de la description
 *   string $project_path      Valeur actuelle du chemin d'installation
 *   string $success_message   Message de succès (vide si aucun)
 *   string $error_message     Message d'erreur (vide si aucun)
 *   string $version           Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string $site_title */
/** @var string $site_description */
/** @var string $project_path */
/** @var string $success_message */
/** @var string $error_message */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Personnalisation - ' . $site_title,
]); ?>
    <div class="admin-header">
        <h1>Personnalisation du site</h1>
        <div class="admin-actions">
            <a href="admin.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <?php if ($success_message !== ''): ?>
            <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message !== ''): ?>
            <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="post" action="personnalisation.php" class="form-container">
            <div class="form-group">
                <label for="site_title">Titre du site :</label>
                <input type="text" id="site_title" name="site_title" required
                       value="<?php echo htmlspecialchars($site_title); ?>">
                <small class="form-help">Ce titre apparaîtra dans l'en-tête des pages et la barre de titre du navigateur.</small>
            </div>
            <div class="form-group">
                <label for="site_description">Description du site :</label>
                <textarea id="site_description" name="site_description" rows="4"
                          class="form-textarea"><?php echo htmlspecialchars($site_description); ?></textarea>
                <small class="form-help">Cette description apparaît sur la page d'accueil du site.</small>
            </div>
            <div class="form-group">
                <label for="project_path">Chemin d'installation :</label>
                <input type="text" id="project_path" name="project_path"
                       value="<?php echo htmlspecialchars($project_path); ?>"
                       placeholder="Laisser vide si ICO est à la racine du domaine">
                <small class="form-help">Sous-chemin d'installation. Laisser vide si ICO est accessible directement via "www.monsite.com".
                    Sinon, indiquer le sous-chemin : par exemple "ico" si l'URL est "www.monsite.com/ico".</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="action-button">Enregistrer les modifications</button>
            </div>
        </form>
    </div>

<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
