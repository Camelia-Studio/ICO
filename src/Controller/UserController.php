<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Config\Config;
use ICO\Enum\UserRole;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Repository\AdminRepository;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\LogRepository;
use ICO\Repository\PrivateAlbumAccessRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;
use ICO\Service\PathService;
use ICO\View\ViewRenderer;

/**
 * Gère la page de gestion des utilisateurs et de leurs rôles.
 * Source : utilisateurs.php (421 lignes).
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
        private readonly ?AlbumIdentifierRepository $albumIdentifierRepo = null,
        private readonly ?PrivateAlbumAccessRepository $privateAlbumAccessRepo = null,
        private readonly ?AlbumService      $albumService = null,
        private readonly ?PathService       $pathService = null,
    ) {
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function handle(): void
    {
        // Auth
        if (!$this->auth->isLoggedIn()) {
            Response::redirect('admin.php?action=login')->send();
            throw new TerminateException();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
            Response::redirect('utilisateurs.php')->send();
            throw new TerminateException();
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
        $role = $this->requestedRole();

        if ($username === '' || $password === '' || !$role instanceof UserRole) {
            $_SESSION['error_message'] = "L'identifiant, le mot de passe et le rôle sont requis.";
            return;
        }

        if (!$this->canAssignRole($role)) {
            $_SESSION['error_message'] = 'Vous ne pouvez pas attribuer ce rôle.';
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
        $newId = $this->adminRepo->create($username, $hash, $role);

        if ($newId > 0) {
            if ($role === UserRole::VISITOR) {
                $this->privateAlbumAccessRepo?->replaceForUser($newId, $this->requestedAlbumIdentifiers());
            }

            $_SESSION['success_message'] = 'Utilisateur ajouté avec succès.';
            $this->logRepo->log(
                (int) $_SESSION['admin_id'],
                'ADD_USER',
                sprintf('Création du compte %s : %s', $role->label(), $username),
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
        $target = $this->adminRepo->findById($userId);
        $role = $this->requestedRole()
            ?? UserRole::tryFrom((string) ($target['role'] ?? ''));

        if ($userId === 0 || $username === '' || $target === null || !$role instanceof UserRole) {
            $_SESSION['error_message'] = 'Des informations sont manquantes.';
            return;
        }

        if (!$this->canManage($target) || !$this->canAssignRole($role)) {
            $_SESSION['error_message'] = "Vous n'êtes pas autorisé à modifier ce compte.";
            return;
        }

        if ($userId === $this->adminRepo->findFirstAdminId()) {
            $role = UserRole::ADMINISTRATOR;
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

        if ($this->adminRepo->update($userId, $username, $hash, $role)) {
            $albumIdentifiers = $role === UserRole::VISITOR ? $this->requestedAlbumIdentifiers() : [];
            $this->privateAlbumAccessRepo?->replaceForUser($userId, $albumIdentifiers);
            $_SESSION['success_message'] = 'Utilisateur modifié avec succès.';
            $this->logRepo->log(
                (int) $_SESSION['admin_id'],
                'EDIT_USER',
                sprintf('Modification du compte %s : %s', $role->label(), $username),
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

        $target = $this->adminRepo->findById($userId);
        if ($target === null || !$this->canManage($target) || $userId === (int) $_SESSION['admin_id']) {
            $_SESSION['error_message'] = "Vous n'êtes pas autorisé à supprimer ce compte.";
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
            $_SESSION['error_message'] = 'Impossible de supprimer ce compte.';
        }
    }

    // -------------------------------------------------------------------------
    // Vue
    // -------------------------------------------------------------------------

    private function renderList(): void
    {
        $firstId = $this->adminRepo->findFirstAdminId();
        $users = array_map(function (array $user) use ($firstId): array {
            $role = UserRole::tryFrom((string) $user['role']) ?? UserRole::ADMINISTRATOR;
            $isMain = (int) $user['id'] === $firstId;

            $user['role_label'] = $isMain ? 'Administrateur principal' : $role->label();
            $user['is_main'] = $isMain;
            $user['is_current'] = (int) $user['id'] === (int) $_SESSION['admin_id'];
            $user['can_manage'] = $this->canManage($user);
            $user['album_identifiers'] = $role === UserRole::VISITOR
                ? ($this->privateAlbumAccessRepo?->findIdentifiersForUser((int) $user['id']) ?? [])
                : [];

            return $user;
        }, $this->adminRepo->findAll());

        $this->view->render('pages/users-list', [
            'users'     => $users,
            'roles'     => $this->assignableRoles(),
            'privateAlbums' => $this->findPrivateAlbums(),
            'siteTitle' => $this->config->getSiteTitle(),
            'version'   => $this->config->getVersion(),
        ]);
    }

    /** @param array<string, mixed> $target */
    private function canManage(array $target): bool
    {
        $actorId = (int) $_SESSION['admin_id'];
        $firstId = $this->adminRepo->findFirstAdminId();
        $targetId = (int) $target['id'];

        if ($actorId === $firstId) {
            return true;
        }

        if ($targetId === $firstId) {
            return false;
        }

        $actorRole = $this->adminRepo->getEffectiveRole($actorId);
        if ($actorRole === UserRole::ADMINISTRATOR) {
            return true;
        }

        $targetRole = UserRole::tryFrom((string) ($target['role'] ?? ''));

        return $actorRole === UserRole::MODERATOR && $targetRole === UserRole::VISITOR;
    }

    private function requestedRole(): ?UserRole
    {
        return UserRole::tryFrom((string) ($_POST['role'] ?? ''));
    }

    private function canAssignRole(UserRole $role): bool
    {
        $actorId = (int) $_SESSION['admin_id'];
        $actorRole = $this->adminRepo->getEffectiveRole($actorId);

        return $actorId === $this->adminRepo->findFirstAdminId()
            || $actorRole === UserRole::ADMINISTRATOR
            || ($actorRole === UserRole::MODERATOR && $role === UserRole::VISITOR);
    }

    /** @return array<string, string> */
    private function assignableRoles(): array
    {
        $roles = $this->adminRepo->getEffectiveRole((int) $_SESSION['admin_id']) === UserRole::MODERATOR
            ? [UserRole::VISITOR]
            : UserRole::cases();

        $result = [];
        foreach ($roles as $role) {
            $result[$role->value] = $role->label();
        }

        return $result;
    }

    /** @return list<string> */
    private function requestedAlbumIdentifiers(): array
    {
        $requested = $_POST['private_albums'] ?? [];
        if (!is_array($requested)) {
            return [];
        }

        $allowed = array_column($this->findPrivateAlbums(), 'identifier');

        return array_values(array_intersect(array_map(strval(...), $requested), $allowed));
    }

    /** @return array<int, array{identifier: string, path: string, title: string}> */
    private function findPrivateAlbums(): array
    {
        if (!$this->pathService instanceof PathService || !$this->albumService instanceof AlbumService || !$this->albumIdentifierRepo instanceof AlbumIdentifierRepository) {
            return [];
        }

        $root = realpath($this->pathService->toAbsolute('liste_albums_prives'));
        if ($root === false) {
            return [];
        }

        $albums = [];
        $this->collectPrivateAlbums($root, $albums);

        usort($albums, static fn (array $a, array $b): int => strcasecmp($a['title'], $b['title']));

        return $albums;
    }

    /** @param array<int, array{identifier: string, path: string, title: string}> $albums */
    private function collectPrivateAlbums(string $path, array &$albums): void
    {
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $albumPath = $item->getPathname();
            $info = $this->albumService->getAlbumInfo($albumPath);
            $albums[] = [
                'identifier' => $this->albumIdentifierRepo->ensure($albumPath),
                'path' => $this->pathService->toRelative($albumPath),
                'title' => (string) $info['title'],
            ];
            $this->collectPrivateAlbums($albumPath, $albums);
        }
    }
}
