<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Config\VestikanConfig;
use ICO\Http\TerminateException;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;
use ICO\Service\UpdateService;
use ICO\Service\VestikanClientFactory;
use ICO\Service\VestikanLinkService;
use ICO\View\ViewRenderer;
use VestikanException;

/**
 * Gère les pages d'administration : login, logout, dashboard, changement de mdp,
 * ainsi que la connexion et la liaison de compte via le SSO Vestikan.
 * Source : admin.php (413 lignes).
 */
class AdminController
{
    public function __construct(
        private readonly Config                $config,
        private readonly AuthService            $auth,
        private readonly AdminRepository        $adminRepo,
        private readonly PasswordValidator      $passwordValidator,
        private readonly UpdateService          $updateService,
        private readonly ViewRenderer           $view,
        private readonly VestikanConfig         $vestikanConfig,
        private readonly VestikanLinkService    $vestikanLink,
        private readonly LogRepository          $logRepo,
        private readonly VestikanClientFactory  $vestikanClientFactory,
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
            'vestikan_login' => $this->vestikanLogin(),
            'vestikan_callback' => $this->vestikanCallback(),
            'unlink_vestikan' => $this->unlinkVestikan(),
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
                $this->completePendingVestikanLink();
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
            'error'           => $error,
            'version'         => $this->config->getVersion(),
            'vestikanEnabled' => $this->vestikanConfig->isConfigured(),
        ]);
    }

    /**
     * Si un vestikan_id est en attente de liaison (callback SSO reçu avant
     * une connexion native), le lie au compte qui vient de se connecter.
     */
    private function completePendingVestikanLink(): void
    {
        $pendingVestikanId = $_SESSION['pending_vestikan_id'] ?? null;
        if (!is_string($pendingVestikanId)) {
            return;
        }

        unset($_SESSION['pending_vestikan_id']);

        $adminId = (int) $_SESSION['admin_id'];

        try {
            $this->vestikanLink->link($pendingVestikanId, $adminId);
            $this->logRepo->log($adminId, 'LINK_VESTIKAN', 'Liaison du compte Vestikan');
            $_SESSION['success_message'] = 'Compte Vestikan lié avec succès.';
        } catch (VestikanException) {
            $_SESSION['error_message'] = 'Ce compte Vestikan est déjà lié à un autre administrateur.';
        }
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

        $vestikanEnabled = $this->vestikanConfig->isConfigured();

        $this->view->render('pages/admin-dashboard', [
            'isFirst'         => $isFirst,
            'updateAvailable' => $updateAvailable,
            'updateStatus'    => $updateStatus,
            'menuItemClass'   => $menuItemClass,
            'version'         => $this->config->getVersion(),
            'vestikanEnabled' => $vestikanEnabled,
            'vestikanLinked'  => $vestikanEnabled && $this->vestikanLink->findLinkedVestikanId((int) $adminId) !== null,
        ]);
    }

    // -------------------------------------------------------------------------
    // SSO Vestikan
    // -------------------------------------------------------------------------

    /**
     * Démarre le flow OAuth Vestikan (bouton de connexion ou de liaison).
     */
    private function vestikanLogin(): void
    {
        if (!$this->vestikanConfig->isConfigured()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        // Si un admin est déjà connecté, on revient au dashboard après le
        // round-trip (cas "lier mon compte" depuis le tableau de bord).
        $returnTo = $this->auth->isLoggedIn() ? 'admin.php' : null;

        $url = $this->vestikanClientFactory->create()->authorizeUrl($returnTo);

        header('Location: ' . $url);
        throw new TerminateException();
    }

    /**
     * Traite le retour de Vestikan : connecte directement si le vestikan_id
     * est déjà lié, lie à la volée si un admin est déjà connecté (self-service
     * depuis le dashboard), ou demande une connexion native pour lier sinon.
     */
    private function vestikanCallback(): void
    {
        if (!$this->vestikanConfig->isConfigured()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        $client = $this->vestikanClientFactory->create();

        try {
            $vestikanId = $client->complete();
        } catch (VestikanException) {
            $_SESSION['error_message'] = 'Connexion Vestikan refusée.';
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        $linkedAdminId = $this->vestikanLink->resolveAdminId($vestikanId);

        if ($linkedAdminId !== null) {
            $admin = $this->adminRepo->findById($linkedAdminId);
            if ($admin === null) {
                $_SESSION['error_message'] = 'Le compte administrateur lié est introuvable.';
                header('Location: admin.php?action=login');
                throw new TerminateException();
            }

            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['last_activity']  = time();

            header('Location: ' . ($client->popReturnTo() ?: 'admin.php'));
            throw new TerminateException();
        }

        if ($this->auth->isLoggedIn()) {
            $adminId = (int) $_SESSION['admin_id'];

            try {
                $this->vestikanLink->link($vestikanId, $adminId);
                $this->logRepo->log($adminId, 'LINK_VESTIKAN', 'Liaison du compte Vestikan');
                $_SESSION['success_message'] = 'Compte Vestikan lié avec succès.';
            } catch (VestikanException) {
                $_SESSION['error_message'] = 'Ce compte Vestikan est déjà lié à un autre administrateur.';
            }

            header('Location: admin.php');
            throw new TerminateException();
        }

        $_SESSION['pending_vestikan_id'] = $vestikanId;
        $_SESSION['info_message'] = 'Connectez-vous avec votre mot de passe pour lier votre compte Vestikan.';
        header('Location: admin.php?action=login');
        throw new TerminateException();
    }

    /**
     * Supprime la liaison Vestikan du compte connecté.
     */
    private function unlinkVestikan(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        $adminId    = (int) $_SESSION['admin_id'];
        $vestikanId = $this->vestikanLink->findLinkedVestikanId($adminId);

        if ($vestikanId !== null) {
            $this->vestikanLink->unlink($vestikanId);
            $this->logRepo->log($adminId, 'UNLINK_VESTIKAN', 'Déliaison du compte Vestikan');
            $_SESSION['success_message'] = 'Compte Vestikan délié.';
        }

        header('Location: admin.php');
        throw new TerminateException();
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
