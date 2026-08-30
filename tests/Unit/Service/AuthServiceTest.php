<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Service;

use ICO\Config\Config;
use ICO\Enum\UserRole;
use ICO\Repository\AdminRepository;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\PrivateAlbumAccessRepository;
use ICO\Service\AuthService;
use ICO\Service\SessionCookieService;
use ICO\Tests\Support\DatabaseTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    use DatabaseTestTrait;

    private AdminRepository $adminRepo;

    private AuthService     $auth;

    private SessionCookieService&MockObject $sessionCookie;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->adminRepo     = new AdminRepository($this->pdo);
        $this->sessionCookie = $this->createMock(SessionCookieService::class);
        $config              = Config::fromFile('/nonexistent-config.txt', '/nonexistent-version.txt');
        $this->auth          = new AuthService($this->adminRepo, $config, $this->sessionCookie);

        // Démarrer une session propre pour chaque test
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // -------------------------------------------------------------------------
    // hashPassword / verifyPassword
    // -------------------------------------------------------------------------

    public function testHashPasswordProducesValidBcryptHash(): void
    {
        $hash = $this->auth->hashPassword('secret');

        $this->assertTrue(password_verify('secret', $hash));
    }

    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        $hash = $this->auth->hashPassword('correct');
        $id   = $this->adminRepo->create('alice', $hash);

        $this->assertTrue($this->auth->verifyPassword($id, 'correct'));
    }

    public function testVerifyPasswordReturnsFalseForWrongPassword(): void
    {
        $hash = $this->auth->hashPassword('correct');
        $id   = $this->adminRepo->create('bob', $hash);

        $this->assertFalse($this->auth->verifyPassword($id, 'wrong'));
    }

    public function testVerifyPasswordReturnsFalseForUnknownAdmin(): void
    {
        $this->assertFalse($this->auth->verifyPassword(999, 'any'));
    }

    // -------------------------------------------------------------------------
    // login
    // -------------------------------------------------------------------------

    public function testLoginSucceedsWithCorrectCredentials(): void
    {
        $hash = $this->auth->hashPassword('pass');
        $id   = $this->adminRepo->create('charlie', $hash);

        $result = $this->auth->login('charlie', 'pass');

        $this->assertTrue($result);
        $this->assertSame($id, $_SESSION['admin_id']);
        $this->assertSame('charlie', $_SESSION['admin_username']);
        $this->assertArrayHasKey('last_activity', $_SESSION);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $hash = $this->auth->hashPassword('pass');
        $this->adminRepo->create('diana', $hash);

        $result = $this->auth->login('diana', 'wrong');

        $this->assertFalse($result);
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

    public function testLoginFailsWithUnknownUsername(): void
    {
        $result = $this->auth->login('nobody', 'pass');

        $this->assertFalse($result);
    }

    public function testVisitorIsAuthenticatedWithoutAdministrativeAccess(): void
    {
        $this->adminRepo->create('principal', $this->auth->hashPassword('pass'));
        $this->adminRepo->create('visitor', $this->auth->hashPassword('pass'), UserRole::VISITOR);

        $this->assertTrue($this->auth->login('visitor', 'pass'));
        $this->assertTrue($this->auth->isAuthenticated());
        $this->assertFalse($this->auth->isLoggedIn());
    }

    public function testVisitorCanOnlyAccessAssignedPrivateAlbum(): void
    {
        $this->adminRepo->create('principal', $this->auth->hashPassword('pass'));
        $visitorId = $this->adminRepo->create('visitor', $this->auth->hashPassword('pass'), UserRole::VISITOR);
        $identifierRepository = new AlbumIdentifierRepository($this->pdo);
        $identifier = $identifierRepository->create('assigned', '/private/assigned');
        $accessRepository = new PrivateAlbumAccessRepository($this->pdo);
        $accessRepository->replaceForUser($visitorId, [$identifier]);

        $config = Config::fromFile('/nonexistent-config.txt', '/nonexistent-version.txt');
        $auth = new AuthService($this->adminRepo, $config, $this->sessionCookie, $accessRepository);
        $auth->login('visitor', 'pass');

        $this->assertTrue($auth->canAccessPrivatePath('/private/assigned/photo.jpg'));
        $this->assertFalse($auth->canAccessPrivatePath('/private/other/photo.jpg'));
    }

    // -------------------------------------------------------------------------
    // isLoggedIn
    // -------------------------------------------------------------------------

    public function testIsLoggedInReturnsFalseWhenNoSession(): void
    {
        $this->assertFalse($this->auth->isLoggedIn());
    }

    public function testIsLoggedInReturnsTrueForValidSession(): void
    {
        $_SESSION['admin_id']      = 1;
        $_SESSION['last_activity'] = time();

        $this->assertTrue($this->auth->isLoggedIn());
    }

    public function testIsLoggedInReturnsFalseForExpiredSession(): void
    {
        $_SESSION['admin_id']      = 1;
        // Activité il y a 7 jours + 1s → expiré
        $_SESSION['last_activity'] = time() - (604800 + 1);

        $this->assertFalse($this->auth->isLoggedIn());
    }

    public function testIsLoggedInUpdatesLastActivity(): void
    {
        $before = time() - 10;
        $_SESSION['admin_id']      = 1;
        $_SESSION['last_activity'] = $before;

        $this->auth->isLoggedIn();

        $this->assertGreaterThan($before, $_SESSION['last_activity']);
    }

    public function testIsLoggedInRefreshesSessionCookie(): void
    {
        $_SESSION['admin_id']      = 1;
        $_SESSION['last_activity'] = time();

        $this->sessionCookie->expects($this->once())
            ->method('refresh')
            ->with($this->greaterThanOrEqual(time() + 604800 - 1));

        $this->auth->isLoggedIn();
    }

    public function testIsLoggedInDoesNotRefreshCookieWhenNotLoggedIn(): void
    {
        $this->sessionCookie->expects($this->never())->method('refresh');

        $this->auth->isLoggedIn();
    }

    // -------------------------------------------------------------------------
    // getLoggedInAdminId / getLoggedInUsername
    // -------------------------------------------------------------------------

    public function testGetLoggedInAdminIdReturnsIdWhenLoggedIn(): void
    {
        $_SESSION['admin_id'] = 42;

        $this->assertSame(42, $this->auth->getLoggedInAdminId());
    }

    public function testGetLoggedInAdminIdReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull($this->auth->getLoggedInAdminId());
    }

    public function testGetLoggedInUsernameReturnsUsernameWhenLoggedIn(): void
    {
        $_SESSION['admin_username'] = 'eve';

        $this->assertSame('eve', $this->auth->getLoggedInUsername());
    }

    public function testGetLoggedInUsernameReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull($this->auth->getLoggedInUsername());
    }

    // -------------------------------------------------------------------------
    // logout
    // -------------------------------------------------------------------------

    public function testLogoutDestroysSession(): void
    {
        $_SESSION['admin_id'] = 1;

        $this->sessionCookie->expects($this->once())->method('expire');

        $this->auth->logout();

        // Après logout, isLoggedIn doit retourner false
        $this->assertFalse($this->auth->isLoggedIn());
    }
}
