<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Controller\VideoController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use PHPUnit\Framework\TestCase;

class VideoControllerTest extends TestCase
{
    private string $tmpDir;

    private string $privateRoot;

    protected function setUp(): void
    {
        $this->tmpDir      = sys_get_temp_dir() . '/ico_videoctrl_test_' . uniqid();
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
    // serve() — extension non-vidéo → 404
    // =========================================================================

    public function testServeReturns404ForNonVideoExtension(): void
    {
        $albumService = $this->makeAlbumService();
        $shareRepo    = $this->createMock(ShareKeyRepository::class);
        $controller   = new VideoController($albumService, $shareRepo, $this->tmpDir);

        $request = $this->createRequest(['path' => $this->privateRoot . '/photo.jpg']);

        $this->expectException(TerminateException::class);
        $controller->serve($request);
    }

    // =========================================================================
    // serve() — chemin hors zone privée → 404
    // =========================================================================

    public function testServeReturns404ForPathOutsidePrivateRoot(): void
    {
        $albumService = $this->makeAlbumService();
        $shareRepo    = $this->createMock(ShareKeyRepository::class);
        $controller   = new VideoController($albumService, $shareRepo, $this->tmpDir);

        $request = $this->createRequest(['path' => '/tmp/outside/evil.mp4']);

        $this->expectException(TerminateException::class);
        $controller->serve($request);
    }

    // =========================================================================
    // serve() — fichier inexistant → 404
    // =========================================================================

    public function testServeReturns404ForRelativePathThatDoesNotExist(): void
    {
        $albumService = $this->makeAlbumService();
        $shareRepo    = $this->createMock(ShareKeyRepository::class);
        $controller   = new VideoController($albumService, $shareRepo, $this->tmpDir);

        $request = $this->createRequest(['path' => 'liste_albums_prives/album/ghost.mp4']);

        $this->expectException(TerminateException::class);
        $controller->serve($request);
    }

    // =========================================================================
    // serve() — chemin valide mais sans clé → 403
    // =========================================================================

    public function testServeReturns403ForValidPathWithNoKey(): void
    {
        $videoPath = $this->privateRoot . '/clip.mp4';
        file_put_contents($videoPath, 'fake-video-data');

        $albumService = $this->makeAlbumService();
        $shareRepo    = $this->createMock(ShareKeyRepository::class);
        $shareRepo->method('findValidByKey')->willReturn(null);
        $controller = new VideoController($albumService, $shareRepo, $this->tmpDir);

        $relativePath = 'liste_albums_prives/album/clip.mp4';
        $request      = $this->createRequest(['path' => $relativePath, 'key' => '']);

        $this->expectException(TerminateException::class);
        $controller->serve($request);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAlbumService(): AlbumService
    {
        return new AlbumService(
            $this->tmpDir . '/liste_albums',
            $this->tmpDir . '/liste_albums_prives',
        );
    }

    /** @param array<string, string> $queryParams */
    private function createRequest(array $queryParams): Request
    {
        return new Request('GET', '/videos.php', $queryParams);
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
