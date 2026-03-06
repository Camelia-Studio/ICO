<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\AlbumController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Service\AlbumService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class AlbumControllerTest extends TestCase
{
    private string $tmpDir;

    private string $albumsRoot;

    protected function setUp(): void
    {
        $this->tmpDir     = sys_get_temp_dir() . '/ico_album_test_' . uniqid();
        $this->albumsRoot = $this->tmpDir . '/liste_albums';
        mkdir($this->albumsRoot, 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
    }

    public function testIndexRendersEmptyAlbums(): void
    {
        $config       = $this->makeConfig();
        $albumService = new AlbumService($this->albumsRoot, $this->tmpDir . '/priv');
        $view         = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/albums', $this->callback(
                fn (array $d): bool =>
                isset($d['albums'], $d['current_album_info'], $d['site_title'])
                && is_array($d['albums'])
            ));

        $request    = new Request('GET', '/albums.php', ['path' => $this->albumsRoot]);
        $controller = new AlbumController($config, $this->tmpDir, 'http://localhost', $albumService, $view);
        $controller->index($request);
    }

    public function testIndexRendersSubAlbums(): void
    {
        $sub1 = $this->albumsRoot . '/album1';
        $sub2 = $this->albumsRoot . '/album2';
        mkdir($sub1, 0o775, true);
        mkdir($sub2, 0o775, true);
        file_put_contents($sub1 . '/infos.txt', "Album 1\nDescription 1\n18-");
        file_put_contents($sub2 . '/infos.txt', "Album 2\nDescription 2\n18-");

        $config       = $this->makeConfig();
        $albumService = new AlbumService($this->albumsRoot, $this->tmpDir . '/priv');

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/albums.php', ['path' => $this->albumsRoot]);
        $controller = new AlbumController($config, $this->tmpDir, 'http://localhost', $albumService, $view);
        $controller->index($request);

        $this->assertCount(2, $capturedData['albums']);
    }

    public function testIndexAlbumsAreSortedAlphabetically(): void
    {
        foreach (['zebra', 'alpha', 'mango'] as $name) {
            $dir = $this->albumsRoot . ('/' . $name);
            mkdir($dir, 0o775, true);
            file_put_contents($dir . '/infos.txt', ucfirst($name) . "\n\n18-");
        }

        $config       = $this->makeConfig();
        $albumService = new AlbumService($this->albumsRoot, $this->tmpDir . '/priv');

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/albums.php', ['path' => $this->albumsRoot]);
        $controller = new AlbumController($config, $this->tmpDir, 'http://localhost', $albumService, $view);
        $controller->index($request);

        $titles = array_column($capturedData['albums'], 'title');
        $sorted = $titles;
        usort($sorted, strcasecmp(...));
        $this->assertSame($sorted, $titles);
    }

    public function testImagesAreUrls(): void
    {
        $sub = $this->albumsRoot . '/album_img';
        mkdir($sub, 0o775, true);
        file_put_contents($sub . '/infos.txt', "Album Img\n\n18-");
        file_put_contents($sub . '/photo.jpg', '');

        $config       = $this->makeConfig();
        $albumService = new AlbumService($this->albumsRoot, $this->tmpDir . '/priv');

        $capturedData = null;
        $view         = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/albums.php', ['path' => $this->albumsRoot]);
        $controller = new AlbumController($config, $this->tmpDir, 'http://localhost', $albumService, $view);
        $controller->index($request);

        $album = $capturedData['albums'][0];
        $this->assertNotEmpty($album['images']);
        foreach ($album['images'] as $image) {
            $this->assertIsArray($image);
            $this->assertStringStartsWith('http', $image['url']);
            $this->assertStringNotContainsString($this->tmpDir, $image['url']);
        }
    }

    public function testIndexRedirectsOnInvalidPath(): void
    {
        $config       = $this->makeConfig();
        $albumService = new AlbumService($this->albumsRoot, $this->tmpDir . '/priv');
        $view         = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $request    = new Request('GET', '/albums.php', ['path' => '/nonexistent/invalid/path']);
        $controller = new AlbumController($config, $this->tmpDir, 'http://localhost', $albumService, $view);

        $this->expectException(TerminateException::class);
        $controller->index($request);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_album_cfg_' . uniqid();
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
