<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\LogController;
use ICO\Controller\SettingsController;
use ICO\Controller\ShareController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class ControllerTest extends TestCase
{
    // =========================================================================
    // LogController::getActionClass  (méthode statique pure — aucune dépendance)
    // =========================================================================

    public function testGetActionClassForCreateActions(): void
    {
        $this->assertSame('log-action-create', LogController::getActionClass('CREATE_FOLDER'));
        $this->assertSame('log-action-create', LogController::getActionClass('ADD_USER'));
        $this->assertSame('log-action-create', LogController::getActionClass('UPLOAD_IMAGES'));
        $this->assertSame('log-action-create', LogController::getActionClass('GENERATE_SHARE_LINK'));
    }

    public function testGetActionClassForEditActions(): void
    {
        $this->assertSame('log-action-edit', LogController::getActionClass('EDIT_USER'));
        $this->assertSame('log-action-edit', LogController::getActionClass('UPDATE_SETTINGS'));
        $this->assertSame('log-action-edit', LogController::getActionClass('MOVE_IMAGES'));
        $this->assertSame('log-action-edit', LogController::getActionClass('MODIFY_SOMETHING'));
    }

    public function testGetActionClassForDeleteActions(): void
    {
        $this->assertSame('log-action-delete', LogController::getActionClass('DELETE_USER'));
        $this->assertSame('log-action-delete', LogController::getActionClass('DELETE_FOLDER'));
        $this->assertSame('log-action-delete', LogController::getActionClass('CLEAN_EXPIRED_KEYS'));
    }

    public function testGetActionClassReturnsEmptyStringForUnknown(): void
    {
        $this->assertSame('', LogController::getActionClass('UNKNOWN_ACTION'));
        $this->assertSame('', LogController::getActionClass(''));
        $this->assertSame('', LogController::getActionClass('LOGIN'));
    }

    public function testGetActionClassIsCaseInsensitive(): void
    {
        $this->assertSame('log-action-create', LogController::getActionClass('create_folder'));
        $this->assertSame('log-action-delete', LogController::getActionClass('delete_images'));
        $this->assertSame('log-action-edit', LogController::getActionClass('edit_user'));
    }

    // =========================================================================
    // SettingsController — rendu GET quand connecté
    // =========================================================================

    public function testSettingsControllerRendersViewWhenLoggedIn(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ico_settings_');
        file_put_contents($tmpFile, "Mon Site\nDescription\nmon-ico");

        $authMock   = $this->createMock(AuthService::class);
        $logMock    = $this->createMock(LogRepository::class);
        $viewMock   = $this->createMock(ViewRenderer::class);

        $authMock->method('isLoggedIn')->willReturn(true);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/settings', $this->callback(fn (array $data): bool => isset($data['site_title'], $data['site_description'], $data['project_path'])));

        $controller = new SettingsController(
            $authMock,
            $logMock,
            $tmpFile,
            sys_get_temp_dir(),
            $viewMock,
        );

        $controller->index(new Request('GET', '/personnalisation.php'));

        unlink($tmpFile);
    }

    public function testSettingsControllerHandlesPostWithEmptyTitle(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ico_settings_');
        file_put_contents($tmpFile, "Titre\nDesc\n");

        $authMock = $this->createMock(AuthService::class);
        $logMock  = $this->createMock(LogRepository::class);
        $viewMock = $this->createMock(ViewRenderer::class);

        $authMock->method('isLoggedIn')->willReturn(true);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/settings', $this->callback(fn (array $data): bool => $data['error_message'] === 'Le titre du site est requis.'));

        $controller = new SettingsController(
            $authMock,
            $logMock,
            $tmpFile,
            sys_get_temp_dir(),
            $viewMock,
        );

        $req = new Request('POST', '/personnalisation.php', [], ['site_title' => '', 'site_description' => '', 'project_path' => '']);
        $controller->index($req);

        unlink($tmpFile);
    }

    // =========================================================================
    // LogController::index — accès autorisé (premier admin connecté)
    // =========================================================================

    public function testLogControllerIndexRendersLogsForFirstAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['admin_id'] = 1;

        $config    = $this->makeConfig();
        $authMock  = $this->createMock(AuthService::class);
        $logMock   = $this->createMock(LogRepository::class);
        $adminMock = $this->createMock(AdminRepository::class);
        $viewMock  = $this->createMock(ViewRenderer::class);

        $authMock->method('isLoggedIn')->willReturn(true);
        $authMock->method('getLoggedInAdminId')->willReturn(1);
        $adminMock->method('findFirstAdminId')->willReturn(1);
        $adminMock->method('findAll')->willReturn([]);
        $logMock->method('count')->willReturn(0);
        $logMock->method('findAll')->willReturn([]);
        $logMock->method('findDistinctActionTypes')->willReturn([]);

        $viewMock->expects($this->once())->method('render')
            ->with('pages/logs', $this->callback(fn (array $data): bool => isset($data['logs'], $data['admins'], $data['filters'], $data['total'])));

        $controller = new LogController($config, $authMock, $logMock, $adminMock, $viewMock);
        $req = new Request('GET', '/logs.php');
        $controller->index($req);

        $_SESSION = [];
    }

    // =========================================================================
    // ShareController — show() logique
    // =========================================================================

    public function testShareControllerRendersViewWithPublicImage(): void
    {
        $config       = $this->makeConfig();
        $shareKeyMock = $this->createMock(ShareKeyRepository::class);
        $viewMock     = $this->createMock(ViewRenderer::class);

        $viewMock->expects($this->once())->method('render')
            ->with('pages/share', $this->callback(fn (array $data): bool => $data['is_private_image'] === false
                && $data['filename'] === 'photo.jpg'));

        $controller = new ShareController($config, $shareKeyMock, $viewMock);
        $req = new Request('GET', '/partage.php', ['image' => 'https://example.com/photo.jpg']);
        $controller->show($req);
    }

    public function testShareControllerRendersViewWithPrivateImageAndValidKey(): void
    {
        $config       = $this->makeConfig();
        $shareKeyMock = $this->createMock(ShareKeyRepository::class);
        $viewMock     = $this->createMock(ViewRenderer::class);

        // Clé valide → findValidByKey retourne quelque chose
        $shareKeyMock->method('findValidByKey')->willReturn([
            'id' => 1, 'key_value' => 'abc', 'path' => '/some/path', 'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $viewMock->expects($this->once())->method('render')
            ->with('pages/share', $this->callback(fn (array $data): bool => $data['is_private_image'] === true));

        // Simuler une session admin inexistante
        unset($_SESSION['admin_id']);

        $imageUrl = 'http://localhost/images.php?path=/liste_albums_prives/img.jpg&key=abc';

        $controller = new ShareController($config, $shareKeyMock, $viewMock);
        $req = new Request('GET', '/partage.php', ['image' => $imageUrl]);
        $controller->show($req);
    }

    public function testShareControllerRedirectsOnEmptyImage(): void
    {
        $config       = $this->makeConfig();
        $shareKeyMock = $this->createMock(ShareKeyRepository::class);
        $viewMock     = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->never())->method('render');

        $controller = new ShareController($config, $shareKeyMock, $viewMock);
        $req = new Request('GET', '/partage.php', ['image' => '']);

        $this->expectException(TerminateException::class);
        $controller->show($req);
    }

    public function testShareControllerRedirectsOnInvalidPrivateKey(): void
    {
        $config       = $this->makeConfig();
        $shareKeyMock = $this->createMock(ShareKeyRepository::class);
        $shareKeyMock->method('findValidByKey')->willReturn(null);
        $viewMock     = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->never())->method('render');

        unset($_SESSION['admin_id']);

        $imageUrl = 'http://localhost/images.php?path=/liste_albums_prives/img.jpg&key=badkey';
        $controller = new ShareController($config, $shareKeyMock, $viewMock);
        $req = new Request('GET', '/partage.php', ['image' => $imageUrl]);

        $this->expectException(TerminateException::class);
        $controller->show($req);
    }

    // =========================================================================
    // SettingsController — not logged in redirects
    // =========================================================================

    public function testSettingsControllerRedirectsWhenNotLoggedIn(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ico_settings_');
        file_put_contents($tmpFile, "Mon Site\nDescription\nmon-ico");

        $authMock = $this->createMock(AuthService::class);
        $logMock  = $this->createMock(LogRepository::class);
        $viewMock = $this->createMock(ViewRenderer::class);

        $authMock->method('isLoggedIn')->willReturn(false);
        $viewMock->expects($this->never())->method('render');

        $controller = new SettingsController($authMock, $logMock, $tmpFile, sys_get_temp_dir(), $viewMock);

        $this->expectException(TerminateException::class);
        $controller->index(new Request('GET', '/personnalisation.php'));

        unlink($tmpFile);
    }

    public function testSettingsControllerHandlesPostSuccess(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ico_settings_');
        file_put_contents($tmpFile, "Titre\nDesc\n");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $authMock = $this->createMock(AuthService::class);
        $logMock  = $this->createMock(LogRepository::class);
        $viewMock = $this->createMock(ViewRenderer::class);

        $authMock->method('isLoggedIn')->willReturn(true);
        $authMock->method('getLoggedInAdminId')->willReturn(1);
        $logMock->expects($this->once())->method('log');
        $viewMock->expects($this->never())->method('render');

        $controller = new SettingsController($authMock, $logMock, $tmpFile, sys_get_temp_dir(), $viewMock);
        $req = new Request('POST', '/personnalisation.php', [], [
            'site_title'       => 'New Title',
            'site_description' => 'New Desc',
            'project_path'     => 'my-path',
        ]);

        $this->expectException(TerminateException::class);
        $controller->index($req);

        unlink($tmpFile);
    }

    public function testSettingsControllerReadsFlashMessagesFromSession(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ico_settings_');
        file_put_contents($tmpFile, "Mon Site\nDescription\nmon-ico");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['success_message'] = 'Sauvegardé';
        $_SESSION['error_message']   = 'Erreur test';

        $authMock = $this->createMock(AuthService::class);
        $logMock  = $this->createMock(LogRepository::class);
        $viewMock = $this->createMock(ViewRenderer::class);

        $authMock->method('isLoggedIn')->willReturn(true);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/settings', $this->callback(fn (array $data): bool => $data['success_message'] === 'Sauvegardé'
                && $data['error_message'] === 'Erreur test'));

        $controller = new SettingsController($authMock, $logMock, $tmpFile, sys_get_temp_dir(), $viewMock);
        $controller->index(new Request('GET', '/personnalisation.php'));

        unlink($tmpFile);
        $_SESSION = [];
    }

    public function testSettingsControllerReadsDefaultConfigWhenFileAbsent(): void
    {
        $missingFile = sys_get_temp_dir() . '/ico_nofile_' . uniqid() . '.txt';
        // deliberately do NOT create the file

        $authMock = $this->createMock(AuthService::class);
        $logMock  = $this->createMock(LogRepository::class);
        $viewMock = $this->createMock(ViewRenderer::class);

        $authMock->method('isLoggedIn')->willReturn(true);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/settings', $this->callback(fn (array $data): bool => $data['site_title'] === 'ICO'));

        $controller = new SettingsController($authMock, $logMock, $missingFile, sys_get_temp_dir(), $viewMock);
        $controller->index(new Request('GET', '/personnalisation.php'));
    }

    // =========================================================================
    // LogController — not logged in
    // =========================================================================

    public function testLogControllerRedirectsWhenNotLoggedIn(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['admin_id']);

        $config    = $this->makeConfig();
        $authMock  = $this->createMock(AuthService::class);
        $logMock   = $this->createMock(LogRepository::class);
        $adminMock = $this->createMock(AdminRepository::class);
        $viewMock  = $this->createMock(ViewRenderer::class);

        $authMock->method('isLoggedIn')->willReturn(false);
        $viewMock->expects($this->never())->method('render');

        $controller = new LogController($config, $authMock, $logMock, $adminMock, $viewMock);
        $req = new Request('GET', '/logs.php');

        $this->expectException(TerminateException::class);
        $controller->index($req);
    }

    public function testLogControllerRedirectsWhenNotFirstAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['admin_id'] = 2;

        $config    = $this->makeConfig();
        $authMock  = $this->createMock(AuthService::class);
        $logMock   = $this->createMock(LogRepository::class);
        $adminMock = $this->createMock(AdminRepository::class);
        $viewMock  = $this->createMock(ViewRenderer::class);

        $authMock->method('isLoggedIn')->willReturn(true);
        $authMock->method('getLoggedInAdminId')->willReturn(2);
        $adminMock->method('findFirstAdminId')->willReturn(1);
        $viewMock->expects($this->never())->method('render');

        $controller = new LogController($config, $authMock, $logMock, $adminMock, $viewMock);
        $req = new Request('GET', '/logs.php');

        $this->expectException(TerminateException::class);
        $controller->index($req);

        $_SESSION = [];
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_ctrl_test_' . uniqid();
        mkdir($tmp, 0o775, true);
        file_put_contents($tmp . '/config.txt', "Test Site\nDescription\n");
        file_put_contents($tmp . '/version.txt', '1.0.0');

        $config = Config::fromFile($tmp . '/config.txt', $tmp . '/version.txt');

        unlink($tmp . '/config.txt');
        unlink($tmp . '/version.txt');
        rmdir($tmp);

        return $config;
    }
}
