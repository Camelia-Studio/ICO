<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Controller\ZipController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\PathService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ZipControllerTest extends TestCase
{
    private string $tmpDir;

    private string $albumsRoot;

    private string $privateRoot;

    protected function setUp(): void
    {
        $this->tmpDir      = sys_get_temp_dir() . '/ico_zip_test_' . uniqid();
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
    // Sécurité — chemin invalide
    // =========================================================================

    public function testDownloadReturnsForbiddenForEmptyPath(): void
    {
        $controller = $this->makeController();
        $request    = new Request('GET', '/zip.php', ['path' => '']);

        $this->expectException(TerminateException::class);
        $controller->download($request);
    }

    public function testDownloadReturnsForbiddenForInsecurePath(): void
    {
        $controller = $this->makeController();
        $request    = new Request('GET', '/zip.php', ['path' => '/etc/passwd']);

        $this->expectException(TerminateException::class);
        $controller->download($request);
    }

    // =========================================================================
    // zip_download désactivé → 403
    // =========================================================================

    public function testDownloadReturnsForbiddenWhenZipDownloadDisabled(): void
    {
        $album = $this->albumsRoot . '/locked';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Locked\nDesc\n18-\n\n0");
        file_put_contents($album . '/photo.jpg', 'fake');

        $controller = $this->makeController();
        $relPath    = 'liste_albums/locked';
        $request    = new Request('GET', '/zip.php', ['path' => $relPath]);

        $this->expectException(TerminateException::class);
        $controller->download($request);
    }

    // =========================================================================
    // zip_download activé → ZIP streamé
    // =========================================================================

    public function testDownloadStreamsZipWhenEnabled(): void
    {
        $album = $this->albumsRoot . '/photos';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Photos\nDesc\n18-\n\n1");
        file_put_contents($album . '/a.jpg', 'img_a');
        file_put_contents($album . '/b.png', 'img_b');
        file_put_contents($album . '/readme.txt', 'should be ignored');

        $controller = $this->makeController();
        $relPath    = 'liste_albums/photos';
        $request    = new Request('GET', '/zip.php', ['path' => $relPath]);

        $existingTemporaryZipFiles = glob(sys_get_temp_dir() . '/ico_zip_*') ?: [];
        $temporaryZipFiles         = [];
        $output                    = '';

        ob_start(
            static function (string $buffer) use (
                &$output,
                &$temporaryZipFiles,
                $existingTemporaryZipFiles,
            ): string {
                $output .= $buffer;

                $currentTemporaryZipFiles = glob(sys_get_temp_dir() . '/ico_zip_*') ?: [];
                $temporaryZipFiles        = array_values(array_unique([
                    ...$temporaryZipFiles,
                    ...array_diff($currentTemporaryZipFiles, $existingTemporaryZipFiles),
                ]));

                return '';
            },
            1,
        );

        try {
            $controller->download($request);
        } catch (TerminateException) {
        } finally {
            ob_end_clean();
        }

        $archivePath = $this->tmpDir . '/download.zip';
        file_put_contents($archivePath, $output);

        $zip = new ZipArchive();

        $this->assertSame([], $temporaryZipFiles);
        $this->assertTrue($zip->open($archivePath) === true);
        $this->assertSame(2, $zip->numFiles);
        $this->assertSame('img_a', $zip->getFromName('a.jpg'));
        $this->assertSame('img_b', $zip->getFromName('b.png'));
        $this->assertFalse($zip->locateName('readme.txt'));

        $zip->close();
    }

    // =========================================================================
    // Albums privés
    // =========================================================================

    public function testDownloadReturnsForbiddenForPrivateAlbumWithoutKey(): void
    {
        $album = $this->privateRoot . '/secret';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Secret\nDesc\n18-\n\n1");
        file_put_contents($album . '/photo.jpg', 'fake');

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->expects($this->never())->method('findValidForPath');

        $controller = $this->makeController($shareKeyRepo);
        $request    = new Request('GET', '/zip.php', ['path' => 'liste_albums_prives/secret']);

        $this->expectException(TerminateException::class);
        $controller->download($request);
    }

    public function testDownloadReturnsForbiddenForPrivateAlbumWithInvalidKey(): void
    {
        $album = $this->privateRoot . '/secret';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Secret\nDesc\n18-\n\n1");
        file_put_contents($album . '/photo.jpg', 'fake');

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidForPath')->willReturn(null);

        $controller = $this->makeController($shareKeyRepo);
        $request    = new Request('GET', '/zip.php', ['path' => 'liste_albums_prives/secret', 'key' => 'bad-key']);

        $this->expectException(TerminateException::class);
        $controller->download($request);
    }

    public function testDownloadStreamsZipForPrivateAlbumWithValidKey(): void
    {
        $album = $this->privateRoot . '/secret';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Secret\nDesc\n18-\n\n1");
        file_put_contents($album . '/a.jpg', 'img_a');

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidForPath')->willReturn(['path' => $album]);

        $controller = $this->makeController($shareKeyRepo);
        $request    = new Request('GET', '/zip.php', ['path' => 'liste_albums_prives/secret', 'key' => 'good-key']);

        $output = '';
        ob_start(
            static function (string $buffer) use (&$output): string {
                $output .= $buffer;

                return '';
            },
            1,
        );

        try {
            $controller->download($request);
        } catch (TerminateException) {
        } finally {
            ob_end_clean();
        }

        $archivePath = $this->tmpDir . '/private-download.zip';
        file_put_contents($archivePath, $output);

        $zip = new ZipArchive();

        $this->assertTrue($zip->open($archivePath) === true);
        $this->assertSame('img_a', $zip->getFromName('a.jpg'));

        $zip->close();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeController(?ShareKeyRepository $shareKeyRepo = null): ZipController
    {
        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $pathService  = new PathService($this->tmpDir, 'http://localhost');

        return new ZipController($albumService, $pathService, $shareKeyRepo ?? $this->createMock(ShareKeyRepository::class));
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
