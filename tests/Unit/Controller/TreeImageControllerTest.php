<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\TreeImageController;
use ICO\Http\TerminateException;
use ICO\Repository\CarouselPositionRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\Service\PathService;
use ICO\Tests\Support\DatabaseTestTrait;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class TreeImageControllerTest extends TestCase
{
    use DatabaseTestTrait;

    private string $tmpDir;

    private string $albumsRoot;

    private string $privateRoot;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $this->tmpDir      = sys_get_temp_dir() . '/ico_treeimg_test_' . uniqid();
        $this->albumsRoot  = $this->tmpDir . '/liste_albums';
        $this->privateRoot = $this->tmpDir . '/liste_albums_prives';

        mkdir($this->albumsRoot . '/album1', 0o775, true);
        mkdir($this->privateRoot . '/secret', 0o775, true);

        file_put_contents($this->albumsRoot . '/album1/infos.txt', "Album 1\nDesc\n18-\n");
        file_put_contents($this->privateRoot . '/secret/infos.txt', "Secret\nDesc\n18-\n");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = ['admin_id' => 1];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST']      = 'localhost';
        $_SERVER['HTTPS']          = '';
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
    }

    // =========================================================================
    // handlePublic — redirect when not logged in
    // =========================================================================

    public function testHandlePublicRedirectsWhenNotLoggedIn(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(false);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->never())->method('render');

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePublic — redirect on invalid path
    // =========================================================================

    public function testHandlePublicRedirectsOnInvalidPath(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_GET['path'] = '/tmp/outside-albums';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePublic — render when logged in
    // =========================================================================

    public function testHandlePublicRendersViewWhenLoggedIn(): void
    {
        file_put_contents($this->albumsRoot . '/album1/photo.jpg', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/tree-image-public', $this->callback(fn (array $d): bool => isset($d['imageData'], $d['siteTitle'])));

        $_GET['path'] = 'liste_albums/album1';

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePublic — POST toggle_top
    // =========================================================================

    public function testHandlePublicPostToggleTop(): void
    {
        file_put_contents($this->albumsRoot . '/album1/photo.jpg', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['path']              = 'liste_albums/album1';
        $_POST['action']           = 'toggle_top';
        $_POST['image']            = 'photo.jpg';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePublic — carrousel toggle_top exclusif
    // =========================================================================

    public function testCarouselToggleTopIsExclusive(): void
    {
        $carouselDir = $this->tmpDir . '/img_carrousel';
        mkdir($carouselDir, 0o775, true);
        file_put_contents($carouselDir . '/already--top--.jpg', '');
        file_put_contents($carouselDir . '/new.jpg', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['path']              = 'img_carrousel';
        $_POST['action']           = 'toggle_top';
        $_POST['image']            = 'new.jpg';

        try {
            $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
            $controller->handlePublic();
        } catch (TerminateException) {
        }

        $this->assertFileDoesNotExist($carouselDir . '/already--top--.jpg');
        $this->assertFileExists($carouselDir . '/already.jpg');
        $this->assertFileExists($carouselDir . '/new--top--.jpg');
    }

    // =========================================================================
    // handlePublic — POST reorder (carrousel)
    // =========================================================================

    public function testCarouselReorderPersistsPositionAndReflectsInListing(): void
    {
        $carouselDir = $this->tmpDir . '/img_carrousel';
        mkdir($carouselDir, 0o775, true);
        file_put_contents($carouselDir . '/a.jpg', '');
        file_put_contents($carouselDir . '/b.jpg', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['path']              = 'img_carrousel';
        $_POST['action']           = 'reorder';
        $_POST['order']            = ['b.jpg', 'a.jpg'];

        try {
            $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
            $controller->handlePublic();
        } catch (TerminateException) {
        }

        $this->assertSame(['b.jpg' => 0, 'a.jpg' => 1], (new CarouselPositionRepository($this->pdo))->findAll());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_POST['action'], $_POST['order']);

        $capturedData = null;
        $viewMock     = $this->createMock(ViewRenderer::class);
        $viewMock->method('render')->willReturnCallback(
            function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            }
        );

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePublic();

        $names = array_column($capturedData['imageData'], 'name');
        $this->assertSame(['b.jpg', 'a.jpg'], $names);
    }

    public function testTreeImagePagesExposeBreadcrumbs(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $capturedData = null;
        $viewMock     = $this->createMock(ViewRenderer::class);
        $viewMock->method('render')->willReturnCallback(
            function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            }
        );

        $_GET['path'] = 'liste_albums/album1';

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePublic();

        $this->assertSame('Album 1', $capturedData['breadcrumbs'][1]['label']);
    }

    // =========================================================================
    // handlePublic — POST delete
    // =========================================================================

    public function testHandlePublicPostDelete(): void
    {
        file_put_contents($this->albumsRoot . '/album1/to-delete.jpg', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $logMock = $this->createMock(LogRepository::class);
        $logMock->expects($this->once())->method('log');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['path']              = 'liste_albums/album1';
        $_POST['action']           = 'delete';
        $_POST['images']           = ['to-delete.jpg'];

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class), $logMock);
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePublic — POST move (invalid destination)
    // =========================================================================

    public function testHandlePublicPostMoveInvalidDestination(): void
    {
        file_put_contents($this->albumsRoot . '/album1/img.jpg', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_GET['path']               = 'liste_albums/album1';
        $_POST['action']            = 'move';
        $_POST['images']            = ['img.jpg'];
        $_POST['destination_path']  = '/tmp/nonexistent-outside';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePrivate — redirect when not logged in
    // =========================================================================

    public function testHandlePrivateRedirectsWhenNotLoggedIn(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(false);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->never())->method('render');

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePrivate — redirect on invalid path
    // =========================================================================

    public function testHandlePrivateRedirectsOnInvalidPath(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_GET['path'] = '/tmp/outside-albums';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePrivate — render when logged in
    // =========================================================================

    public function testHandlePrivateRendersViewWhenLoggedIn(): void
    {
        file_put_contents($this->privateRoot . '/secret/img.png', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/tree-image-private', $this->callback(fn (array $d): bool => isset($d['imageData'], $d['siteTitle'])));

        $_GET['path'] = 'liste_albums_prives/secret';

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePrivate — POST toggle_top
    // =========================================================================

    public function testHandlePrivatePostToggleTop(): void
    {
        file_put_contents($this->privateRoot . '/secret/img.png', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['path']              = 'liste_albums_prives/secret';
        $_POST['action']           = 'toggle_top';
        $_POST['image']            = 'img.png';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePrivate — POST delete
    // =========================================================================

    public function testHandlePrivatePostDelete(): void
    {
        file_put_contents($this->privateRoot . '/secret/to-delete.png', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $logMock = $this->createMock(LogRepository::class);
        $logMock->expects($this->once())->method('log');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['path']              = 'liste_albums_prives/secret';
        $_POST['action']           = 'delete';
        $_POST['images']           = ['to-delete.png'];

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class), $logMock);
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePublic — POST move valid destination
    // =========================================================================

    public function testHandlePublicPostMoveValidDestination(): void
    {
        $destDir = $this->albumsRoot . '/album2';
        mkdir($destDir, 0o775, true);
        file_put_contents($destDir . '/infos.txt', "Album 2\nDesc\n18-\n");
        file_put_contents($this->albumsRoot . '/album1/img.jpg', 'data');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $logMock = $this->createMock(LogRepository::class);
        $logMock->expects($this->once())->method('log');

        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_GET['path']               = 'liste_albums/album1';
        $_POST['action']            = 'move';
        $_POST['images']            = ['img.jpg'];
        $_POST['destination_path']  = 'liste_albums/album2';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class), $logMock);
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePublic — POST upload (no files = no-op)
    // =========================================================================

    public function testHandlePublicPostUploadNoFiles(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['path']              = 'liste_albums/album1';
        $_POST['action']           = 'upload';
        $_FILES                    = [];

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
        $controller->handlePublic();
    }

    public function testHandlePrivatePostUploadNoFiles(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['path']              = 'liste_albums_prives/secret';
        $_POST['action']           = 'upload';
        $_FILES                    = [];

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
        $controller->handlePrivate();
    }

    // =========================================================================
    // Vidéos dans l'arbre public
    // =========================================================================

    public function testHandlePublicListsVideoFiles(): void
    {
        file_put_contents($this->albumsRoot . '/album1/photo.jpg', '');
        file_put_contents($this->albumsRoot . '/album1/clip.mp4', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $capturedData = null;
        $viewMock     = $this->createMock(ViewRenderer::class);
        $viewMock->method('render')->willReturnCallback(
            function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            }
        );

        $_GET['path'] = 'liste_albums/album1';

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePublic();

        $names = array_column($capturedData['imageData'], 'name');
        $this->assertContains('clip.mp4', $names);
        $this->assertContains('photo.jpg', $names);

        $videoItem = array_values(array_filter($capturedData['imageData'], fn (array $i): bool => $i['name'] === 'clip.mp4'))[0];
        $this->assertSame('video', $videoItem['type']);
        $this->assertSame('video/mp4', $videoItem['mime']);

        $imageItem = array_values(array_filter($capturedData['imageData'], fn (array $i): bool => $i['name'] === 'photo.jpg'))[0];
        $this->assertSame('image', $imageItem['type']);
        $this->assertNull($imageItem['mime']);
    }

    public function testHandlePrivateVideoUsesVideosPhpUrl(): void
    {
        file_put_contents($this->privateRoot . '/secret/clip.mp4', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $capturedData = null;
        $viewMock     = $this->createMock(ViewRenderer::class);
        $viewMock->method('render')->willReturnCallback(
            function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            }
        );

        $_GET['path'] = 'liste_albums_prives/secret';

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePrivate();

        $videoItem = $capturedData['imageData'][0];
        $this->assertSame('video', $videoItem['type']);
        $this->assertStringContainsString('videos.php', $videoItem['url']);
        $this->assertStringNotContainsString('images.php', $videoItem['url']);
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeController(
        AuthService $auth,
        ViewRenderer $view,
        ?LogRepository $logMock = null,
    ): TreeImageController {
        $config       = $this->makeConfig();
        $log          = $logMock ?? $this->createMock(LogRepository::class);
        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot, $this->tmpDir . '/img_carrousel');

        return new TreeImageController(
            $config,
            new PathService($this->tmpDir, 'http://localhost'),
            $auth,
            $albumService,
            $log,
            $view,
            new CarouselPositionRepository($this->pdo),
        );
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_treeimg_cfg_' . uniqid();
        mkdir($tmp, 0o775, true);
        file_put_contents($tmp . '/config.txt', "Test Site\n\njpg,png,gif\n");
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
