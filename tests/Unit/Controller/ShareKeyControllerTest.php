<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\ShareKeyController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\LogRepository;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class ShareKeyControllerTest extends TestCase
{
    private string $tmpDir;

    private string $albumsRoot;

    private string $privateRoot;

    protected function setUp(): void
    {
        $this->tmpDir      = sys_get_temp_dir() . '/ico_sharekey_test_' . uniqid();
        $this->albumsRoot  = $this->tmpDir . '/liste_albums';
        $this->privateRoot = $this->tmpDir . '/liste_albums_prives';
        mkdir($this->albumsRoot, 0o775, true);
        mkdir($this->privateRoot, 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
    }

    // =========================================================================
    // index() — not logged in
    // =========================================================================

    public function testIndexRedirectsWhenNotLoggedIn(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $shareKeyRepo   = $this->createMock(ShareKeyRepository::class);
        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $view           = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $request    = new Request('GET', '/cles-partage.php');
        $controller = $this->makeController($auth, $shareKeyRepo, $albumIdentRepo, null, $view);

        $this->expectException(TerminateException::class);
        $controller->index($request);
    }

    // =========================================================================
    // index() — GET, logged in
    // =========================================================================

    public function testIndexRendersViewWhenLoggedIn(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findAll')->willReturn([]);

        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $albumIdentRepo->method('findAll')->willReturn([]);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/share-keys', $this->callback(
                fn (array $d): bool =>
                isset($d['keys'], $d['albums'], $d['filter'])
            ));

        $request    = new Request('GET', '/cles-partage.php', ['filter' => 'active']);
        $controller = $this->makeController($auth, $shareKeyRepo, $albumIdentRepo, null, $view);
        $controller->index($request);
    }

    public function testIndexEnrichesKeys(): void
    {
        $albumPath = $this->albumsRoot . '/album1';
        mkdir($albumPath, 0o775, true);
        file_put_contents($albumPath . '/infos.txt', "Album 1\nDesc\n18-");

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findAll')->willReturn([[
            'id'               => 1,
            'key_value'        => 'abc123',
            'path'             => $albumPath,
            'album_identifier' => 'uuid-1',
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'created_at'       => date('Y-m-d H:i:s'),
            'comment'          => '',
        ]]);

        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $albumIdentRepo->method('findAll')->willReturn([]);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/cles-partage.php');
        $controller = $this->makeController($auth, $shareKeyRepo, $albumIdentRepo, null, $view);
        $controller->index($request);

        $this->assertCount(1, $capturedData['keys']);
        $this->assertArrayHasKey('album_title', $capturedData['keys'][0]);
        $this->assertArrayHasKey('share_url', $capturedData['keys'][0]);
        $this->assertArrayHasKey('is_expired', $capturedData['keys'][0]);
    }

    // =========================================================================
    // index() — POST delete_key
    // =========================================================================

    public function testIndexPostDeleteKeySuccess(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findAll')->willReturn([]);
        $shareKeyRepo->expects($this->once())->method('deleteById')->with(42)->willReturn(true);

        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $albumIdentRepo->method('findAll')->willReturn([]);

        $logRepo = $this->createMock(LogRepository::class);
        $logRepo->expects($this->once())->method('log');

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('POST', '/cles-partage.php', [], ['action' => 'delete_key', 'key_id' => '42']);
        $controller = $this->makeController($auth, $shareKeyRepo, $albumIdentRepo, $logRepo, $view);
        $controller->index($request);

        $this->assertSame('Clé supprimée avec succès.', $capturedData['success_message']);
    }

    public function testIndexPostDeleteKeyInvalidId(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findAll')->willReturn([]);

        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $albumIdentRepo->method('findAll')->willReturn([]);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('POST', '/cles-partage.php', [], ['action' => 'delete_key', 'key_id' => '0']);
        $controller = $this->makeController($auth, $shareKeyRepo, $albumIdentRepo, null, $view);
        $controller->index($request);

        $this->assertSame('Identifiant de clé invalide.', $capturedData['error_message']);
    }

    public function testIndexPostCleanExpiredWithMatches(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findAll')->willReturn([]);
        $shareKeyRepo->expects($this->once())->method('deleteExpired')->willReturn(3);

        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $albumIdentRepo->method('findAll')->willReturn([]);

        $logRepo = $this->createMock(LogRepository::class);
        $logRepo->expects($this->once())->method('log');

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('POST', '/cles-partage.php', [], ['action' => 'clean_expired']);
        $controller = $this->makeController($auth, $shareKeyRepo, $albumIdentRepo, $logRepo, $view);
        $controller->index($request);

        $this->assertStringContainsString('3', $capturedData['success_message']);
    }

    public function testIndexPostCleanExpiredWithNone(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findAll')->willReturn([]);
        $shareKeyRepo->expects($this->once())->method('deleteExpired')->willReturn(0);

        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $albumIdentRepo->method('findAll')->willReturn([]);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('POST', '/cles-partage.php', [], ['action' => 'clean_expired']);
        $controller = $this->makeController($auth, $shareKeyRepo, $albumIdentRepo, null, $view);
        $controller->index($request);

        $this->assertStringContainsString('Aucune', $capturedData['success_message']);
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeController(
        AuthService $auth,
        ShareKeyRepository $shareKeyRepo,
        AlbumIdentifierRepository $albumIdentRepo,
        ?LogRepository $logRepo,
        ViewRenderer $view,
    ): ShareKeyController {
        $config       = $this->makeConfig();
        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $log          = $logRepo ?? $this->createMock(LogRepository::class);

        return new ShareKeyController(
            $config,
            $auth,
            $shareKeyRepo,
            $albumIdentRepo,
            $albumService,
            $log,
            'http://localhost',
            $view,
        );
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_sharekey_cfg_' . uniqid();
        mkdir($tmp, 0o775, true);
        file_put_contents($tmp . '/config.txt', "Test Site\nDesc\n");
        file_put_contents($tmp . '/version.txt', '1.0.0');
        $config = Config::fromFile($tmp . '/config.txt', $tmp . '/version.txt');
        unlink($tmp . '/config.txt');
        unlink($tmp . '/version.txt');
        rmdir($tmp);
        return $config;
    }

    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirRecursive($path) : unlink($path);
        }

        rmdir($dir);
    }
}
