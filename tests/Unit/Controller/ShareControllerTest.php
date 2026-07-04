<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\ShareController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class ShareControllerTest extends TestCase
{
    private string $tmpDir;

    private string $albumsRoot;

    private string $privateRoot;

    private AlbumService $albumService;

    protected function setUp(): void
    {
        $this->tmpDir      = sys_get_temp_dir() . '/ico_share_ctrl_test_' . uniqid();
        $this->albumsRoot  = $this->tmpDir . '/liste_albums';
        $this->privateRoot = $this->tmpDir . '/liste_albums_prives';
        mkdir($this->albumsRoot . '/mon-album', 0o775, true);
        mkdir($this->privateRoot . '/secret-album', 0o775, true);

        $this->albumService = new AlbumService($this->albumsRoot, $this->privateRoot);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION               = [];
        $_SERVER['HTTPS']       = 'off';
        $_SERVER['HTTP_HOST']   = 'localhost';
        $_SERVER['REQUEST_URI'] = '/partage.php';
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
        $_SESSION = [];
    }

    // =========================================================================
    // Images publiques — options album appliquées
    // =========================================================================

    public function testShowUsesAlbumShareOptionsForPublicImageDownloadFalse(): void
    {
        file_put_contents(
            $this->albumsRoot . '/mon-album/infos.txt',
            "Album\nDesc\n18-\n\n0\n" . json_encode(['mode' => 'custom', 'download' => false, 'source' => true, 'share' => true])
        );

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/share', $this->callback(
                fn (array $d): bool =>
                    $d['allow_download'] === false &&
                    $d['allow_source']   === true  &&
                    $d['allow_share']    === true
            ));

        $this->makeController(view: $view)->show(
            new Request('GET', '/partage.php', query: ['image' => 'liste_albums/mon-album/photo.jpg'])
        );
    }

    public function testShowDefaultsAllTrueForPublicImageWithoutShareOptionsInInfosTxt(): void
    {
        // Pas de ligne 5 dans infos.txt
        file_put_contents($this->albumsRoot . '/mon-album/infos.txt', "Album\nDesc\n18-\n\n0");

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/share', $this->callback(
                fn (array $d): bool =>
                    $d['allow_download'] === true &&
                    $d['allow_source']   === true &&
                    $d['allow_share']    === true
            ));

        $this->makeController(view: $view)->show(
            new Request('GET', '/partage.php', query: ['image' => 'liste_albums/mon-album/photo.jpg'])
        );
    }

    public function testShowUsesGlobalShareOptionsForPublicImageInGlobalMode(): void
    {
        file_put_contents(
            $this->albumsRoot . '/mon-album/infos.txt',
            "Album\nDesc\n18-\n\n0\n" . json_encode(['mode' => 'global'])
        );

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/share', $this->callback(
                fn (array $d): bool =>
                    $d['allow_download'] === false &&
                    $d['allow_source']   === true &&
                    $d['allow_share']    === false
            ));

        $this->makeController(
            view: $view,
            config: $this->makeConfig(['download' => false, 'source' => true, 'share' => false]),
        )->show(
            new Request('GET', '/partage.php', query: ['image' => 'liste_albums/mon-album/photo.jpg'])
        );
    }

    // =========================================================================
    // Images privées — share_keys.options prioritaire (comportement inchangé)
    // =========================================================================

    public function testShowUsesShareKeyOptionsForPrivateImageIgnoringAlbumDefaults(): void
    {
        // L'album privé a download=true par défaut mais le lien a download=false
        file_put_contents(
            $this->privateRoot . '/secret-album/infos.txt',
            "Secret\nDesc\n18-\n\n0\n" . json_encode(['download' => true, 'source' => true, 'share' => true])
        );

        $shareKey = $this->createMock(ShareKeyRepository::class);
        $shareKey->method('findValidForPath')->willReturn([
            'path'       => $this->privateRoot . '/secret-album',
            'identifier' => 'uuid-abc',
            'options'    => json_encode(['download' => false, 'source' => false, 'share' => true]),
        ]);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/share', $this->callback(
                fn (array $d): bool =>
                    $d['allow_download'] === false &&
                    $d['allow_source']   === false &&
                    $d['allow_share']    === true
            ));

        // Construire une URL privée simulée
        $imageUrl = 'images.php?path='
            . rawurlencode($this->privateRoot . '/secret-album/photo.jpg')
            . '&key=validkey';

        $_SESSION['admin_id'] = 1;

        $this->makeController(shareKeyRepo: $shareKey, view: $view)->show(
            new Request('GET', '/partage.php', query: ['image' => $imageUrl])
        );
    }

    public function testShowRedirectsWhenPrivateImageIsOutsideSharedFolder(): void
    {
        $parent  = $this->privateRoot . '/parent';
        $sibling = $this->privateRoot . '/sibling';
        mkdir($parent, 0o775, true);
        mkdir($sibling, 0o775, true);

        $shareKey = $this->createMock(ShareKeyRepository::class);
        $shareKey->expects($this->once())
            ->method('findValidForPath')
            ->with('parent-key', $sibling . '/photo.jpg')
            ->willReturn(null);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->never())->method('render');

        $imageUrl = 'images.php?path='
            . rawurlencode($sibling . '/photo.jpg')
            . '&key=parent-key';

        $this->expectException(TerminateException::class);

        $this->makeController(shareKeyRepo: $shareKey, view: $view)->show(
            new Request('GET', '/partage.php', query: ['image' => $imageUrl])
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeController(
        ?ShareKeyRepository $shareKeyRepo = null,
        ?ViewRenderer       $view         = null,
        ?Config             $config       = null,
    ): ShareController {
        $config ??= $this->makeConfig();

        return new ShareController(
            $config,
            $shareKeyRepo ?? $this->createMock(ShareKeyRepository::class),
            $view ?? $this->createMock(ViewRenderer::class),
            $this->albumService,
            $this->tmpDir,
        );
    }

    /**
     * @param array{download: bool, source: bool, share: bool}|null $defaultShareOptions
     */
    private function makeConfig(?array $defaultShareOptions = null): Config
    {
        $cfgDir = sys_get_temp_dir() . '/ico_share_cfg_' . uniqid();
        mkdir($cfgDir, 0o775, true);
        $configContent = "Test\n\n";
        if ($defaultShareOptions !== null) {
            $configContent = "Test\n\n\n5\n" . json_encode($defaultShareOptions);
        }

        file_put_contents($cfgDir . '/config.txt', $configContent);
        file_put_contents($cfgDir . '/version.txt', '1.0.0');
        $config = Config::fromFile($cfgDir . '/config.txt', $cfgDir . '/version.txt');
        unlink($cfgDir . '/config.txt');
        unlink($cfgDir . '/version.txt');
        rmdir($cfgDir);

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
