<?php
/**
 * Vue : liste des liens sociaux (admin).
 *
 * Variables :
 *   list<array<string, mixed>> $links           Liens enregistrés
 *   string $success_message  Message de succès (vide si aucun)
 *   string $error_message    Message d'erreur (vide si aucun)
 *   string $site_title       Titre du site
 *   string $version          Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var list<array<string, mixed>> $links */
/** @var string $success_message */
/** @var string $error_message */
/** @var string $site_title */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Liens sociaux - ' . $site_title,
]); ?>
    <div class="admin-header">
        <h1>Liens sociaux</h1>
        <div class="admin-actions">
            <a href="liens-sociaux.php?action=new" class="action-button action-button-success">+ Ajouter</a>
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
                        <th>Nom</th>
                        <th>URL</th>
                        <th>Ordre</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($links as $link): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $link['label']); ?></td>
                        <td>
                            <a href="<?php echo htmlspecialchars((string) $link['url']); ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="footer-link" style="word-break:break-all;">
                                <?php echo htmlspecialchars((string) $link['url']); ?>
                            </a>
                        </td>
                        <td><?php echo (int) $link['display_order']; ?></td>
                        <td>
                            <?php if ($link['is_active']): ?>
                                <span class="status-badge status-active">Actif</span>
                            <?php else: ?>
                                <span class="status-badge status-expired">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="liens-sociaux.php?action=edit&id=<?php echo (int) $link['id']; ?>"
                               class="tree-button" title="Modifier">✏️</a>
                            <form method="post" action="liens-sociaux.php?action=delete" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
                                <button type="submit" class="tree-button tree-button-danger"
                                        title="Supprimer"
                                        onclick="return confirm('Supprimer ce lien ?')">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($links)): ?>
                    <tr>
                        <td colspan="5" class="no-data">Aucun lien social — <a href="liens-sociaux.php?action=new">en ajouter un</a></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <button class="scroll-top" title="Retour en haut">↑</button>
    <script src="js/scroll-top.js"></script>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
