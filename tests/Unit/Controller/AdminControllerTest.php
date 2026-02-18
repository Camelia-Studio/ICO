<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\AdminController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\AdminRepository;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;
use ICO\Service\UpdateService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

    /**
     * Tests for AdminController — only paths that don't call exit().
     *
     * Previously untestable paths (all called exit unconditionally) are now
     * testable because exit() was replaced by throw new TerminateException().
     */
class AdminControllerTest extends TestCase
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
    // handle() dispatch — GET login renders form
    // =========================================================================

    public function testHandleLoginActionRendersLoginForm(): void
    {
        $_GET['action'] = 'login';

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/admin-login', $this->callback(fn (array $d) =>
                array_key_exists('error', $d) && $d['error'] === null
            ));

        $controller = $this->makeController(view: $view);
        $controller->handle();
    }

    public function testHandleLoginPostFailureRendersWithError(): void
    {
        $_GET['action']  = 'login';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['username' => 'admin', 'password' => 'wrong'];

        $auth = $this->createMock(AuthService::class);
        $auth->method('login')->willReturn(false);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/admin-login', $this->callback(fn (array $d) =>
                $d['error'] === 'Identifiants incorrects'
            ));

        $controller = $this->makeController(auth: $auth, view: $view);
        $controller->handle();
    }

    // =========================================================================
    // handle() dispatch — home (dashboard) when logged in
    // =========================================================================

    public function testHandleDefaultRendersHomeWhenLoggedIn(): void
    {
        $_SESSION['admin_id']      = 1;
        $_SESSION['last_activity'] = time();

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(1);

        $updateService = $this->createMock(UpdateService::class);
        $updateService->method('checkUpdate')->willReturn(null);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/admin-dashboard', $this->callback(fn (array $d) =>
                isset($d['isFirst'], $d['updateAvailable'])
            ));

        $controller = $this->makeController(
            auth:          $auth,
            adminRepo:     $adminRepo,
            updateService: $updateService,
            view:          $view,
        );
        $controller->handle();
    }

    public function testHandleHomeShowsUpdateAvailableBadge(): void
    {
        $_SESSION['admin_id']      = 1;
        $_SESSION['last_activity'] = time();

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findFirstAdminId')->willReturn(2);

        $updateService = $this->createMock(UpdateService::class);
        $updateService->method('checkUpdate')->willReturn(['available' => true, 'latest' => '2.0.0']);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $controller = $this->makeController(
            auth:          $auth,
            adminRepo:     $adminRepo,
            updateService: $updateService,
            view:          $view,
        );
        $controller->handle();

        $this->assertTrue($capturedData['updateAvailable']);
        $this->assertStringContainsString('update-available', $capturedData['menuItemClass']);
    }

    // =========================================================================
    // show_change_password when logged in
    // =========================================================================

    public function testHandleShowChangePasswordRendersFormWhenLoggedIn(): void
    {
        $_GET['action'] = 'show_change_password';

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/admin-change-password', $this->callback(fn (array $d) =>
                isset($d['version'])
            ));

        $controller = $this->makeController(auth: $auth, view: $view);
        $controller->handle();
    }

    // =========================================================================
    // logout — redirects
    // =========================================================================

    public function testLogoutRedirects(): void
    {
        $_GET['action'] = 'logout';

        $auth = $this->createMock(AuthService::class);
        $auth->expects($this->once())->method('logout');

        $controller = $this->makeController(auth: $auth);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // login POST success — redirects
    // =========================================================================

    public function testLoginPostSuccessRedirects(): void
    {
        $_GET['action']            = 'login';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['username' => 'admin', 'password' => 'correct'];

        $auth = $this->createMock(AuthService::class);
        $auth->method('login')->willReturn(true);

        $controller = $this->makeController(auth: $auth);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // home — not logged in, redirects
    // =========================================================================

    public function testHomeRedirectsWhenNotLoggedIn(): void
    {
        // default action = home
        $_GET = [];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // showChangePassword — not logged in, redirects
    // =========================================================================

    public function testShowChangePasswordRedirectsWhenNotLoggedIn(): void
    {
        $_GET['action'] = 'show_change_password';

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $controller = $this->makeController(auth: $auth, view: $view);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // changePassword — various paths
    // =========================================================================

    public function testChangePasswordRedirectsWhenNotLoggedIn(): void
    {
        $_GET['action'] = 'change_password';

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $controller = $this->makeController(auth: $auth);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testChangePasswordRedirectsOnGetRequest(): void
    {
        $_GET['action']            = 'change_password';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $controller = $this->makeController(auth: $auth);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testChangePasswordRedirectsOnWeakPassword(): void
    {
        $_GET['action']            = 'change_password';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['admin_id']      = 1;
        $_POST = [
            'current_password' => 'OldPass1!',
            'new_password'     => 'weak',
            'confirm_password' => 'weak',
        ];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $controller = $this->makeController(auth: $auth);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testChangePasswordRedirectsOnMismatch(): void
    {
        $_GET['action']            = 'change_password';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['admin_id']      = 1;
        $_POST = [
            'current_password' => 'OldPass1!XyZ9',
            'new_password'     => 'NewStr0ng!Pass',
            'confirm_password' => 'Diff3rent!Pass',
        ];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $controller = $this->makeController(auth: $auth);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testChangePasswordRedirectsOnWrongCurrentPassword(): void
    {
        $_GET['action']            = 'change_password';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['admin_id']      = 1;
        $_POST = [
            'current_password' => 'WrongOld1!XyZ',
            'new_password'     => 'NewStr0ng!Pass',
            'confirm_password' => 'NewStr0ng!Pass',
        ];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('verifyPassword')->willReturn(false);

        $controller = $this->makeController(auth: $auth);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testChangePasswordSuccessRedirects(): void
    {
        $_GET['action']            = 'change_password';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['admin_id']      = 1;
        $_POST = [
            'current_password' => 'CorrectOld1!XZ',
            'new_password'     => 'NewStr0ng!Pass',
            'confirm_password' => 'NewStr0ng!Pass',
        ];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('verifyPassword')->willReturn(true);
        $auth->method('hashPassword')->willReturn('hashed');

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('updatePassword')->willReturn(true);

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo);

        $this->expectException(TerminateException::class);
        $controller->handle();

        $this->assertArrayHasKey('success_message', $_SESSION);
    }

    public function testChangePasswordFailureRedirects(): void
    {
        $_GET['action']            = 'change_password';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['admin_id']      = 1;
        $_POST = [
            'current_password' => 'CorrectOld1!XZ',
            'new_password'     => 'NewStr0ng!Pass',
            'confirm_password' => 'NewStr0ng!Pass',
        ];

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('verifyPassword')->willReturn(true);
        $auth->method('hashPassword')->willReturn('hashed');

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('updatePassword')->willReturn(false);

        $controller = $this->makeController(auth: $auth, adminRepo: $adminRepo);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeController(
        ?AuthService      $auth          = null,
        ?AdminRepository  $adminRepo     = null,
        ?UpdateService    $updateService = null,
        ?ViewRenderer     $view          = null,
    ): AdminController {
        $config    = $this->makeConfig();
        $auth      = $auth          ?? $this->createMock(AuthService::class);
        $adminRepo = $adminRepo     ?? $this->createMock(AdminRepository::class);
        $pwdVal    = new PasswordValidator();
        $updSvc    = $updateService ?? $this->createMock(UpdateService::class);
        $view      = $view          ?? $this->createMock(ViewRenderer::class);

        return new AdminController($config, $auth, $adminRepo, $pwdVal, $updSvc, $view);
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_admin_cfg_' . uniqid();
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
