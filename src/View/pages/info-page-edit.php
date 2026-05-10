<?php
/**
 * Vue : formulaire de création / édition d'une page "en savoir plus" (admin).
 *
 * Variables :
 *   array<string, mixed>|null $page          Page existante (null = création)
 *   string $error_message  Message d'erreur (vide si aucun)
 *   string $site_title     Titre du site
 *   string $version        Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var array<string, mixed>|null $page */
/** @var string $error_message */
/** @var string $site_title */
/** @var string $version */

$isEdit    = $page !== null;
$pageTitle = $isEdit ? 'Modifier la page' : 'Nouvelle page';
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => $pageTitle . ' - ' . $site_title,
]); ?>
    <div class="admin-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <div class="admin-actions">
            <a href="pages-info.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <?php if ($error_message !== ''): ?>
            <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="post" action="pages-info.php?action=save" class="form-container">
            <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo (int) $page['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="title">Titre de la page :</label>
                <input type="text" id="title" name="title" required autofocus
                       value="<?php echo htmlspecialchars((string) ($page['title'] ?? '')); ?>"
                       oninput="syncSlug(this.value)">
            </div>

            <div class="form-group">
                <label for="slug">Slug (URL) :</label>
                <div style="display:flex; gap:.5rem; align-items:center;">
                    <input type="text" id="slug" name="slug" required
                           value="<?php echo htmlspecialchars((string) ($page['slug'] ?? '')); ?>"
                           pattern="[a-z0-9\-]+" title="Lettres minuscules, chiffres et tirets uniquement"
                           style="flex:1;">
                    <button type="button" class="action-button action-button-secondary"
                            onclick="document.getElementById('slug').value = generateSlug(document.getElementById('title').value);"
                            title="Regénérer depuis le titre">↺</button>
                </div>
                <small class="form-help">Identifiant dans l'URL : <code>page.php?slug=<span id="slug-preview"><?php echo htmlspecialchars((string) ($page['slug'] ?? 'mon-slug')); ?></span></code></small>
            </div>

            <div class="form-group">
                <label for="content">Contenu (HTML accepté) :</label>
                <textarea id="content" name="content" rows="18"
                          class="form-textarea" style="font-family: monospace; font-size: .9rem;"><?php echo htmlspecialchars((string) ($page['content'] ?? '')); ?></textarea>
                <small class="form-help">Le contenu HTML sera affiché tel quel sur la page publique.</small>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_published" value="1"
                           <?php echo (!$isEdit || $page['is_published']) ? 'checked' : ''; ?>>
                    Page publiée (visible par les visiteurs)
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="action-button">
                    <?php echo $isEdit ? 'Enregistrer les modifications' : 'Créer la page'; ?>
                </button>
            </div>
        </form>
    </div>

    <script>
    function generateSlug(title) {
        const map = {
            'à':'a','â':'a','ä':'a','é':'e','è':'e','ê':'e','ë':'e',
            'î':'i','ï':'i','ô':'o','ö':'o','ù':'u','û':'u','ü':'u',
            'ç':'c','ñ':'n'
        };
        return title.toLowerCase()
            .replace(/[àâäéèêëîïôöùûüçñ]/g, c => map[c] || c)
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    let slugEdited = <?php echo $isEdit ? 'true' : 'false'; ?>;

    document.getElementById('slug').addEventListener('input', () => { slugEdited = true; });

    function syncSlug(title) {
        if (slugEdited) {
            document.getElementById('slug-preview').textContent = document.getElementById('slug').value || 'mon-slug';
            return;
        }
        const slug = generateSlug(title);
        document.getElementById('slug').value = slug;
        document.getElementById('slug-preview').textContent = slug || 'mon-slug';
    }

    document.getElementById('slug').addEventListener('input', function() {
        document.getElementById('slug-preview').textContent = this.value || 'mon-slug';
    });
    </script>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
