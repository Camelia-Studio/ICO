<?php
/**
 * Vue : gestion des clés de partage (admin).
 *
 * Variables :
 *   list<array<string, mixed>> $keys         Clés enrichies (album_title, album_mature, share_url, is_expired)
 *   list<array<string, mixed>> $albums       Albums disponibles pour le filtre
 *   string $filter         Filtre de statut actif : 'active' | 'expired' | 'all'
 *   string $album_filter   Identifiant UUID de l'album filtré (vide = tous)
 *   string $success_message Message de succès (vide si aucun)
 *   string $error_message   Message d'erreur (vide si aucun)
 *   string $site_title      Titre du site
 *   string $version         Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var list<array<string, mixed>> $keys */
/** @var list<array<string, mixed>> $albums */
/** @var string $filter */
/** @var string $album_filter */
/** @var string $success_message */
/** @var string $error_message */
/** @var string $site_title */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Gestion des clés de partage - ' . $site_title,
]); ?>
    <div class="admin-header">
        <h1>Gestion des clés de partage</h1>
        <div class="admin-actions">
            <button onclick="cleanExpiredKeys()" class="action-button">
                Nettoyer les clés expirées
            </button>
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

        <div class="filters">
            <div class="filter-group">
                <label for="status-filter">Statut&nbsp;:</label>
                <select id="status-filter" class="form-select" onchange="updateFilters()">
                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Clés actives</option>
                    <option value="expired" <?php echo $filter === 'expired' ? 'selected' : ''; ?>>Clés expirées</option>
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Toutes les clés</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="album-filter">Album&nbsp;:</label>
                <select id="album-filter" class="form-select" onchange="updateFilters()">
                    <option value="">Tous les albums</option>
                    <?php foreach ($albums as $album): ?>
                    <option value="<?php echo htmlspecialchars($album['identifier']); ?>"
                            <?php echo $album_filter === $album['identifier'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($album['path']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="keys-list">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Album</th>
                        <th>URL de partage</th>
                        <th>Créée le</th>
                        <th>Expire le</th>
                        <th>Commentaire</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($keys as $key): ?>
                    <tr class="<?php echo $key['is_expired'] ? 'expired-key' : ''; ?>">
                        <td title="<?php echo htmlspecialchars($key['path']); ?>">
                            <?php echo htmlspecialchars($key['album_title']); ?>
                            <?php if ($key['album_mature']): ?>
                                <span class="mature-warning">🔞</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$key['is_expired']): ?>
                            <div class="share-url">
                                <input type="text" readonly value="<?php echo htmlspecialchars($key['share_url']); ?>"
                                       class="share-url-input" onclick="this.select()">
                                <button onclick="copyShareUrl(this)" class="tree-button" title="Copier">📋</button>
                            </div>
                            <?php else: ?>
                            <span class="expired-text">Expirée</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($key['created_at'])); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($key['expires_at'])); ?></td>
                        <td><?php echo htmlspecialchars($key['comment']); ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="delete_key">
                                <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                <button type="submit" class="tree-button tree-button-danger"
                                        onclick="return confirm('Voulez-vous vraiment supprimer cette clé ?')">
                                    🗑️
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($keys)): ?>
                    <tr>
                        <td colspan="6" class="no-data">Aucune clé trouvée</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function cleanExpiredKeys() {
        if (confirm('Voulez-vous supprimer toutes les clés expirées ?')) {
            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = '<input type="hidden" name="action" value="clean_expired">';
            document.body.appendChild(form);
            form.submit();
        }
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

    function updateFilters() {
        const statusFilter = document.getElementById('status-filter').value;
        const albumFilter = document.getElementById('album-filter').value;

        let url = 'clefs.php?filter=' + statusFilter;
        if (albumFilter) {
            url += '&album=' + albumFilter;
        }

        window.location.href = url;
    }
    </script>
    <button class="scroll-top" title="Retour en haut">↑</button>
    <script>
    const scrollBtn = document.querySelector('.scroll-top');
    window.addEventListener('scroll', () => {
        scrollBtn.style.display = window.scrollY > 500 ? 'flex' : 'none';
    });
    scrollBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    </script>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
