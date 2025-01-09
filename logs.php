<?php
require_once 'fonctions.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php?action=login');
    exit;
}

// Vérifier que c'est bien le premier administrateur
$db = new SQLite3('database.sqlite');
$stmt = $db->prepare('SELECT MIN(id) as first_id FROM admins');
$result = $stmt->execute();
$firstId = $result->fetchArray()['first_id'];

if ($_SESSION['admin_id'] != $firstId) {
    $_SESSION['error_message'] = "Accès non autorisé. Seul le premier administrateur peut consulter les logs.";
    header('Location: admin.php');
    exit;
}

// Supprimer les logs de plus d'un mois
$db->exec('DELETE FROM admin_logs WHERE created_at < datetime("now", "-1 month")');

// Tableau de traduction des actions
$actionTranslations = [
    'ADD_USER' => 'Ajouter un utilisateur',
    'EDIT_USER' => 'Modifier un utilisateur',
    'DELETE_USER' => 'Supprimer un utilisateur',
    'CREATE_FOLDER' => 'Créer un dossier',
    'EDIT_FOLDER' => 'Modifier un dossier',
    'DELETE_FOLDER' => 'Supprimer un dossier',
    'CREATE_PRIVATE_FOLDER' => 'Créer un dossier privé',
    'EDIT_PRIVATE_FOLDER' => 'Modifier un dossier privé',
    'DELETE_PRIVATE_FOLDER' => 'Supprimer un dossier privé',
    'UPLOAD_IMAGES' => 'Téléverser des images',
    'DELETE_IMAGES' => 'Supprimer des images',
    'MOVE_IMAGES' => 'Déplacer des images',
    'UPLOAD_PRIVATE_IMAGES' => 'Téléverser des images privées',
    'DELETE_PRIVATE_IMAGES' => 'Supprimer des images privées',
    'GENERATE_SHARE_LINK' => 'Générer un lien de partage',
    'CLEAN_EXPIRED_KEYS' => 'Nettoyer les clés expirées',
    'DELETE_SHARE_KEY' => 'Supprimer une clé de partage',
    'UPDATE_SETTINGS' => 'Modifier les paramètres'
];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filtres
$actionType = isset($_GET['action_type']) ? $_GET['action_type'] : '';
$adminFilter = isset($_GET['admin']) ? intval($_GET['admin']) : 0;
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : '';

// Construction de la requête
$whereClause = [];
$params = [];

if ($actionType) {
    $whereClause[] = 'action_type = :action_type';
    $params[':action_type'] = $actionType;
}

if ($adminFilter) {
    $whereClause[] = 'admin_id = :admin_id';
    $params[':admin_id'] = $adminFilter;
}

if ($dateRange) {
    switch ($dateRange) {
        case '24h':
            $whereClause[] = 'created_at >= datetime("now", "-1 day")';
            break;
        case '48h':
            $whereClause[] = 'created_at >= datetime("now", "-2 days")';
            break;
        case '72h':
            $whereClause[] = 'created_at >= datetime("now", "-3 days")';
            break;
        case '1week':
            $whereClause[] = 'created_at >= datetime("now", "-7 days")';
            break;
    }
}

$whereSQL = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';

// Récupérer le nombre total de logs
$countQuery = "SELECT COUNT(*) as total FROM admin_logs $whereSQL";
$stmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$total = $stmt->execute()->fetchArray()['total'];
$totalPages = ceil($total / $perPage);

// Récupérer les logs
$query = "SELECT l.*, a.username 
          FROM admin_logs l 
          LEFT JOIN admins a ON l.admin_id = a.id 
          $whereSQL 
          ORDER BY l.created_at DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
$stmt->bindValue(':limit', $perPage, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$logs = [];
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $logs[] = $row;
}

function getLogActionClass($actionType) {
    if (strpos(strtolower($actionType), 'create') !== false || 
        strpos(strtolower($actionType), 'add') !== false || 
        strpos(strtolower($actionType), 'upload') !== false || 
        strpos(strtolower($actionType), 'generate') !== false) {
        return 'log-action-create';
    }
    
    if (strpos(strtolower($actionType), 'edit') !== false || 
        strpos(strtolower($actionType), 'update') !== false || 
        strpos(strtolower($actionType), 'modify') !== false || 
        strpos(strtolower($actionType), 'move') !== false) {
        return 'log-action-edit';
    }
    
    if (strpos(strtolower($actionType), 'delete') !== false || 
        strpos(strtolower($actionType), 'remove') !== false || 
        strpos(strtolower($actionType), 'clean') !== false) {
        return 'log-action-delete';
    }
    
    return '';
}

// Récupérer la liste des admins pour le filtre
$admins = [];
$result = $db->query('SELECT id, username FROM admins ORDER BY username');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $admins[] = $row;
}

// Récupérer les types d'actions uniques pour le filtre
$actionTypes = [];
$result = $db->query('SELECT DISTINCT action_type FROM admin_logs ORDER BY action_type');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $actionTypes[] = $row['action_type'];
}

$config = getSiteConfig();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs administrateurs - <?php echo htmlspecialchars($config['site_title']); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
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
                        <?php foreach($actionTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"
                                    <?php echo $actionType === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($actionTranslations[$type] ?? $type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="admin">Administrateur&nbsp;:</label>
                    <select name="admin" id="admin" class="form-select">
                        <option value="">Tous les administrateurs</option>
                        <?php foreach($admins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>"
                                    <?php echo $adminFilter === $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_range">Période&nbsp;:</label>
                    <select name="date_range" id="date_range" class="form-select">
                        <option value="">Toutes les dates</option>
                        <option value="24h" <?php echo $dateRange === '24h' ? 'selected' : ''; ?>>Dernières 24h</option>
                        <option value="48h" <?php echo $dateRange === '48h' ? 'selected' : ''; ?>>Dernières 48h</option>
                        <option value="72h" <?php echo $dateRange === '72h' ? 'selected' : ''; ?>>Dernières 72h</option>
                        <option value="1week" <?php echo $dateRange === '1week' ? 'selected' : ''; ?>>Dernière semaine</option>
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
                    <?php foreach($logs as $log): 
                        $actionClass = getLogActionClass($log['action_type']);
                    ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($log['username']); ?></td>
                        <td class="<?php echo $actionClass; ?>"><?php echo htmlspecialchars($actionTranslations[$log['action_type']] ?? $log['action_type']); ?></td>
                        <td><?php echo htmlspecialchars($log['action_description']); ?></td>
                        <td><?php echo htmlspecialchars($log['target_path'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&action_type=<?php echo urlencode($actionType); ?>&admin=<?php echo $adminFilter; ?>&date_range=<?php echo urlencode($dateRange); ?>" 
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
    <?php include 'footer.php'; ?>
</body>
</html>