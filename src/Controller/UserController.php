<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;

/**
 * Gère la page de gestion des utilisateurs administrateurs.
 * Source : utilisateurs.php (421 lignes).
 * Accès réservé au premier administrateur.
 */
class UserController
{
    public function __construct(
        private readonly Config            $config,
        private readonly AuthService       $auth,
        private readonly AdminRepository   $adminRepo,
        private readonly LogRepository     $logRepo,
        private readonly PasswordValidator $passwordValidator,
    ) {}

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function handle(): void
    {
        // Auth
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            exit;
        }

        // Accès premier admin uniquement
        $adminId = (int) $_SESSION['admin_id'];
        $firstId = $this->adminRepo->findFirstAdminId();

        if ($adminId !== $firstId) {
            $_SESSION['error_message'] = 'Accès non autorisé. Seul le premier administrateur peut gérer les comptes.';
            header('Location: admin.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
            header('Location: utilisateurs.php');
            exit;
        }

        $this->renderList();
    }

    // -------------------------------------------------------------------------
    // Actions POST
    // -------------------------------------------------------------------------

    private function handlePost(): void
    {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                $this->addUser();
                break;
            case 'edit':
                $this->editUser();
                break;
            case 'delete':
                $this->deleteUser();
                break;
        }
    }

    private function addUser(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $_SESSION['error_message'] = "L'identifiant et le mot de passe sont requis.";
            return;
        }

        $error = $this->passwordValidator->validate($password);
        if ($error !== null) {
            $_SESSION['error_message'] = $error;
            return;
        }

        if ($this->adminRepo->usernameExists($username)) {
            $_SESSION['error_message'] = 'Cet identifiant existe déjà.';
            return;
        }

        $hash = $this->auth->hashPassword($password);
        $newId = $this->adminRepo->create($username, $hash);

        if ($newId > 0) {
            $_SESSION['success_message'] = 'Utilisateur ajouté avec succès.';
            $this->logRepo->log(
                (int) $_SESSION['admin_id'],
                'ADD_USER',
                'Création du compte administrateur : ' . $username,
            );
        } else {
            $_SESSION['error_message'] = "Erreur lors de l'ajout de l'utilisateur.";
        }
    }

    private function editUser(): void
    {
        $userId   = (int) ($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($userId === 0 || $username === '') {
            $_SESSION['error_message'] = 'Des informations sont manquantes.';
            return;
        }

        if ($this->adminRepo->usernameExists($username, $userId)) {
            $_SESSION['error_message'] = 'Cet identifiant existe déjà.';
            return;
        }

        // Validation mot de passe si fourni
        $hash = null;
        if ($password !== '') {
            $error = $this->passwordValidator->validate($password);
            if ($error !== null) {
                $_SESSION['error_message'] = $error;
                return;
            }
            $hash = $this->auth->hashPassword($password);
        }

        if ($this->adminRepo->update($userId, $username, $hash)) {
            $_SESSION['success_message'] = 'Utilisateur modifié avec succès.';
            $this->logRepo->log(
                (int) $_SESSION['admin_id'],
                'EDIT_USER',
                'Modification du compte administrateur : ' . $username,
            );
        } else {
            $_SESSION['error_message'] = "Erreur lors de la modification de l'utilisateur.";
        }
    }

    private function deleteUser(): void
    {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId === 0) {
            $_SESSION['error_message'] = 'ID utilisateur manquant.';
            return;
        }

        if ($this->adminRepo->delete($userId)) {
            $_SESSION['success_message'] = 'Utilisateur supprimé avec succès.';
            $this->logRepo->log(
                (int) $_SESSION['admin_id'],
                'DELETE_USER',
                "Suppression d'un compte administrateur",
                'ID: ' . $userId,
            );
        } else {
            // delete() retourne false si c'est le compte principal ou si introuvable
            $_SESSION['error_message'] = 'Impossible de supprimer le compte principal.';
        }
    }

    // -------------------------------------------------------------------------
    // Vue
    // -------------------------------------------------------------------------

    private function renderList(): void
    {
        $users     = $this->adminRepo->findAll();
        $siteTitle = $this->config->getSiteTitle();
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - <?php echo htmlspecialchars($siteTitle); ?></title>
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
                        <td><?php echo htmlspecialchars((string) $user['id']); ?></td>
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
                        <td><?php echo htmlspecialchars((string) $user['created_at']); ?></td>
                        <td class="table-actions">
                            <button onclick="editUser(<?php
                                echo htmlspecialchars((string) $user['id']); ?>,
                                '<?php echo htmlspecialchars($user['username']); ?>')"
                                class="tree-button">
                                ✏️
                            </button>
                            <?php if (!$isMainAdmin): ?>
                            <button onclick="deleteUser(<?php echo htmlspecialchars((string) $user['id']); ?>)"
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
        <?php
    }
}
