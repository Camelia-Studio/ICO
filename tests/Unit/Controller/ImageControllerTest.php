<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Controller\ImageController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use PHPUnit\Framework\TestCase;

class ImageControllerTest extends TestCase
{
    private string $tmpDir;

    private string $privateRoot;

    protected function setUp(): void
    {
        $this->tmpDir      = sys_get_temp_dir() . '/ico_imgctrl_test_' . uniqid();
        $this->privateRoot = $this->tmpDir . '/liste_albums_prives/album';

        mkdir($this->privateRoot, 0o775, true);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
        $_SESSION = [];
    }

    // =========================================================================
    // serve() — chemin hors zone → 404
    // =========================================================================

    public function testServeReturns404ForPathOutsidePrivateRoot(): void
    {
        $albumService = new AlbumService(
            $this->tmpDir . '/liste_albums',
            $this->tmpDir . '/liste_albums_prives',
        );
        $shareRepo = $this->createMock(ShareKeyRepository::class);

        $controller = new ImageController($albumService, $shareRepo, $this->tmpDir);

        $request = $this->createRequest(['path' => '/tmp/outside/evil.jpg']);

        $this->expectException(TerminateException::class);
        $controller->serve($request);
    }

    // =========================================================================
    // serve() — chemin relatif inexistant → 404
    // =========================================================================

    public function testServeReturns404ForRelativePathThatDoesNotExist(): void
    {
        $albumService = new AlbumService(
            $this->tmpDir . '/liste_albums',
            $this->tmpDir . '/liste_albums_prives',
        );
        $shareRepo = $this->createMock(ShareKeyRepository::class);

        $controller = new ImageController($albumService, $shareRepo, $this->tmpDir);

        $request = $this->createRequest(['path' => 'liste_albums_prives/album/ghost.jpg']);

        $this->expectException(TerminateException::class);
        $controller->serve($request);
    }

    // =========================================================================
    // serve() — chemin relatif valide mais sans clé → 403
    // =========================================================================

    public function testServeReturns403ForRelativePathWithNoKey(): void
    {
        $imagePath = $this->privateRoot . '/photo.jpg';
        file_put_contents($imagePath, 'fake-image-data');

        $albumService = new AlbumService(
            $this->tmpDir . '/liste_albums',
            $this->tmpDir . '/liste_albums_prives',
        );

        $shareRepo = $this->createMock(ShareKeyRepository::class);
        $shareRepo->method('findValidByKey')->willReturn(null);

        $controller = new ImageController($albumService, $shareRepo, $this->tmpDir);

        $relativePath = 'liste_albums_prives/album/photo.jpg';
        $request      = $this->createRequest(['path' => $relativePath, 'key' => '']);

        $this->expectException(TerminateException::class);
        $controller->serve($request);
    }

    public function testServeReturns403WhenShareKeyTargetsSiblingFolder(): void
    {
        $parentPath  = $this->tmpDir . '/liste_albums_prives/parent';
        $siblingPath = $this->tmpDir . '/liste_albums_prives/sibling';
        mkdir($parentPath, 0o775, true);
        mkdir($siblingPath, 0o775, true);

        file_put_contents($siblingPath . '/photo.jpg', 'fake-image-data');

        $albumService = new AlbumService(
            $this->tmpDir . '/liste_albums',
            $this->tmpDir . '/liste_albums_prives',
        );

        $shareRepo = $this->createMock(ShareKeyRepository::class);
        $shareRepo->method('findValidForPath')->willReturn(null);

        $controller = new ImageController($albumService, $shareRepo, $this->tmpDir);

        http_response_code(200);
        ob_start();
        try {
            $controller->serve($this->createRequest([
                'path' => 'liste_albums_prives/sibling/photo.jpg',
                'key'  => 'parent-key',
            ]));
        } catch (TerminateException) {
            $this->assertSame(403, http_response_code());
        } finally {
            ob_end_clean();
            http_response_code(200);
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @param array<string, string> $queryParams */
    private function createRequest(array $queryParams): Request
    {
        return new Request('GET', '/images.php', $queryParams);
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
