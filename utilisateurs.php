<?php
require_once 'fonctions.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php?action=login');
    exit;
}
checkAdminSession();

// Vérifier que c'est bien le premier administrateur
$db = new SQLite3('database.sqlite');
$stmt = $db->prepare('SELECT MIN(id) as first_id FROM admins');
$result = $stmt->execute();
$firstId = $result->fetchArray()['first_id'];

if ($_SESSION['admin_id'] != $firstId) {
    $_SESSION['error_message'] = "Accès non autorisé. Seul le premier administrateur peut gérer les comptes.";
    header('Location: admin.php');
    exit;
}

// Se connecter à la base de données
function getDB() {
    return new SQLite3('database.sqlite');
}

// Gérer les actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = getDB();
    
    switch ($action) {
        case 'add':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $_SESSION['error_message'] = "L'identifiant et le mot de passe sont requis.";
                break;
            }
            
            // Vérification du mot de passe
            if (strlen($password) < 12) {
                $_SESSION['error_message'] = "Le mot de passe doit faire au moins 12 caractères.";
                break;
            }
            
            if (!preg_match('/[a-z]/', $password)) {
                $_SESSION['error_message'] = "Le mot de passe doit contenir au moins une lettre minuscule.";
                break;
            }
            
            if (!preg_match('/[A-Z]/', $password)) {
                $_SESSION['error_message'] = "Le mot de passe doit contenir au moins une lettre majuscule.";
                break;
            }
            
            if (!preg_match('/[0-9]/', $password)) {
                $_SESSION['error_message'] = "Le mot de passe doit contenir au moins un chiffre.";
                break;
            }
            
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $_SESSION['error_message'] = "Le mot de passe doit contenir au moins un caractère spécial.";
                break;
            }
            
            // Vérifier si l'utilisateur existe déjà
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM admins WHERE username = :username');
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $result = $stmt->execute()->fetchArray();
            
            if ($result['count'] > 0) {
                $_SESSION['error_message'] = "Cet identifiant existe déjà.";
                break;
            }
            
            // Créer le nouvel utilisateur
            $stmt = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Utilisateur ajouté avec succès.";
                logAdminAction(
                    $_SESSION['admin_id'],
                    'ADD_USER',
                    "Création du compte administrateur : " . $username
                );
            } else {
                $_SESSION['error_message'] = "Erreur lors de l'ajout de l'utilisateur.";
            }
            break;
        
            case 'edit':
                $userId = $_POST['user_id'] ?? '';
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if (empty($userId) || empty($username)) {
                    $_SESSION['error_message'] = "Des informations sont manquantes.";
                    break;
                }
            
                // Vérifier que le nouvel identifiant n'existe pas déjà (sauf pour l'utilisateur actuel)
                $stmt = $db->prepare('SELECT COUNT(*) as count FROM admins WHERE username = :username AND id != :id');
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                $result = $stmt->execute()->fetchArray();
                
                if ($result['count'] > 0) {
                    $_SESSION['error_message'] = "Cet identifiant existe déjà.";
                    break;
                }
                
                // Si un nouveau mot de passe est fourni
                if (!empty($password)) {
                    // Vérification du mot de passe
                    if (strlen($password) < 12) {
                        $_SESSION['error_message'] = "Le mot de passe doit faire au moins 12 caractères.";
                        break;
                    }
                    
                    if (!preg_match('/[a-z]/', $password)) {
                        $_SESSION['error_message'] = "Le mot de passe doit contenir au moins une lettre minuscule.";
                        break;
                    }
                    
                    if (!preg_match('/[A-Z]/', $password)) {
                        $_SESSION['error_message'] = "Le mot de passe doit contenir au moins une lettre majuscule.";
                        break;
                    }
                    
                    if (!preg_match('/[0-9]/', $password)) {
                        $_SESSION['error_message'] = "Le mot de passe doit contenir au moins un chiffre.";
                        break;
                    }
                    
                    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                        $_SESSION['error_message'] = "Le mot de passe doit contenir au moins un caractère spécial.";
                        break;
                    }
            
                    // Construction de la requête avec nouveau mot de passe
                    $stmt = $db->prepare('UPDATE admins SET username = :username, password_hash = :password_hash WHERE id = :id');
                    $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
                } else {
                    // Construction de la requête sans nouveau mot de passe
                    $stmt = $db->prepare('UPDATE admins SET username = :username WHERE id = :id');
                }
                
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Utilisateur modifié avec succès.";
                    logAdminAction(
                        $_SESSION['admin_id'],
                        'EDIT_USER',
                        "Modification du compte administrateur : " . $username
                    );
                } else {
                    $_SESSION['error_message'] = "Erreur lors de la modification de l'utilisateur.";
                }
                break;
            
        case 'delete':
            $userId = $_POST['user_id'] ?? '';
            
            if (empty($userId)) {
                $_SESSION['error_message'] = "ID utilisateur manquant.";
                break;
            }
            
            // Vérifier que l'utilisateur n'est pas le premier compte
            $stmt = $db->prepare('SELECT MIN(id) as first_id FROM admins');
            $firstId = $stmt->execute()->fetchArray()['first_id'];
            
            if ($userId == $firstId) {
                $_SESSION['error_message'] = "Impossible de supprimer le compte principal.";
                break;
            }
            
            // Supprimer l'utilisateur
            $stmt = $db->prepare('DELETE FROM admins WHERE id = :id AND id != (SELECT MIN(id) FROM admins)');
            $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Utilisateur supprimé avec succès.";
                logAdminAction(
                    $_SESSION['admin_id'],
                    'DELETE_USER',
                    "Suppression d'un compte administrateur",
                    "ID: " . $userId
                );
            } else {
                $_SESSION['error_message'] = "Erreur lors de la suppression de l'utilisateur.";
            }
            break;
    }
    
    header('Location: utilisateurs.php');
    exit;
}

