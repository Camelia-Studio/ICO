<?php
/**
 * Vue : formulaire d'ajout / modification d'un lien social (admin).
 *
 * Variables :
 *   array<string, mixed>|null $link           Lien existant (null = création)
 *   string $error_message  Message d'erreur (vide si aucun)
 *   string $site_title     Titre du site
 *   string $version        Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var array<string, mixed>|null $link */
/** @var string $error_message */
/** @var string $site_title */
/** @var string $version */

$isEdit    = $link !== null;
$pageTitle = $isEdit ? 'Modifier le lien' : 'Nouveau lien social';
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => $pageTitle . ' - ' . $site_title,
]); ?>
    <div class="admin-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <div class="admin-actions">
            <a href="liens-sociaux.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <?php if ($error_message !== ''): ?>
            <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="post" action="liens-sociaux.php?action=save" class="form-container">
            <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="label">Nom :</label>
                <input type="text" id="label" name="label" required autofocus maxlength="100"
                       value="<?php echo htmlspecialchars((string) ($link['label'] ?? '')); ?>"
                       placeholder="ex : Instagram, YouTube…">
            </div>

            <div class="form-group">
                <label for="url">URL :</label>
                <input type="url" id="url" name="url" required
                       value="<?php echo htmlspecialchars((string) ($link['url'] ?? '')); ?>"
                       placeholder="https://…">
            </div>

            <div class="form-group">
                <label for="display_order">Ordre d'affichage :</label>
                <input type="number" id="display_order" name="display_order" min="0" max="999"
                       value="<?php echo (int) ($link['display_order'] ?? 0); ?>"
                       style="width:6rem;">
                <small class="form-help">Les valeurs les plus faibles apparaissent en premier.</small>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1"
                           <?php echo (!$isEdit || $link['is_active']) ? 'checked' : ''; ?>>
                    Lien actif (visible dans le pied de page)
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="action-button">
                    <?php echo $isEdit ? 'Enregistrer les modifications' : 'Ajouter le lien'; ?>
                </button>
            </div>
        </form>
    </div>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
