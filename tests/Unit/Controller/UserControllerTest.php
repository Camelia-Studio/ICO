<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\UserController;
use ICO\Http\TerminateException;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class UserControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    // =========================================================================
    // Not logged in
    // =========================================================================

    public function testHandleRedirectsWhenNotLoggedIn(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // Not first admin
    // =========================================================================

    public function testHandleRedirectsWhenNotFirstAdmin(): void
    {
        $_SESSION['admin_id'] = 2;

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(1);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // GET — renders user list
    // =========================================================================

    public function testHandleGetRendersUserList(): void
    {
        $_SESSION['admin_id'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(1);
        $adminRepo->method('findAll')->willReturn([]);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/users-list', $this->callback(fn (array $d) =>
                isset($d['users'], $d['siteTitle'])
            ));

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo, view: $view);
        $controller->handle();
    }

    // =========================================================================
    // POST — add user
    // =========================================================================

    public function testHandlePostAddUserRedirects(): void
    {
        $_SESSION['admin_id'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'add', 'username' => 'newuser', 'password' => 'Str0ng!PassW0rd'];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('hashPassword')->willReturn('hashed');

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(1);
        $adminRepo->method('usernameExists')->willReturn(false);
        $adminRepo->method('create')->willReturn(2);

        $logRepo = $this->createMock(LogRepository::class);
        $logRepo->expects($this->once())->method('log');

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo, logRepo: $logRepo, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testHandlePostAddUserWithEmptyFieldsSetsError(): void
    {
        $_SESSION['admin_id'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'add', 'username' => '', 'password' => ''];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(1);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testHandlePostAddUserWithDuplicateUsernameSetsError(): void
    {
        $_SESSION['admin_id'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'add', 'username' => 'existing', 'password' => 'Str0ng!PassW0rd'];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(1);
        $adminRepo->method('usernameExists')->willReturn(true);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // POST — edit user
    // =========================================================================

    public function testHandlePostEditUserSuccess(): void
    {
        $_SESSION['admin_id'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'edit', 'user_id' => '2', 'username' => 'edited', 'password' => ''];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(1);
        $adminRepo->method('usernameExists')->willReturn(false);
        $adminRepo->method('update')->willReturn(true);

        $logRepo = $this->createMock(LogRepository::class);
        $logRepo->expects($this->once())->method('log');

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo, logRepo: $logRepo, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // POST — delete user
    // =========================================================================

    public function testHandlePostDeleteUserSuccess(): void
    {
        $_SESSION['admin_id'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'delete', 'user_id' => '2'];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(1);
        $adminRepo->method('delete')->willReturn(true);

        $logRepo = $this->createMock(LogRepository::class);
        $logRepo->expects($this->once())->method('log');

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo, logRepo: $logRepo, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testHandlePostDeleteUserFailure(): void
    {
        $_SESSION['admin_id'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'delete', 'user_id' => '1'];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(1);
        $adminRepo->method('delete')->willReturn(false);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeController(
        ?AuthService      $auth      = null,
        ?AdminRepository  $adminRepo = null,
        ?LogRepository    $logRepo   = null,
        ?ViewRenderer     $view      = null,
    ): UserController {
        $config    = $this->makeConfig();
        $auth      = $auth      ?? $this->createMock(AuthService::class);
        $adminRepo = $adminRepo ?? $this->createMock(AdminRepository::class);
        $logRepo   = $logRepo   ?? $this->createMock(LogRepository::class);
        $pwdVal    = new PasswordValidator();
        $view      = $view      ?? $this->createMock(ViewRenderer::class);

        return new UserController($config, $auth, $adminRepo, $logRepo, $pwdVal, $view);
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_user_cfg_' . uniqid();
        mkdir($tmp, 0775, true);
        file_put_contents($tmp . '/config.txt', "Test Site\nDesc\n");
        file_put_contents($tmp . '/version.txt', '1.0.0');
        $config = Config::fromFile($tmp . '/config.txt', $tmp . '/version.txt');
        unlink($tmp . '/config.txt');
        unlink($tmp . '/version.txt');
        rmdir($tmp);
        return $config;
    }
}
