<?php
/**
 * Vue : liste des pages "en savoir plus" (admin).
 *
 * Variables :
 *   list<array<string, mixed>> $pages          Pages enregistrées
 *   string $success_message  Message de succès (vide si aucun)
 *   string $error_message    Message d'erreur (vide si aucun)
 *   string $base_url         URL de base du site
 *   string $site_title       Titre du site
 *   string $version          Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var list<array<string, mixed>> $pages */
/** @var string $success_message */
/** @var string $error_message */
/** @var string $base_url */
/** @var string $site_title */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Pages "En savoir plus" - ' . $site_title,
]); ?>
    <div class="admin-header">
        <h1>Pages "En savoir plus"</h1>
        <div class="admin-actions">
            <a href="pages-info.php?action=new" class="action-button action-button-success">+ Nouvelle page</a>
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

        <div class="keys-list">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Slug</th>
                        <th>Statut</th>
                        <th>Créée le</th>
                        <th>Modifiée le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $page['title']); ?></td>
                        <td>
                            <code><?php echo htmlspecialchars((string) $page['slug']); ?></code>
                        </td>
                        <td>
                            <?php if ($page['is_published']): ?>
                                <span class="status-badge status-active">Publiée</span>
                            <?php else: ?>
                                <span class="status-badge status-expired">Brouillon</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime((string) $page['created_at'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime((string) $page['updated_at'])); ?></td>
                        <td class="actions-cell">
                            <?php if ($page['is_published']): ?>
                            <?php $pageUrl = htmlspecialchars($base_url) . '/page.php?slug=' . urlencode((string) $page['slug']); ?>
                            <a href="<?php echo $pageUrl; ?>"
                               target="_blank" class="tree-button" title="Voir la page">👁</a>
                            <button type="button" class="tree-button"
                                    title="Copier le lien"
                                    onclick="copyPageUrl(this, '<?php echo htmlspecialchars($base_url . '/page.php?slug=' . urlencode((string) $page['slug']), ENT_QUOTES); ?>')">📋</button>
                            <?php endif; ?>
                            <a href="pages-info.php?action=edit&id=<?php echo (int) $page['id']; ?>"
                               class="tree-button" title="Modifier">✏️</a>
                            <form method="post" action="pages-info.php?action=delete" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo (int) $page['id']; ?>">
                                <button type="submit" class="tree-button tree-button-danger"
                                        title="Supprimer"
                                        onclick="return confirm('Supprimer définitivement cette page ?')">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pages)): ?>
                    <tr>
                        <td colspan="6" class="no-data">Aucune page créée — <a href="pages-info.php?action=new">créer la première</a></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <button class="scroll-top" title="Retour en haut">↑</button>
    <script src="js/scroll-top.js"></script>
    <script>
    function copyPageUrl(button, url) {
        navigator.clipboard.writeText(url).then(() => {
            const original = button.innerHTML;
            button.innerHTML = '✓';
            button.classList.add('copied');
            setTimeout(() => {
                button.innerHTML = original;
                button.classList.remove('copied');
            }, 2000);
        });
    }
    </script>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
