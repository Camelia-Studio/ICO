<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\TerminateException;
use ICO\Repository\AdminRepository;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;
use ICO\Service\UpdateService;
use ICO\View\ViewRenderer;

/**
 * Gère les pages d'administration : login, logout, dashboard, changement de mdp.
 * Source : admin.php (413 lignes).
 */
class AdminController
{
    public function __construct(
        private readonly Config            $config,
        private readonly AuthService       $auth,
        private readonly AdminRepository   $adminRepo,
        private readonly PasswordValidator $passwordValidator,
        private readonly UpdateService     $updateService,
        private readonly ViewRenderer      $view,
    ) {
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function handle(): void
    {
        $action = $_GET['action'] ?? 'home';

        match ($action) {
            'login' => $this->login(),
            'logout' => $this->logout(),
            'show_change_password' => $this->showChangePassword(),
            'change_password' => $this->changePassword(),
            default => $this->home(),
        };
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
                throw new TerminateException();
            }

            $this->renderLogin('Identifiants incorrects');
            return;
        }

        $this->renderLogin();
    }

    private function renderLogin(?string $error = null): void
    {
        $this->view->render('pages/admin-login', [
            'error'   => $error,
            'version' => $this->config->getVersion(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    private function logout(): void
    {
        $this->auth->logout();
        header('Location: admin.php');
        throw new TerminateException();
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    private function home(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        $adminId  = $_SESSION['admin_id'];
        $firstId  = $this->adminRepo->findFirstAdminId();
        $isFirst  = ($adminId == $firstId);

        $updateStatus    = $this->updateService->checkUpdate();
        $updateAvailable = $updateStatus && $updateStatus['available'];
        $menuItemClass   = 'admin-menu-item' . ($updateAvailable ? ' update-available' : ' disabled');

        $this->view->render('pages/admin-dashboard', [
            'isFirst'         => $isFirst,
            'updateAvailable' => $updateAvailable,
            'updateStatus'    => $updateStatus,
            'menuItemClass'   => $menuItemClass,
            'version'         => $this->config->getVersion(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Changement de mot de passe
    // -------------------------------------------------------------------------

    private function showChangePassword(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        $this->view->render('pages/admin-change-password', [
            'version' => $this->config->getVersion(),
        ]);
    }

    private function changePassword(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php');
            throw new TerminateException();
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation de la force
        $error = $this->passwordValidator->validate($newPassword);
        if ($error !== null) {
            $_SESSION['error_message'] = $error;
            header('Location: admin.php?action=show_change_password');
            throw new TerminateException();
        }

        // Vérifier que les deux nouveaux mots de passe correspondent
        if ($newPassword !== $confirmPassword) {
            $_SESSION['error_message'] = 'Les deux nouveaux mots de passe ne correspondent pas.';
            header('Location: admin.php?action=show_change_password');
            throw new TerminateException();
        }

        // Vérifier l'ancien mot de passe via AuthService
        if (!$this->auth->verifyPassword((int) $_SESSION['admin_id'], $currentPassword)) {
            $_SESSION['error_message'] = 'Le mot de passe actuel est incorrect.';
            header('Location: admin.php?action=show_change_password');
            throw new TerminateException();
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

        throw new TerminateException();
    }
}
