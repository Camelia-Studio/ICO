<?php
require_once 'fonctions.php';
session_start();

// Vérifier si un utilisateur est connecté
function checkAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: admin.php?action=login');
        exit;
    }
}

// Se connecter à la base de données
function getDB() {
    return new SQLite3('database.sqlite');
}

// Page de connexion
function showLoginForm($error = null) {
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ICO</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
    <div class="admin-login">
        <h1>Connexion</h1>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="admin.php?action=login">
            <div class="form-group">
                <label for="username">Identifiant :</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="action-button">Se connecter</button>
        </form>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
    <?php
}

// Page principale d'administration
function showAdminInterface() {
    checkAuth();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - ICO</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
    <div class="admin-header">
        <h1>Administration ICO</h1>
        <div class="admin-actions">
            <a href="index.php" target="_blank" class="action-button action-button-success">Accéder à la galerie</a>
            <a href="admin.php?action=show_change_password" class="action-button">Changer mon mdp</a>
            <a href="admin.php?action=logout" class="action-button action-button-danger">Déconnexion</a>
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

        <div class="admin-menu">
            <a href="arbre.php" class="admin-menu-item">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        <path d="M9 13h6"></path>
                        <path d="M12 10v6"></path>
                    </svg>
                </div>
                <div class="menu-content">
                    <h2>Gestion des albums</h2>
                    <p>Organisez vos albums et gérez l'arborescence de votre galerie photo.</p>
                </div>
            </a>

            <?php
            // Vérifier si c'est le premier administrateur
            $db = getDB();
            $stmt = $db->prepare('SELECT MIN(id) as first_id FROM admins');
            $result = $stmt->execute();
            $firstId = $result->fetchArray()['first_id'];
            
            if ($_SESSION['admin_id'] == $firstId):
            ?>
            <a href="utilisateurs.php" class="admin-menu-item">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <div class="menu-content">
                    <h2>Gestion des comptes</h2>
                    <p>Gérez les comptes administrateurs de la galerie photo.</p>
                </div>
            </a>
            <?php endif; ?>
            <a href="arbre-prive.php" class="admin-menu-item">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div class="menu-content">
                    <h2>Gestion des albums privés</h2>
                    <p>Gérez vos albums photos privés et sécurisés.</p>
                </div>
            </a>
            <a href="clefs.php" class="admin-menu-item">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
                    </svg>
                </div>
                <div class="menu-content">
                    <h2>Clés de partage</h2>
                    <p>Gérez les accès temporaires à vos albums privés.</p>
                </div>
            </a>
            <a href="personnalisation.php" class="admin-menu-item">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20s8-3 8-10V4l-8-2-8 2v6c0 7 8 10 8 10z"/>
                    </svg>
                </div>
                <div class="menu-content">
                    <h2>Personnalisation</h2>
                    <p>Personnalisez le titre et la description de votre galerie.</p>
                </div>
            </a>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
    <?php
}

// Page de changement de mot de passe
function showChangePasswordForm() {
    checkAuth();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe - ICO</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
    <div class="admin-header">
        <h1>Changer le mot de passe</h1>
        <a href="admin.php" class="action-button action-button-secondary">Retour</a>
    </div>
    <div class="admin-content">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <form method="post" action="admin.php?action=change_password" class="form-container">
            <div class="form-group">
                <label for="current_password">Mot de passe actuel :</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe :</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe :</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <div class="form-actions">
                <button type="submit" class="action-button">Changer le mot de passe</button>
            </div>
        </form>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
    <?php
}

// Traiter la connexion
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $db = getDB();
        $stmt = $db->prepare('SELECT id, password_hash FROM admins WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($user = $result->fetchArray()) {
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['admin_id'] = $user['id'];
                header('Location: admin.php');
                exit;
            }
        }
        
        showLoginForm('Identifiants incorrects');
        return;
    }
    
    showLoginForm();
}

// Gérer le changement de mot de passe
function handlePasswordChange() {
    checkAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: admin.php');
        return;
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Vérifier que les nouveaux mots de passe correspondent
    if ($newPassword !== $confirmPassword) {
        $_SESSION['error_message'] = "Les nouveaux mots de passe ne correspondent pas.";
        header('Location: admin.php?action=show_change_password');
        return;
    }

    // Vérifier que le nouveau mot de passe est assez long
    if (strlen($newPassword) < 8) {
        $_SESSION['error_message'] = "Le nouveau mot de passe doit faire au moins 8 caractères.";
        header('Location: admin.php?action=show_change_password');
        return;
    }

    $db = getDB();
    
    // Vérifier l'ancien mot de passe
    $stmt = $db->prepare('SELECT password_hash FROM admins WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['admin_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray();

    if (!password_verify($currentPassword, $user['password_hash'])) {
        $_SESSION['error_message'] = "Le mot de passe actuel est incorrect.";
        header('Location: admin.php?action=show_change_password');
        return;
    }

    // Mettre à jour le mot de passe
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id');
    $stmt->bindValue(':hash', $newHash, SQLITE3_TEXT);
    $stmt->bindValue(':id', $_SESSION['admin_id'], SQLITE3_INTEGER);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Mot de passe changé avec succès.";
        header('Location: admin.php');
    } else {
        $_SESSION['error_message'] = "Une erreur est survenue lors du changement de mot de passe.";
        header('Location: admin.php?action=show_change_password');
    }
    return;
}

// Gérer la déconnexion
function handleLogout() {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Router principal
$action = $_GET['action'] ?? 'home';

switch ($action) {
    case 'login':
        handleLogin();
        break;
        
    case 'logout':
        handleLogout();
        break;

    case 'show_change_password':
        showChangePasswordForm();
        break;

    case 'change_password':
        handlePasswordChange();
        break;
        
    default:
        showAdminInterface();
        break;
}
?>