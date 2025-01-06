<?php
require_once 'fonctions.php';

session_start();
checkAdminSession();

// Variables pour stocker les messages
$successMessage = null;
$errorMessage = null;

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php?action=login');
    exit;
}

// Initialisation des variables
$db = new SQLite3('database.sqlite');
$keys = [];
$albums = [];
$filter = $_GET['filter'] ?? 'active';
$albumFilter = $_GET['album'] ?? '';

// Gérer les actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_key':
            $keyId = $_POST['key_id'] ?? '';
            if ($keyId) {
                $stmt = $db->prepare('DELETE FROM share_keys WHERE id = :id');
                $stmt->bindValue(':id', $keyId, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $successMessage = "Clé supprimée avec succès.";
                } else {
                    $errorMessage = "Erreur lors de la suppression de la clé.";
                }
            }
            break;
            
        case 'clean_expired':
            $deletedCount = cleanExpiredShareKeys();
            if ($deletedCount > 0) {
                $successMessage = "$deletedCount clé(s) expirée(s) supprimée(s).";
            } else {
                $successMessage = "Aucune clé expirée à supprimer.";
            }
            break;
    }
}

// Construire la requête SQL en fonction des filtres
$query = 'SELECT s.*, a.path, a.identifier as album_identifier 
          FROM share_keys s
          JOIN album_identifiers a ON s.album_identifier = a.identifier
          WHERE 1=1';

if ($filter === 'active') {
    $query .= ' AND s.expires_at > datetime("now")';
} elseif ($filter === 'expired') {
    $query .= ' AND s.expires_at <= datetime("now")';
}

if (!empty($albumFilter)) {
    $query .= ' AND a.identifier = :album_identifier';
}

$query .= ' ORDER BY s.created_at DESC';

$stmt = $db->prepare($query);
if (!empty($albumFilter)) {
    $stmt->bindValue(':album_identifier', $albumFilter, SQLITE3_TEXT);
}

$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $keys[] = $row;
}

// Récupérer la liste des albums pour le filtre
$albumsQuery = 'SELECT DISTINCT a.identifier, a.path
                FROM album_identifiers a
                ORDER BY a.path';
$albumsResult = $db->query($albumsQuery);
while ($row = $albumsResult->fetchArray(SQLITE3_ASSOC)) {
    $albums[] = $row;
}

$config = getSiteConfig();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des clés de partage - <?php echo htmlspecialchars($config['site_title']); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
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
        <?php if ($successMessage): ?>
            <div class="message success-message"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="message error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="filters">
            <div class="filter-group">
                <label for="status-filter">Statut :</label>
                <select id="status-filter" class="form-select" onchange="updateFilters()">
                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Clés actives</option>
                    <option value="expired" <?php echo $filter === 'expired' ? 'selected' : ''; ?>>Clés expirées</option>
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Toutes les clés</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="album-filter">Album :</label>
                <select id="album-filter" class="form-select" onchange="updateFilters()">
                    <option value="">Tous les albums</option>
                    <?php foreach ($albums as $album): ?>
                    <option value="<?php echo htmlspecialchars($album['identifier']); ?>"
                            <?php echo $albumFilter === $album['identifier'] ? 'selected' : ''; ?>>
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
                    <?php foreach ($keys as $key): 
                        $shareUrl = getBaseUrl() . '/galeries-privees.php?key=' . urlencode($key['key_value']);
                        $isExpired = strtotime($key['expires_at']) <= time();
                        $albumInfo = getAlbumInfo($key['path']);
                    ?>
                    <tr class="<?php echo $isExpired ? 'expired-key' : ''; ?>">
                        <td title="<?php echo htmlspecialchars($key['path']); ?>">
                            <?php echo htmlspecialchars($albumInfo['title']); ?>
                            <?php if ($albumInfo['mature_content']): ?>
                                <span class="mature-warning">🔞</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isExpired): ?>
                            <div class="share-url">
                                <input type="text" readonly value="<?php echo $shareUrl; ?>" 
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
    <?php include 'footer.php'; ?>
</body>
</html>