<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\GalleryController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\FileService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class GalleryControllerTest extends TestCase
{
    private string $tmpDir;

    private string $albumsRoot;

    private string $privateRoot;

    protected function setUp(): void
    {
        $this->tmpDir     = sys_get_temp_dir() . '/ico_gallery_test_' . uniqid();
        $this->albumsRoot = $this->tmpDir . '/liste_albums';
        $this->privateRoot = $this->tmpDir . '/liste_albums_prives';
        mkdir($this->albumsRoot . '/album1', 0o775, true);
        mkdir($this->privateRoot . '/secret', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
    }

    // =========================================================================
    // show() — public gallery
    // =========================================================================

    public function testShowRendersPublicGalleryWithImages(): void
    {
        file_put_contents($this->albumsRoot . '/album1/photo.jpg', '');

        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);
        $shareKeyRepo  = $this->createMock(ShareKeyRepository::class);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries.php', ['path' => $this->albumsRoot . '/album1']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->show($request);

        $this->assertSame('pages/gallery-public', 'pages/gallery-public');
        $this->assertCount(1, $capturedData['images']);
        $this->assertNotNull($capturedData['header_image']);
    }

    public function testShowRendersPublicGalleryWithEmptyDir(): void
    {
        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
        $shareKeyRepo  = $this->createMock(ShareKeyRepository::class);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/gallery-public', $this->callback(fn (array $d): bool => $d['images'] === []))
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries.php', ['path' => $this->albumsRoot . '/album1']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->show($request);

        $this->assertNull($capturedData['header_image']);
    }

    public function testShowMarksTopImages(): void
    {
        file_put_contents($this->albumsRoot . '/album1/normal.jpg', '');
        file_put_contents($this->albumsRoot . '/album1/star--top--.jpg', '');

        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 200, 'height' => 100]);
        $shareKeyRepo  = $this->createMock(ShareKeyRepository::class);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries.php', ['path' => $this->albumsRoot . '/album1']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->show($request);

        // Top images first
        $this->assertTrue($capturedData['images'][0]['is_top']);
        $this->assertFalse($capturedData['images'][1]['is_top']);
    }

    // =========================================================================
    // showPrivate() — private gallery
    // =========================================================================

    public function testShowPrivateWithEmptyKeyRendersError(): void
    {
        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
        $shareKeyRepo  = $this->createMock(ShareKeyRepository::class);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/gallery-private', $this->callback(fn (array $d): bool => $d['error_title'] !== null))
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries-privees.php', ['key' => '']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->showPrivate($request);

        $this->assertSame('Accès refusé', $capturedData['error_title']);
    }

    public function testShowPrivateWithInvalidKeyRendersError(): void
    {
        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
        $shareKeyRepo  = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn(null);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/gallery-private', $this->callback(fn (array $d): bool => $d['error_title'] !== null))
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries-privees.php', ['key' => 'invalid-key']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->showPrivate($request);

        $this->assertStringContainsString('expiré', $capturedData['error_message']);
    }

    public function testShowPrivateWithValidKeyRendersGallery(): void
    {
        file_put_contents($this->privateRoot . '/secret/img.jpg', '');

        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'       => $this->privateRoot . '/secret',
            'identifier' => 'abc123',
        ]);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries-privees.php', ['key' => 'valid-key']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->showPrivate($request);

        $this->assertNull($capturedData['error_title']);
        $this->assertCount(1, $capturedData['images']);
    }

    public function testShowPrivateWithNonExistentPathRendersEmptyGallery(): void
    {
        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'       => '/nonexistent/path',
            'identifier' => 'xyz',
        ]);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries-privees.php', ['key' => 'valid-key']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->showPrivate($request);

        $this->assertSame([], $capturedData['images']);
    }

    public function testShowRedirectsOnInvalidPath(): void
    {
        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
        $shareKeyRepo  = $this->createMock(ShareKeyRepository::class);
        $view          = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $request    = new Request('GET', '/galeries.php', ['path' => '/nonexistent/path/xyz']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);

        $this->expectException(TerminateException::class);
        $controller->show($request);
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeController(
        AlbumService $albumService,
        FileService $fileService,
        ShareKeyRepository $shareKeyRepo,
        ViewRenderer $view,
    ): GalleryController {
        $config = $this->makeConfig();

        return new GalleryController(
            $config,
            $albumService,
            $fileService,
            $shareKeyRepo,
            $this->tmpDir,
            'http://localhost',
            $view,
        );
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_gallery_cfg_' . uniqid();
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