// Récupérer la liste des utilisateurs
$db = getDB();
$users = [];
$result = $db->query('SELECT * FROM admins ORDER BY id');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

$config = getSiteConfig();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - <?php echo htmlspecialchars($config['site_title']); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
    <div class="admin-header">
        <h1>Gestion des utilisateurs</h1>
        <div class="admin-actions">
            <button onclick="openAddModal()" class="action-button action-button-success">
                Ajouter un utilisateur
            </button>
            <a href="admin.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success-message"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="users-list">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Identifiant</th>
                        <th>Date de création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        $isMainAdmin = $user['id'] === $users[0]['id'];
                    ?>
                    <tr class="<?php echo $isMainAdmin ? 'main-admin' : ''; ?>">
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($user['username']); ?>
                            <?php if ($isMainAdmin): ?>
                                <span class="admin-badge">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2l3 7h7l-6 4 3 7-7-4-7 4 3-7-6-4h7z"/>
                                    </svg>
                                    Admin principal
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                        <td class="table-actions">
                            <button onclick="editUser(<?php 
                                echo htmlspecialchars($user['id']); ?>, 
                                '<?php echo htmlspecialchars($user['username']); ?>')" 
                                class="tree-button">
                                ✏️
                            </button>
                            <?php if (!$isMainAdmin): ?>
                            <button onclick="deleteUser(<?php echo htmlspecialchars($user['id']); ?>)"
                                class="tree-button tree-button-danger">
                                🗑️
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal d'ajout -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <h2>Ajouter un utilisateur</h2>
            <form method="post" action="utilisateurs.php">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="username">Identifiant :</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe :</label>
                    <input type="password" id="password" name="password" required minlength="12">
                    <small class="form-help">
                        Le mot de passe doit contenir au moins :
                        <ul>
                            <li>12 caractères</li>
                            <li>1 lettre minuscule</li>
                            <li>1 lettre majuscule</li>
                            <li>1 chiffre</li>
                            <li>1 caractère spécial</li>
                        </ul>
                    </small>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('addUserModal')" 
                        class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal d'édition -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <h2>Modifier l'utilisateur</h2>
            <form method="post" action="utilisateurs.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label for="edit_username">Identifiant :</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="edit_password">Nouveau mot de passe (laisser vide pour ne pas changer) :</label>
                    <input type="password" id="edit_password" name="password" minlength="12">
                    <small class="form-help">
                        Le mot de passe doit contenir au moins :
                        <ul>
                            <li>12 caractères</li>
                            <li>1 lettre minuscule</li>
                            <li>1 lettre majuscule</li>
                            <li>1 chiffre</li>
                            <li>1 caractère spécial</li>
                        </ul>
                    </small>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('editUserModal')" 
                        class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button">Modifier</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de suppression -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <h2>Confirmer la suppression</h2>
            <p>Êtes-vous sûr de vouloir supprimer cet utilisateur ?</p>
            <form method="post" action="utilisateurs.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="form-actions">
                    <button type="button" onclick="closeModal('deleteUserModal')" 
                        class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button action-button-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openAddModal() {
        document.getElementById('addUserModal').style.display = 'block';
    }

    function editUser(id, username) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_password').value = '';
        document.getElementById('editUserModal').style.display = 'block';
    }

    function deleteUser(id) {
        document.getElementById('delete_user_id').value = id;
        document.getElementById('deleteUserModal').style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
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