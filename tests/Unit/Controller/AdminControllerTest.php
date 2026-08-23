<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Config\VestikanConfig;
use ICO\Controller\AdminController;
use ICO\Http\TerminateException;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;
use ICO\Service\UpdateService;
use ICO\Service\VestikanClientFactory;
use ICO\Service\VestikanClientInterface;
use ICO\Service\VestikanLinkService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;
use VestikanException;

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
            ->with('pages/admin-login', $this->callback(
                fn (array $d): bool =>
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
            ->with('pages/admin-login', $this->callback(
                fn (array $d): bool =>
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
            ->with('pages/admin-dashboard', $this->callback(
                fn (array $d): bool =>
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
            ->with('pages/admin-change-password', $this->callback(
                fn (array $d): bool =>
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
    // login POST success — lie le vestikan_id en attente
    // =========================================================================

    public function testLoginPostSuccessLinksPendingVestikanId(): void
    {
        $_GET['action']            = 'login';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['username' => 'admin', 'password' => 'correct'];
        $_SESSION['pending_vestikan_id'] = 'abc123';

        $auth = $this->createMock(AuthService::class);
        $auth->method('login')->willReturnCallback(function (): true {
            $_SESSION['admin_id'] = 1;
            return true;
        });

        $vestikanLink = $this->createMock(VestikanLinkService::class);
        $vestikanLink->expects($this->once())->method('link')->with('abc123', 1);

        $logRepo = $this->createMock(LogRepository::class);
        $logRepo->expects($this->once())->method('log')->with(1, 'LINK_VESTIKAN', $this->anything());

        $controller = $this->makeController(auth: $auth, vestikanLink: $vestikanLink, logRepo: $logRepo);

        $this->expectException(TerminateException::class);
        $controller->handle();

        $this->assertArrayNotHasKey('pending_vestikan_id', $_SESSION);
    }

    // =========================================================================
    // vestikan_login
    // =========================================================================

    public function testVestikanLoginRedirectsToLoginWhenNotConfigured(): void
    {
        $_GET['action'] = 'vestikan_login';

        $controller = $this->makeController();

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testVestikanLoginRedirectsToAuthorizeUrl(): void
    {
        $_GET['action'] = 'vestikan_login';

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $client = $this->createMock(VestikanClientInterface::class);
        $client->expects($this->once())->method('authorizeUrl')->with(null)
            ->willReturn('https://vestikan.example/authorize?state=xyz');

        $factory = $this->createMock(VestikanClientFactory::class);
        $factory->method('create')->willReturn($client);

        $controller = $this->makeController(
            auth: $auth,
            vestikanConfig: $this->makeVestikanConfig(configured: true),
            vestikanClientFactory: $factory,
        );

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    // =========================================================================
    // vestikan_callback
    // =========================================================================

    public function testVestikanCallbackRedirectsToLoginWhenNotConfigured(): void
    {
        $_GET['action'] = 'vestikan_callback';

        $controller = $this->makeController();

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testVestikanCallbackShowsErrorWhenFlowFails(): void
    {
        $_GET['action'] = 'vestikan_callback';

        $client = $this->createMock(VestikanClientInterface::class);
        $client->method('complete')->willThrowException(new VestikanException('State invalide'));

        $factory = $this->createMock(VestikanClientFactory::class);
        $factory->method('create')->willReturn($client);

        $controller = $this->makeController(
            vestikanConfig: $this->makeVestikanConfig(configured: true),
            vestikanClientFactory: $factory,
        );

        $this->expectException(TerminateException::class);
        $controller->handle();

        $this->assertArrayHasKey('error_message', $_SESSION);
    }

    public function testVestikanCallbackLogsInWhenAlreadyLinked(): void
    {
        $_GET['action'] = 'vestikan_callback';

        $client = $this->createMock(VestikanClientInterface::class);
        $client->method('complete')->willReturn('vestikan-id-1');
        $client->method('popReturnTo')->willReturn(null);

        $factory = $this->createMock(VestikanClientFactory::class);
        $factory->method('create')->willReturn($client);

        $vestikanLink = $this->createMock(VestikanLinkService::class);
        $vestikanLink->method('resolveAdminId')->with('vestikan-id-1')->willReturn(1);

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findById')->with(1)->willReturn(['id' => 1, 'username' => 'admin']);

        $controller = $this->makeController(
            adminRepo: $adminRepo,
            vestikanConfig: $this->makeVestikanConfig(configured: true),
            vestikanLink: $vestikanLink,
            vestikanClientFactory: $factory,
        );

        $this->expectException(TerminateException::class);
        $controller->handle();

        $this->assertSame(1, $_SESSION['admin_id']);
        $this->assertSame('admin', $_SESSION['admin_username']);
    }

    public function testVestikanCallbackLinksToCurrentAdminWhenAlreadyLoggedIn(): void
    {
        $_GET['action']       = 'vestikan_callback';
        $_SESSION['admin_id'] = 2;

        $client = $this->createMock(VestikanClientInterface::class);
        $client->method('complete')->willReturn('vestikan-id-2');

        $factory = $this->createMock(VestikanClientFactory::class);
        $factory->method('create')->willReturn($client);

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $vestikanLink = $this->createMock(VestikanLinkService::class);
        $vestikanLink->method('resolveAdminId')->willReturn(null);
        $vestikanLink->expects($this->once())->method('link')->with('vestikan-id-2', 2);

        $logRepo = $this->createMock(LogRepository::class);
        $logRepo->expects($this->once())->method('log')->with(2, 'LINK_VESTIKAN', $this->anything());

        $controller = $this->makeController(
            auth: $auth,
            vestikanConfig: $this->makeVestikanConfig(configured: true),
            vestikanLink: $vestikanLink,
            logRepo: $logRepo,
            vestikanClientFactory: $factory,
        );

        $this->expectException(TerminateException::class);
        $controller->handle();

        $this->assertArrayHasKey('success_message', $_SESSION);
    }

    public function testVestikanCallbackStoresPendingIdWhenNotLoggedIn(): void
    {
        $_GET['action'] = 'vestikan_callback';

        $client = $this->createMock(VestikanClientInterface::class);
        $client->method('complete')->willReturn('vestikan-id-3');

        $factory = $this->createMock(VestikanClientFactory::class);
        $factory->method('create')->willReturn($client);

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $vestikanLink = $this->createMock(VestikanLinkService::class);
        $vestikanLink->method('resolveAdminId')->willReturn(null);
        $vestikanLink->expects($this->never())->method('link');

        $controller = $this->makeController(
            auth: $auth,
            vestikanConfig: $this->makeVestikanConfig(configured: true),
            vestikanLink: $vestikanLink,
            vestikanClientFactory: $factory,
        );

        $this->expectException(TerminateException::class);
        $controller->handle();

        $this->assertSame('vestikan-id-3', $_SESSION['pending_vestikan_id']);
    }

    // =========================================================================
    // unlink_vestikan
    // =========================================================================

    public function testUnlinkVestikanRedirectsWhenNotLoggedIn(): void
    {
        $_GET['action'] = 'unlink_vestikan';

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $controller = $this->makeController(auth: $auth);

        $this->expectException(TerminateException::class);
        $controller->handle();
    }

    public function testUnlinkVestikanRemovesExistingLink(): void
    {
        $_GET['action']       = 'unlink_vestikan';
        $_SESSION['admin_id'] = 1;

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $vestikanLink = $this->createMock(VestikanLinkService::class);
        $vestikanLink->method('findLinkedVestikanId')->with(1)->willReturn('vestikan-id-1');
        $vestikanLink->expects($this->once())->method('unlink')->with('vestikan-id-1');

        $logRepo = $this->createMock(LogRepository::class);
        $logRepo->expects($this->once())->method('log')->with(1, 'UNLINK_VESTIKAN', $this->anything());

        $controller = $this->makeController(auth: $auth, vestikanLink: $vestikanLink, logRepo: $logRepo);

        $this->expectException(TerminateException::class);
        $controller->handle();

        $this->assertArrayHasKey('success_message', $_SESSION);
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeController(
        ?AuthService            $auth                  = null,
        ?AdminRepository        $adminRepo             = null,
        ?UpdateService          $updateService          = null,
        ?ViewRenderer           $view                  = null,
        ?VestikanConfig         $vestikanConfig        = null,
        ?VestikanLinkService    $vestikanLink          = null,
        ?LogRepository          $logRepo               = null,
        ?VestikanClientFactory  $vestikanClientFactory = null,
    ): AdminController {
        $config    = $this->makeConfig();
        $auth ??= $this->createMock(AuthService::class);
        $adminRepo ??= $this->createMock(AdminRepository::class);
        $pwdVal    = new PasswordValidator();
        $updSvc    = $updateService ?? $this->createMock(UpdateService::class);
        $view ??= $this->createMock(ViewRenderer::class);
        $vestikanConfig ??= $this->makeVestikanConfig(configured: false);
        $vestikanLink ??= $this->createMock(VestikanLinkService::class);
        $logRepo ??= $this->createMock(LogRepository::class);
        $vestikanClientFactory ??= $this->createMock(VestikanClientFactory::class);

        return new AdminController(
            $config,
            $auth,
            $adminRepo,
            $pwdVal,
            $updSvc,
            $view,
            $vestikanConfig,
            $vestikanLink,
            $logRepo,
            $vestikanClientFactory,
        );
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_admin_cfg_' . uniqid();
        mkdir($tmp, 0o775, true);
        file_put_contents($tmp . '/config.txt', "Test Site\nDesc\n");
        file_put_contents($tmp . '/version.txt', '1.0.0');
        $config = Config::fromFile($tmp . '/config.txt', $tmp . '/version.txt');
        unlink($tmp . '/config.txt');
        unlink($tmp . '/version.txt');
        rmdir($tmp);
        return $config;
    }

    private function makeVestikanConfig(bool $configured): VestikanConfig
    {
        $tmp = sys_get_temp_dir() . '/ico_vestikan_cfg_' . uniqid() . '.php';

        if ($configured) {
            file_put_contents($tmp, "<?php\nreturn [\n"
                . "    'base_url' => 'https://vestikan.example',\n"
                . "    'client_id' => 'vk_client_test',\n"
                . "    'client_secret' => 'secret',\n"
                . "    'redirect_uri' => 'https://ico.example/admin.php?action=vestikan_callback',\n"
                . "];\n");
        }

        $config = VestikanConfig::fromFile($tmp);

        if (file_exists($tmp)) {
            unlink($tmp);
        }

        return $config;
    }
}
