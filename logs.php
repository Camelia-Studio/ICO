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

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filtres
$actionType = isset($_GET['action_type']) ? $_GET['action_type'] : '';
$adminFilter = isset($_GET['admin']) ? intval($_GET['admin']) : 0;

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
                    <label for="action_type">Type d'action :</label>
                    <select name="action_type" id="action_type" class="form-select">
                        <option value="">Toutes les actions</option>
                        <?php foreach($actionTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"
                                    <?php echo $actionType === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="admin">Administrateur :</label>
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
                    <?php foreach($logs as $log): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($log['username']); ?></td>
                        <td><?php echo htmlspecialchars($log['action_type']); ?></td>
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
                <a href="?page=<?php echo $i; ?>&action_type=<?php echo urlencode($actionType); ?>&admin=<?php echo $adminFilter; ?>" 
                   class="pagination-link <?php echo $page === $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>