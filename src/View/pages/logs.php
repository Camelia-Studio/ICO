<?php
/**
 * Vue : logs administrateurs avec filtres et pagination.
 *
 * Variables :
 *   list<array<string, mixed>> $logs                Liste des entrées de log
 *   list<array<string, mixed>> $admins              Liste des admins pour le filtre
 *   string[]                   $action_types        Types d'actions distincts
 *   array<string, string>      $action_translations Traductions des types d'actions
 *   array{action_type: string, admin_id: int, date_range: string} $filters Filtres actifs
 *   int    $page         Page courante
 *   int    $total_pages  Nombre total de pages
 *   int    $total        Nombre total de logs
 *   string $site_title   Titre du site
 *   string $version      Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var list<array<string, mixed>> $logs */
/** @var list<array<string, mixed>> $admins */
/** @var string[] $action_types */
/** @var array<string, string> $action_translations */
/** @var array<string, mixed> $filters */
/** @var int $page */
/** @var int $total_pages */
/** @var string $site_title */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Logs administrateurs - ' . $site_title,
]); ?>
    <div class="admin-header">
        <h1>Logs administrateurs</h1>
        <div class="admin-actions">
            <a href="admin.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <!-- Filtres -->
        <div class="filters">
            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label for="action_type">Type d'action&nbsp;:</label>
                    <select name="action_type" id="action_type" class="form-select">
                        <option value="">Toutes les actions</option>
                        <?php foreach ($action_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"
                                    <?php echo $filters['action_type'] === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($action_translations[$type] ?? $type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="admin">Administrateur&nbsp;:</label>
                    <select name="admin" id="admin" class="form-select">
                        <option value="">Tous les administrateurs</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>"
                                    <?php echo $filters['admin_id'] === (int) $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_range">Période&nbsp;:</label>
                    <select name="date_range" id="date_range" class="form-select">
                        <option value="">Toutes les dates</option>
                        <option value="24h" <?php echo $filters['date_range'] === '24h' ? 'selected' : ''; ?>>Dernières 24h</option>
                        <option value="48h" <?php echo $filters['date_range'] === '48h' ? 'selected' : ''; ?>>Dernières 48h</option>
                        <option value="72h" <?php echo $filters['date_range'] === '72h' ? 'selected' : ''; ?>>Dernières 72h</option>
                        <option value="1week" <?php echo $filters['date_range'] === '1week' ? 'selected' : ''; ?>>Dernière semaine</option>
                    </select>
                </div>

                <button type="submit" class="action-button">Filtrer</button>
            </form>
        </div>

        <!-- Tableau des logs -->
        <div class="logs-list">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Administrateur</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Chemin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $actionClass = \ICO\Controller\LogController::getActionClass($log['action_type']);
                    ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($log['username'] ?? ''); ?></td>
                        <td class="<?php echo $actionClass; ?>"><?php echo htmlspecialchars($action_translations[$log['action_type']] ?? $log['action_type']); ?></td>
                        <td><?php echo htmlspecialchars($log['action_description']); ?></td>
                        <td><?php echo htmlspecialchars($log['target_path'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&action_type=<?php echo urlencode($filters['action_type']); ?>&admin=<?php echo $filters['admin_id']; ?>&date_range=<?php echo urlencode($filters['date_range']); ?>"
                   class="pagination-link <?php echo $page === $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
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
