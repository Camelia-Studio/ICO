<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Repository\AdminRepository;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;
use ICO\Service\UpdateService;

/**
 * Gère les pages d'administration : login, logout, dashboard, changement de mdp.
 * Source : admin.php (413 lignes).
 */
class AdminController
{
    public function __construct(
        private readonly AuthService       $auth,
        private readonly AdminRepository   $adminRepo,
        private readonly PasswordValidator $passwordValidator,
        private readonly UpdateService     $updateService,
    ) {}

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function handle(): void
    {
        $action = $_GET['action'] ?? 'home';

        switch ($action) {
            case 'login':
                $this->login();
                break;
            case 'logout':
                $this->logout();
                break;
            case 'show_change_password':
                $this->showChangePassword();
                break;
            case 'change_password':
                $this->changePassword();
                break;
            default:
                $this->home();
                break;
        }
    }

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    private function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($this->auth->login($username, $password)) {
                header('Location: admin.php');
                exit;
            }

            $this->renderLogin('Identifiants incorrects');
            return;
        }

        $this->renderLogin();
    }

    private function renderLogin(?string $error = null): void
    {
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

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    private function logout(): void
    {
        $this->auth->logout();
        header('Location: admin.php');
        exit;
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    private function home(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            exit;
        }

        $adminId  = $_SESSION['admin_id'];
        $firstId  = $this->adminRepo->findFirstAdminId();
        $isFirst  = ($adminId == $firstId);

        $updateStatus    = $this->updateService->checkUpdate();
        $updateAvailable = $updateStatus && $updateStatus['available'];
        $menuItemClass   = 'admin-menu-item' . ($updateAvailable ? ' update-available' : ' disabled');
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

            <?php if ($isFirst): ?>
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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </div>
                <div class="menu-content">
                    <h2>Options de personnalisation</h2>
                    <p>Personnalisez le titre et la description de votre galerie.</p>
                </div>
            </a>

            <?php if ($isFirst): ?>
            <a href="logs.php" class="admin-menu-item">
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </div>
                <div class="menu-content">
                    <h2>Logs système</h2>
                    <p>Consultez l'historique des actions des administrateurs.</p>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($updateAvailable): ?>
                <a href="https://git.crystalyx.net/camelia-studio/ICO/releases/tag/<?php echo htmlspecialchars($updateStatus['latest']); ?>"
                   class="<?php echo $menuItemClass; ?>"
                   target="_blank"
                   rel="noopener noreferrer">
            <?php else: ?>
                <div class="<?php echo $menuItemClass; ?>">
            <?php endif; ?>
                <div class="menu-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" rx="4" />
                        <path d="M7 14l5-5 5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="menu-content">
                    <h2>Mise à jour</h2>
                    <?php if ($updateAvailable): ?>
                        <div class="update-status">
                            Version actuelle : <?php echo htmlspecialchars($updateStatus['current']); ?>
                            Dernière version : <?php echo htmlspecialchars($updateStatus['latest']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php if ($updateAvailable): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
        <?php
    }

    // -------------------------------------------------------------------------
    // Changement de mot de passe
    // -------------------------------------------------------------------------

    private function showChangePassword(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            exit;
        }
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
                <input type="password" id="new_password" name="new_password" required minlength="12">
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
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe :</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="12">
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

    private function changePassword(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php');
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation de la force
        $error = $this->passwordValidator->validate($newPassword);
        if ($error !== null) {
            $_SESSION['error_message'] = $error;
            header('Location: admin.php?action=show_change_password');
            exit;
        }

        // Vérifier que les deux nouveaux mots de passe correspondent
        if ($newPassword !== $confirmPassword) {
            $_SESSION['error_message'] = 'Les deux nouveaux mots de passe ne correspondent pas.';
            header('Location: admin.php?action=show_change_password');
            exit;
        }

        // Vérifier l'ancien mot de passe via AuthService
        if (!$this->auth->verifyPassword((int) $_SESSION['admin_id'], $currentPassword)) {
            $_SESSION['error_message'] = 'Le mot de passe actuel est incorrect.';
            header('Location: admin.php?action=show_change_password');
            exit;
        }

        // Mettre à jour
        $newHash = $this->auth->hashPassword($newPassword);
        if ($this->adminRepo->updatePassword((int) $_SESSION['admin_id'], $newHash)) {
            $_SESSION['success_message'] = 'Mot de passe changé avec succès.';
            header('Location: admin.php');
        } else {
            $_SESSION['error_message'] = 'Une erreur est survenue lors du changement de mot de passe.';
            header('Location: admin.php?action=show_change_password');
        }
        exit;
    }
}
