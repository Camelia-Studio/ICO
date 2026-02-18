<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\TreeController;
use ICO\Http\TerminateException;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\LogRepository;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\Service\FileService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class TreeControllerTest extends TestCase
{
    private string $tmpDir;
    private string $albumsRoot;
    private string $privateRoot;

    protected function setUp(): void
    {
        $this->tmpDir      = sys_get_temp_dir() . '/ico_tree_test_' . uniqid();
        $this->albumsRoot  = $this->tmpDir . '/liste_albums';
        $this->privateRoot = $this->tmpDir . '/liste_albums_prives';

        mkdir($this->albumsRoot,  0775, true);
        mkdir($this->privateRoot, 0775, true);

        file_put_contents($this->albumsRoot  . '/infos.txt', "Albums\nDesc\n18-\n");
        file_put_contents($this->privateRoot . '/infos.txt', "Albums privés\nDesc\n18-\n");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = ['admin_id' => 1];
        $_GET     = [];
        $_POST    = [];
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
    // handlePublic — render when logged in with valid path
    // =========================================================================

    public function testHandlePublicRendersViewWhenLoggedIn(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/tree-public', $this->callback(fn (array $d) => isset($d['tree'])));

        $_GET['path'] = $this->albumsRoot;

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePublic — POST create_folder
    // =========================================================================

    public function testHandlePublicPostCreateFolder(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->never())->method('render');

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->method('sanitizeFilename')->willReturn('mon-album');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'create_folder';
        $_POST['path']             = $this->albumsRoot;
        $_POST['new_name']         = 'mon-album';
        $_POST['description']      = 'desc';
        $_POST['more_info_url']    = '';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $viewMock, null, $fileSvc);
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePublic — POST delete_folder
    // =========================================================================

    public function testHandlePublicPostDeleteFolder(): void
    {
        $toDelete = $this->albumsRoot . '/to-delete';
        mkdir($toDelete, 0775, true);
        file_put_contents($toDelete . '/infos.txt', "To delete\n\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->expects($this->once())->method('deleteDirectoryRecursively')->with($toDelete);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'delete_folder';
        $_POST['path']             = $toDelete;

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, $fileSvc);
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePublic — POST edit_folder
    // =========================================================================

    public function testHandlePublicPostEditFolder(): void
    {
        $albumPath = $this->albumsRoot . '/editable';
        mkdir($albumPath, 0775, true);
        file_put_contents($albumPath . '/infos.txt', "Old Title\nOld Desc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'edit_folder';
        $_POST['path']             = $albumPath;
        $_POST['new_name']         = 'New Title';
        $_POST['description']      = 'New Desc';
        $_POST['more_info_url']    = '';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
        $controller->handlePublic();

        $this->assertStringContainsString('New Title', file_get_contents($albumPath . '/infos.txt'));
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
    // handlePrivate — render when logged in with valid private path
    // =========================================================================

    public function testHandlePrivateRendersViewWhenLoggedIn(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/tree-private', $this->callback(fn (array $d) => isset($d['tree'])));

        $_GET['path'] = $this->privateRoot;

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePrivate — POST create_folder
    // =========================================================================

    public function testHandlePrivatePostCreateFolder(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->method('sanitizeFilename')->willReturn('album-secret');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'create_folder';
        $_POST['path']             = $this->privateRoot;
        $_POST['new_name']         = 'album-secret';
        $_POST['description']      = 'desc';
        $_POST['more_info_url']    = '';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, $fileSvc);
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePrivate — POST delete_folder
    // =========================================================================

    public function testHandlePrivatePostDeleteFolder(): void
    {
        $toDelete = $this->privateRoot . '/to-delete';
        mkdir($toDelete, 0775, true);
        file_put_contents($toDelete . '/infos.txt', "To delete\n\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->expects($this->once())->method('deleteDirectoryRecursively')->with($toDelete);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'delete_folder';
        $_POST['path']             = $toDelete;

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, $fileSvc);
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePrivate — POST edit_folder
    // =========================================================================

    public function testHandlePrivatePostEditFolder(): void
    {
        $albumPath = $this->privateRoot . '/editable';
        mkdir($albumPath, 0775, true);
        file_put_contents($albumPath . '/infos.txt', "Old Title\nOld Desc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'edit_folder';
        $_POST['path']             = $albumPath;
        $_POST['new_name']         = 'New Title';
        $_POST['description']      = 'New Desc';
        $_POST['more_info_url']    = '';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class));
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePublic — GET with subfolders (covers mature_content, hasSubfolders, hasImages branches)
    // =========================================================================

    public function testHandlePublicRendersTreeWithSubfoldersAndImages(): void
    {
        // Album with mature content and images (no sub-subfolders → hasSubfolders=false, hasImages=true)
        $albumA = $this->albumsRoot . '/album-mature';
        mkdir($albumA, 0775, true);
        file_put_contents($albumA . '/infos.txt', "Album Mature\nDesc\n18+\n");
        file_put_contents($albumA . '/photo.jpg', '');

        // Album with a subfolder (hasSubfolders=true)
        $albumB    = $this->albumsRoot . '/album-parent';
        $albumBSub = $albumB . '/sub';
        mkdir($albumBSub, 0775, true);
        file_put_contents($albumB    . '/infos.txt', "Album Parent\nDesc\n18-\n");
        file_put_contents($albumBSub . '/infos.txt', "Sub\nDesc\n18-\n");

        // Private album with images (covers generateShareLink branch)
        $privAlbum = $this->privateRoot . '/priv-with-img';
        mkdir($privAlbum, 0775, true);
        file_put_contents($privAlbum . '/infos.txt', "Private Mature\nDesc\n18+\n");
        file_put_contents($privAlbum . '/photo.png', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/tree-public', $this->callback(fn (array $d) => isset($d['tree'])));

        $_GET['path'] = $this->albumsRoot;

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePublic();
    }

    // =========================================================================
    // handlePrivate — GET with subfolders (covers mature_content, hasImages, generateShareLink branches)
    // =========================================================================

    public function testHandlePrivateRendersTreeWithSubfoldersAndImages(): void
    {
        // Private album with mature content + images (covers mature_content=true, hasImages=true → share button)
        $privAlbum = $this->privateRoot . '/priv-mature';
        mkdir($privAlbum, 0775, true);
        file_put_contents($privAlbum . '/infos.txt', "Private Mature\nDesc\n18+\n");
        file_put_contents($privAlbum . '/photo.png', '');

        // Private album with a subfolder (hasSubfolders=true branch)
        $privParent = $this->privateRoot . '/priv-parent';
        $privSub    = $privParent . '/sub';
        mkdir($privSub, 0775, true);
        file_put_contents($privParent . '/infos.txt', "Priv Parent\nDesc\n18-\n");
        file_put_contents($privSub    . '/infos.txt', "Priv Sub\nDesc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/tree-private', $this->callback(fn (array $d) => isset($d['tree'])));

        $_GET['path'] = $this->privateRoot;

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePublic — POST create_folder when folder already exists
    // =========================================================================

    public function testHandlePublicPostCreateFolderWhenAlreadyExists(): void
    {
        $existing = $this->albumsRoot . '/existing';
        mkdir($existing, 0775, true);
        file_put_contents($existing . '/infos.txt', "Existing\nDesc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->method('sanitizeFilename')->willReturn('existing');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'create_folder';
        $_POST['path']             = $this->albumsRoot;
        $_POST['new_name']         = 'existing';
        $_POST['description']      = '';
        $_POST['more_info_url']    = '';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, $fileSvc);
        $controller->handlePublic();

        $this->assertArrayHasKey('error_message', $_SESSION);
    }

    // =========================================================================
    // handlePrivate — POST generate_link

    public function testHandlePrivatePostGenerateLink(): void
    {
        $albumPath = $this->privateRoot . '/shared-album';
        mkdir($albumPath, 0775, true);
        file_put_contents($albumPath . '/infos.txt', "Shared\nDesc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $albumIdentRepo->method('ensure')->willReturn('identifier-abc');

        $shareKeyRepo = $this->createMock(ShareKeyRepository::class);
        $shareKeyRepo->method('create')->willReturn('share-key-xyz');

        $logMock = $this->createMock(LogRepository::class);
        $logMock->expects($this->once())->method('log');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HOST']      = 'localhost';
        $_SERVER['HTTPS']          = '';
        $_POST['action']           = 'generate_link';
        $_POST['path']             = $albumPath;
        $_POST['duration']         = '24';
        $_POST['comment']          = 'test';

        $this->expectException(TerminateException::class);

        $controller = $this->makeControllerWithRepos($authMock, $this->createMock(ViewRenderer::class), $logMock, $albumIdentRepo, $shareKeyRepo);
        $controller->handlePrivate();
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeController(
        AuthService $auth,
        ViewRenderer $view,
        ?LogRepository $logMock = null,
        ?FileService $fileSvc = null,
    ): TreeController {
        $config       = $this->makeConfig();
        $log          = $logMock ?? $this->createMock(LogRepository::class);
        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileSvc      = $fileSvc ?? $this->createMock(FileService::class);

        return new TreeController(
            $config,
            $auth,
            $albumService,
            $fileSvc,
            $log,
            $this->createMock(AlbumIdentifierRepository::class),
            $this->createMock(ShareKeyRepository::class),
            $view,
        );
    }

    private function makeControllerWithRepos(
        AuthService $auth,
        ViewRenderer $view,
        ?LogRepository $logMock = null,
        ?AlbumIdentifierRepository $albumIdentRepo = null,
        ?ShareKeyRepository $shareKeyRepo = null,
        ?FileService $fileSvc = null,
    ): TreeController {
        $config       = $this->makeConfig();
        $log          = $logMock ?? $this->createMock(LogRepository::class);
        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileSvc      = $fileSvc ?? $this->createMock(FileService::class);

        return new TreeController(
            $config,
            $auth,
            $albumService,
            $fileSvc,
            $log,
            $albumIdentRepo ?? $this->createMock(AlbumIdentifierRepository::class),
            $shareKeyRepo   ?? $this->createMock(ShareKeyRepository::class),
            $view,
        );
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_tree_cfg_' . uniqid();
        mkdir($tmp, 0775, true);
        file_put_contents($tmp . '/config.txt', "Test Site\n\n");
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
