<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\GalleryController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\Service\FileService;
use ICO\Service\PathService;
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
        $this->assertSame('photo.jpg', $capturedData['images'][0]['filename']);
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

    public function testShowPassesSlideshowIntervalAndDefaultAllowFlagsToView(): void
    {
        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries.php', ['path' => $this->albumsRoot . '/album1']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->show($request);

        $this->assertArrayHasKey('slideshow_interval', $capturedData);
        $this->assertSame(5, $capturedData['slideshow_interval']); // défaut config sans ligne 4
        // Sans infos.txt, les options de partage sont activées par défaut
        $this->assertTrue($capturedData['allow_download']);
        $this->assertTrue($capturedData['allow_share']);
        $this->assertTrue($capturedData['allow_source']);
    }

    public function testShowReadsShareOptionsFromAlbumInfoTxt(): void
    {
        $shareOptionsJson = json_encode(['mode' => 'custom', 'download' => false, 'source' => false, 'share' => false]);
        file_put_contents(
            $this->albumsRoot . '/album1/infos.txt',
            "Mon album\nDescription\n18-\n\n0\n" . $shareOptionsJson
        );

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries.php', ['path' => $this->albumsRoot . '/album1']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->show($request);

        $this->assertFalse($capturedData['allow_download']);
        $this->assertFalse($capturedData['allow_share']);
        $this->assertFalse($capturedData['allow_source']);
    }

    public function testShowUsesGlobalShareOptionsWhenAlbumUsesGlobalMode(): void
    {
        file_put_contents(
            $this->albumsRoot . '/album1/infos.txt',
            "Mon album\nDescription\n18-\n\n0\n" . json_encode(['mode' => 'global'])
        );

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries.php', ['path' => $this->albumsRoot . '/album1']);
        $controller = $this->makeController(
            $albumService,
            $fileService,
            $shareKeyRepo,
            $view,
            $this->makeConfig(['download' => false, 'source' => true, 'share' => false]),
        );
        $controller->show($request);

        $this->assertFalse($capturedData['allow_download']);
        $this->assertFalse($capturedData['allow_share']);
        $this->assertTrue($capturedData['allow_source']);
    }

    public function testShowLinksBreadcrumbHomeToAlbumsPage(): void
    {
        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries.php', ['path' => $this->albumsRoot . '/album1']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->show($request);

        $this->assertSame('Accueil', $capturedData['breadcrumbs'][0]['label']);
        $this->assertSame('albums.php', $capturedData['breadcrumbs'][0]['url']);
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
        $this->assertSame('img.jpg', $capturedData['images'][0]['filename']);
    }

    public function testShowPrivateParentKeyRendersSubfolderNavigation(): void
    {
        $parent = $this->privateRoot . '/parent';
        $child  = $parent . '/child';
        mkdir($child, 0o775, true);
        file_put_contents($this->privateRoot . '/infos.txt', "Albums privés\nDesc\n18-\n");
        file_put_contents($parent . '/infos.txt', "Parent\nDesc\n18-\n");
        file_put_contents($child . '/infos.txt', "Child\nDesc\n18-\n");
        file_put_contents($child . '/photo.jpg', '');

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'       => $parent,
            'identifier' => 'parent-id',
        ]);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $this->assertSame('pages/albums', $tpl);
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries-privees.php', ['key' => 'parent-key']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->showPrivate($request);

        $this->assertSame('Parent', $capturedData['current_album_info']['title']);
        $this->assertSame('Albums privés', $capturedData['breadcrumbs'][0]['label']);
        $this->assertSame('galeries-privees.php?key=parent-key', $capturedData['breadcrumbs'][0]['url']);
        $this->assertSame('Parent', $capturedData['breadcrumbs'][1]['label']);
        $this->assertNull($capturedData['breadcrumbs'][1]['url']);
        $this->assertCount(1, $capturedData['albums']);
        $this->assertSame('Child', $capturedData['albums'][0]['title']);
        $this->assertNotEmpty($capturedData['albums'][0]['images']);
        $this->assertStringContainsString('/images.php?path=liste_albums_prives%2Fparent%2Fchild%2Fphoto.jpg', $capturedData['albums'][0]['images'][0]['url']);
        $this->assertStringContainsString('key=parent-key', $capturedData['albums'][0]['images'][0]['url']);
        $this->assertStringContainsString('galeries-privees.php?key=parent-key', $capturedData['albums'][0]['url']);
        $this->assertStringContainsString('path=liste_albums_prives%2Fparent%2Fchild', $capturedData['albums'][0]['url']);
    }

    public function testShowPrivateParentKeyRendersDescendantGalleryWhenPathIsRequested(): void
    {
        $parent = $this->privateRoot . '/parent-gallery';
        $child  = $parent . '/child';
        mkdir($child, 0o775, true);
        file_put_contents($parent . '/infos.txt', "Parent\nDesc\n18-\n");
        file_put_contents($child . '/infos.txt', "Child\nDesc\n18-\n");
        file_put_contents($child . '/photo.jpg', '');

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'       => $parent,
            'identifier' => 'parent-id',
        ]);
        $shareKeyRepo->method('findValidForPath')->willReturn([
            'path'       => $parent,
            'identifier' => 'parent-id',
        ]);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $this->assertSame('pages/gallery-private', $tpl);
                $capturedData = $data;
            });

        $request = new Request('GET', '/galeries-privees.php', [
            'key'  => 'parent-key',
            'path' => 'liste_albums_prives/parent-gallery/child',
        ]);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->showPrivate($request);

        $this->assertNull($capturedData['error_title']);
        $this->assertCount(1, $capturedData['images']);
        $this->assertStringContainsString('liste_albums_prives%2Fparent-gallery%2Fchild%2Fphoto.jpg', $capturedData['images'][0]['url']);
        $this->assertStringContainsString('key=parent-key', $capturedData['images'][0]['url']);
    }

    public function testShowPrivateWithAdminPathRendersGalleryWithoutShareKey(): void
    {
        file_put_contents($this->privateRoot . '/secret/img.jpg', '');
        $_SESSION['admin_id'] = 1;

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->expects($this->never())->method('findValidByKey');

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries-privees.php', ['path' => 'liste_albums_prives/secret']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->showPrivate($request);

        $this->assertNull($capturedData['error_title']);
        $this->assertCount(1, $capturedData['images']);
        $this->assertStringContainsString('/images.php?path=', $capturedData['images'][0]['url']);
        $this->assertStringContainsString('liste_albums_prives%2Fsecret%2Fimg.jpg', $capturedData['images'][0]['url']);
        $this->assertStringNotContainsString('key=', $capturedData['images'][0]['url']);

        unset($_SESSION['admin_id']);
    }

    public function testShowPrivateWithAdminParentPathRendersSubfolderNavigationWithoutShareKey(): void
    {
        $parent = $this->privateRoot . '/admin-parent';
        $child  = $parent . '/child';
        mkdir($child, 0o775, true);
        file_put_contents($this->privateRoot . '/infos.txt', "Albums privés\nDesc\n18-\n");
        file_put_contents($parent . '/infos.txt', "Admin Parent\nDesc\n18-\n");
        file_put_contents($child . '/infos.txt', "Child\nDesc\n18-\n");
        file_put_contents($child . '/photo.jpg', '');
        $_SESSION['admin_id'] = 1;

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->expects($this->never())->method('findValidByKey');

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $this->assertSame('pages/albums', $tpl);
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries-privees.php', ['path' => 'liste_albums_prives/admin-parent']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->showPrivate($request);

        $this->assertSame('Admin Parent', $capturedData['current_album_info']['title']);
        $this->assertSame('Albums privés', $capturedData['breadcrumbs'][0]['label']);
        $this->assertSame('galeries-privees.php?path=liste_albums_prives', $capturedData['breadcrumbs'][0]['url']);
        $this->assertSame('Admin Parent', $capturedData['breadcrumbs'][1]['label']);
        $this->assertNull($capturedData['breadcrumbs'][1]['url']);
        $this->assertCount(1, $capturedData['albums']);
        $this->assertSame('Child', $capturedData['albums'][0]['title']);
        $this->assertNotEmpty($capturedData['albums'][0]['images']);
        $this->assertStringContainsString('/images.php?path=liste_albums_prives%2Fadmin-parent%2Fchild%2Fphoto.jpg', $capturedData['albums'][0]['images'][0]['url']);
        $this->assertStringNotContainsString('key=', $capturedData['albums'][0]['images'][0]['url']);
        $this->assertStringContainsString(
            'galeries-privees.php?path=liste_albums_prives%2Fadmin-parent%2Fchild',
            $capturedData['albums'][0]['url']
        );
        $this->assertStringNotContainsString('key=', $capturedData['albums'][0]['url']);

        unset($_SESSION['admin_id']);
    }

    public function testShowPrivateWithAdminPathRequiresLoggedInAdmin(): void
    {
        file_put_contents($this->privateRoot . '/secret/img.jpg', '');
        unset($_SESSION['admin_id']);

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->expects($this->never())->method('findValidByKey');

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/gallery-private', $this->callback(fn (array $data): bool => $data['error_title'] === 'Accès refusé'))
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries-privees.php', ['path' => 'liste_albums_prives/secret']);
        $controller = $this->makeController($albumService, $fileService, $shareKeyRepo, $view);
        $controller->showPrivate($request);

        $this->assertSame('Accès refusé', $capturedData['error_title']);
        $this->assertSame([], $capturedData['images']);
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

    public function testShowIncludesVideoItems(): void
    {
        file_put_contents($this->albumsRoot . '/album1/photo.jpg', '');
        file_put_contents($this->albumsRoot . '/album1/clip.mp4', '');

        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);
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

        $this->assertCount(2, $capturedData['images']);
        $types = array_column($capturedData['images'], 'type');
        $this->assertContains('image', $types);
        $this->assertContains('video', $types);
    }

    public function testShowHeaderImageSkipsVideos(): void
    {
        file_put_contents($this->albumsRoot . '/album1/clip.mp4', '');
        file_put_contents($this->albumsRoot . '/album1/photo.jpg', '');

        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);
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

        $this->assertNotNull($capturedData['header_image']);
        $this->assertStringEndsWith('photo.jpg', $capturedData['header_image']);
    }

    public function testShowHeaderImageNullForVideoOnlyGallery(): void
    {
        file_put_contents($this->albumsRoot . '/album1/clip.mp4', '');

        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);
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

        $this->assertNull($capturedData['header_image']);
    }

    public function testShowPrivateIncludesVideoWithProxyUrl(): void
    {
        file_put_contents($this->privateRoot . '/secret/clip.mp4', '');

        $albumService  = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService   = $this->createMock(FileService::class);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'       => $this->privateRoot . '/secret',
            'identifier' => 'abc123',
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

        $this->assertNull($capturedData['error_title']);
        $this->assertCount(1, $capturedData['images']);
        $video = $capturedData['images'][0];
        $this->assertSame('video', $video['type']);
        $this->assertStringContainsString('/videos.php', $video['url']);
        $this->assertNotNull($video['share_url']);
        $this->assertStringContainsString('partage.php', $video['share_url']);
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

    public function testShowPrivatePassesDefaultAllowFlagsWhenNoOptions(): void
    {
        file_put_contents($this->privateRoot . '/secret/img.jpg', '');

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'    => $this->privateRoot . '/secret',
            'options' => null,
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

        $this->assertTrue($capturedData['allow_download']);
        $this->assertTrue($capturedData['allow_share']);
        $this->assertTrue($capturedData['allow_source']);
    }

    public function testShowPrivateUsesAlbumEffectiveShareOptionsWhenKeyHasNoOptions(): void
    {
        file_put_contents($this->privateRoot . '/secret/img.jpg', '');
        file_put_contents(
            $this->privateRoot . '/secret/infos.txt',
            "Secret\nDesc\n18-\n\n0\n" . json_encode(['mode' => 'global'])
        );

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'    => $this->privateRoot . '/secret',
            'options' => null,
        ]);

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $request    = new Request('GET', '/galeries-privees.php', ['key' => 'valid-key']);
        $controller = $this->makeController(
            $albumService,
            $fileService,
            $shareKeyRepo,
            $view,
            $this->makeConfig(['download' => false, 'source' => true, 'share' => false]),
        );
        $controller->showPrivate($request);

        $this->assertFalse($capturedData['allow_download']);
        $this->assertFalse($capturedData['allow_share']);
        $this->assertTrue($capturedData['allow_source']);
    }

    public function testShowPrivatePassesDecodedAllowFlagsFromOptions(): void
    {
        file_put_contents($this->privateRoot . '/secret/img.jpg', '');

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'    => $this->privateRoot . '/secret',
            'options' => json_encode(['download' => false, 'source' => false]),
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

        $this->assertFalse($capturedData['allow_download']);
        $this->assertTrue($capturedData['allow_share']);
        $this->assertFalse($capturedData['allow_source']);
    }

    public function testShowPrivateLetsKeyOptionsOverridePrivateAlbumDefaultsPartially(): void
    {
        file_put_contents($this->privateRoot . '/secret/img.jpg', '');
        file_put_contents(
            $this->privateRoot . '/secret/infos.txt',
            "Secret\nDesc\n18-\n\n0\n" . json_encode([
                'mode'     => 'custom',
                'download' => false,
                'source'   => true,
                'share'    => false,
            ])
        );

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);
        $fileService->method('getSecureImageSize')->willReturn(['width' => 100, 'height' => 100]);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'    => $this->privateRoot . '/secret',
            'options' => json_encode(['source' => false]),
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

        $this->assertFalse($capturedData['allow_download']);
        $this->assertFalse($capturedData['allow_share']);
        $this->assertFalse($capturedData['allow_source']);
    }

    public function testShowPrivateVideoHasShareUrl(): void
    {
        file_put_contents($this->privateRoot . '/secret/clip.mp4', '');

        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileService  = $this->createMock(FileService::class);

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('findValidByKey')->willReturn([
            'path'    => $this->privateRoot . '/secret',
            'options' => null,
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

        $video = $capturedData['images'][0];
        $this->assertSame('video', $video['type']);
        $this->assertSame('clip.mp4', $video['filename']);
        $this->assertNotNull($video['share_url']);
        $this->assertStringContainsString('partage.php', $video['share_url']);
        $this->assertStringContainsString('videos.php', $video['share_url']);
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeController(
        AlbumService $albumService,
        FileService $fileService,
        ShareKeyRepository $shareKeyRepo,
        ViewRenderer $view,
        ?Config $config = null,
    ): GalleryController {
        $config ??= $this->makeConfig();

        return new GalleryController(
            $config,
            $albumService,
            $fileService,
            $shareKeyRepo,
            new PathService($this->tmpDir, 'http://localhost'),
            $this->makeAuthService(),
            $view,
        );
    }

    private function makeAuthService(): AuthService
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturnCallback(static fn (): bool => isset($_SESSION['admin_id']));

        return $auth;
    }

    /**
     * @param array{download: bool, source: bool, share: bool}|null $defaultShareOptions
     */
    private function makeConfig(?array $defaultShareOptions = null): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_gallery_cfg_' . uniqid();
        mkdir($tmp, 0o775, true);
        $configContent = "Test Site\nDesc\n";
        if ($defaultShareOptions !== null) {
            $configContent = "Test Site\nDesc\n\n5\n" . json_encode($defaultShareOptions);
        }

        file_put_contents($tmp . '/config.txt', $configContent);
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
