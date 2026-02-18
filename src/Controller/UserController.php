<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;
use ICO\View\ViewRenderer;

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
        private readonly ViewRenderer      $view,
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
        $this->view->render('pages/users-list', [
            'users'     => $this->adminRepo->findAll(),
            'siteTitle' => $this->config->getSiteTitle(),
            'version'   => $this->config->getVersion(),
        ]);
    }
}
