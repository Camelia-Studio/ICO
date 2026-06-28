<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\TreeController;
use ICO\Http\TerminateException;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\InfoPageRepository;
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

        mkdir($this->albumsRoot, 0o775, true);
        mkdir($this->privateRoot, 0o775, true);

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
            ->with('pages/tree-public', $this->callback(fn (array $d): bool => isset($d['tree'])));

        $_GET['path'] = 'liste_albums';

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
        $_POST['path']             = 'liste_albums';
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
        mkdir($toDelete, 0o775, true);
        file_put_contents($toDelete . '/infos.txt', "To delete\n\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->expects($this->once())->method('deleteDirectoryRecursively')->with(realpath($toDelete));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'delete_folder';
        $_POST['path']             = 'liste_albums/to-delete';

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
        mkdir($albumPath, 0o775, true);
        file_put_contents($albumPath . '/infos.txt', "Old Title\nOld Desc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'edit_folder';
        $_POST['path']             = 'liste_albums/editable';
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
            ->with('pages/tree-private', $this->callback(fn (array $d): bool => isset($d['tree'])));

        $_GET['path'] = 'liste_albums_prives';

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
        $_POST['path']             = 'liste_albums_prives';
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
        mkdir($toDelete, 0o775, true);
        file_put_contents($toDelete . '/infos.txt', "To delete\n\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->expects($this->once())->method('deleteDirectoryRecursively')->with(realpath($toDelete));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'delete_folder';
        $_POST['path']             = 'liste_albums_prives/to-delete';

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
        mkdir($albumPath, 0o775, true);
        file_put_contents($albumPath . '/infos.txt', "Old Title\nOld Desc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'edit_folder';
        $_POST['path']             = 'liste_albums_prives/editable';
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
        mkdir($albumA, 0o775, true);
        file_put_contents($albumA . '/infos.txt', "Album Mature\nDesc\n18+\n");
        file_put_contents($albumA . '/photo.jpg', '');

        // Album with a subfolder (hasSubfolders=true)
        $albumB    = $this->albumsRoot . '/album-parent';
        $albumBSub = $albumB . '/sub';
        mkdir($albumBSub, 0o775, true);
        file_put_contents($albumB    . '/infos.txt', "Album Parent\nDesc\n18-\n");
        file_put_contents($albumBSub . '/infos.txt', "Sub\nDesc\n18-\n");

        // Private album with images (covers generateShareLink branch)
        $privAlbum = $this->privateRoot . '/priv-with-img';
        mkdir($privAlbum, 0o775, true);
        file_put_contents($privAlbum . '/infos.txt', "Private Mature\nDesc\n18+\n");
        file_put_contents($privAlbum . '/photo.png', '');

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/tree-public', $this->callback(fn (array $d): bool => isset($d['tree'])));

        $_GET['path'] = 'liste_albums';

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
        mkdir($privAlbum, 0o775, true);
        file_put_contents($privAlbum . '/infos.txt', "Private Mature\nDesc\n18+\n");
        file_put_contents($privAlbum . '/photo.png', '');

        // Private album with a subfolder (hasSubfolders=true branch)
        $privParent = $this->privateRoot . '/priv-parent';
        $privSub    = $privParent . '/sub';
        mkdir($privSub, 0o775, true);
        file_put_contents($privParent . '/infos.txt', "Priv Parent\nDesc\n18-\n");
        file_put_contents($privSub    . '/infos.txt', "Priv Sub\nDesc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $viewMock = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/tree-private', $this->callback(fn (array $d): bool => isset($d['tree'])));

        $_GET['path'] = 'liste_albums_prives';

        $controller = $this->makeController($authMock, $viewMock);
        $controller->handlePrivate();
    }

    // =========================================================================
    // handlePublic — POST create_folder when folder already exists
    // =========================================================================

    public function testHandlePublicPostCreateFolderWhenAlreadyExists(): void
    {
        $existing = $this->albumsRoot . '/existing';
        mkdir($existing, 0o775, true);
        file_put_contents($existing . '/infos.txt', "Existing\nDesc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->method('sanitizeFilename')->willReturn('existing');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'create_folder';
        $_POST['path']             = 'liste_albums';
        $_POST['new_name']         = 'existing';
        $_POST['description']      = '';
        $_POST['more_info_url']    = '';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, $fileSvc);
        $controller->handlePublic();

        $this->assertArrayHasKey('error_message', $_SESSION);
    }

    // =========================================================================
    // zip_download — persisté dans infos.txt via edit_folder
    // =========================================================================

    public function testHandlePublicPostEditFolderPersistsZipDownloadEnabled(): void
    {
        $albumPath = $this->albumsRoot . '/zip-album';
        mkdir($albumPath, 0o775, true);
        file_put_contents($albumPath . '/infos.txt', "Titre\nDesc\n18-\n\n0");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'edit_folder';
        $_POST['path']             = 'liste_albums/zip-album';
        $_POST['new_name']         = 'Titre';
        $_POST['description']      = 'Desc';
        $_POST['more_info_url']    = '';
        $_POST['zip_download']     = '1';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePublic();
        } catch (TerminateException) {
        }

        $lines = explode("\n", file_get_contents($albumPath . '/infos.txt'));
        $this->assertSame('1', $lines[4]);
    }

    public function testHandlePublicPostEditFolderPersistsZipDownloadDisabled(): void
    {
        $albumPath = $this->albumsRoot . '/zip-album-off';
        mkdir($albumPath, 0o775, true);
        file_put_contents($albumPath . '/infos.txt', "Titre\nDesc\n18-\n\n1");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'edit_folder';
        $_POST['path']             = 'liste_albums/zip-album-off';
        $_POST['new_name']         = 'Titre';
        $_POST['description']      = 'Desc';
        $_POST['more_info_url']    = '';
        // zip_download absent du POST → désactivé

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePublic();
        } catch (TerminateException) {
        }

        $lines = explode("\n", file_get_contents($albumPath . '/infos.txt'));
        $this->assertSame('0', $lines[4]);
    }

    public function testHandlePublicPostCreateFolderDefaultsZipDownloadToZero(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->method('sanitizeFilename')->willReturn('new-album');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'create_folder';
        $_POST['path']             = 'liste_albums';
        $_POST['new_name']         = 'new-album';
        $_POST['description']      = '';
        $_POST['more_info_url']    = '';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, $fileSvc)->handlePublic();
        } catch (TerminateException) {
        }

        $newPath = $this->albumsRoot . '/new-album/infos.txt';
        $this->assertFileExists($newPath);
        $lines = explode("\n", file_get_contents($newPath));
        $this->assertSame('0', $lines[4]);
    }

    // =========================================================================
    // handlePublic — POST move_folder
    // =========================================================================

    public function testHandlePublicPostMoveFolderSuccess(): void
    {
        $source = $this->albumsRoot . '/source';
        $dest   = $this->albumsRoot . '/dest';
        mkdir($source, 0o775, true);
        mkdir($dest, 0o775, true);
        file_put_contents($source . '/infos.txt', "Source\nDesc\n18-\n");
        file_put_contents($dest   . '/infos.txt', "Destination\nDesc\n18-\n");

        $authMock      = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $albumIdentRepo->expects($this->once())->method('updatePathsAfterMove');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'move_folder';
        $_POST['path']             = 'liste_albums/source';
        $_POST['dest_path']        = 'liste_albums/dest';

        $this->expectException(TerminateException::class);

        $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, null, $albumIdentRepo)
            ->handlePublic();

        $this->assertDirectoryExists($dest . '/source');
        $this->assertDirectoryDoesNotExist($source);
    }

    public function testHandlePublicPostMoveFolderBlockedCircular(): void
    {
        $source = $this->albumsRoot . '/parent';
        $child  = $source . '/child';
        mkdir($child, 0o775, true);
        file_put_contents($source . '/infos.txt', "Parent\nDesc\n18-\n");
        file_put_contents($child  . '/infos.txt', "Child\nDesc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'move_folder';
        $_POST['path']             = 'liste_albums/parent';
        $_POST['dest_path']        = 'liste_albums/parent/child';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePublic();
        } catch (TerminateException) {
        }

        $this->assertArrayHasKey('error_message', $_SESSION);
        $this->assertDirectoryExists($source);
    }

    public function testHandlePublicPostMoveFolderBlockedAlreadyThere(): void
    {
        $folder = $this->albumsRoot . '/myfolder';
        mkdir($folder, 0o775, true);
        file_put_contents($folder . '/infos.txt', "Folder\nDesc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'move_folder';
        $_POST['path']             = 'liste_albums/myfolder';
        $_POST['dest_path']        = 'liste_albums';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePublic();
        } catch (TerminateException) {
        }

        $this->assertArrayHasKey('error_message', $_SESSION);
        $this->assertDirectoryExists($folder);
    }

    public function testHandlePublicPostMoveFolderBlockedCollision(): void
    {
        $source      = $this->albumsRoot . '/source';
        $dest        = $this->albumsRoot . '/dest';
        $destConflict = $dest . '/source';
        mkdir($source, 0o775, true);
        mkdir($destConflict, 0o775, true);
        file_put_contents($source       . '/infos.txt', "Source\nDesc\n18-\n");
        file_put_contents($dest         . '/infos.txt', "Dest\nDesc\n18-\n");
        file_put_contents($destConflict . '/infos.txt', "Conflict\nDesc\n18-\n");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'move_folder';
        $_POST['path']             = 'liste_albums/source';
        $_POST['dest_path']        = 'liste_albums/dest';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePublic();
        } catch (TerminateException) {
        }

        $this->assertArrayHasKey('error_message', $_SESSION);
        $this->assertDirectoryExists($source);
    }

    // =========================================================================
    // handlePrivate — POST move_folder
    // =========================================================================

    public function testHandlePrivatePostMoveFolderSuccess(): void
    {
        $source = $this->privateRoot . '/source';
        $dest   = $this->privateRoot . '/dest';
        mkdir($source, 0o775, true);
        mkdir($dest, 0o775, true);
        file_put_contents($source . '/infos.txt', "Source\nDesc\n18-\n");
        file_put_contents($dest   . '/infos.txt', "Destination\nDesc\n18-\n");

        $authMock      = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $albumIdentRepo = $this->createMock(AlbumIdentifierRepository::class);
        $albumIdentRepo->expects($this->once())->method('updatePathsAfterMove');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'move_folder';
        $_POST['path']             = 'liste_albums_prives/source';
        $_POST['dest_path']        = 'liste_albums_prives/dest';

        $this->expectException(TerminateException::class);

        $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, null, $albumIdentRepo)
            ->handlePrivate();

        $this->assertDirectoryExists($dest . '/source');
        $this->assertDirectoryDoesNotExist($source);
    }

    // =========================================================================
    // handlePrivate — POST generate_link

    public function testHandlePrivatePostGenerateLink(): void
    {
        $albumPath = $this->privateRoot . '/shared-album';
        mkdir($albumPath, 0o775, true);
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
        $_POST['path']             = 'liste_albums_prives/shared-album';
        $_POST['duration']         = '24';
        $_POST['comment']          = 'test';

        $this->expectException(TerminateException::class);

        $controller = $this->makeControllerWithRepos($authMock, $this->createMock(ViewRenderer::class), $logMock, $albumIdentRepo, $shareKeyRepo);
        $controller->handlePrivate();
    }

    // =========================================================================
    // POST public create_folder — écrit share_options en ligne 5
    // =========================================================================

    public function testHandlePublicPostCreateFolderWritesShareOptions(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->method('sanitizeFilename')->willReturn('mon-album');

        $_SERVER['REQUEST_METHOD']   = 'POST';
        $_POST['action']             = 'create_folder';
        $_POST['path']               = 'liste_albums';
        $_POST['new_name']           = 'mon-album';
        $_POST['description']        = '';
        $_POST['more_info_url']      = '';
        $_POST['share_options_mode'] = 'custom';
        $_POST['opt_share_download'] = 'on';
        // opt_share_source non envoyé (décoché)
        $_POST['opt_share_share']    = 'on';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, $fileSvc)
                ->handlePublic();
        } catch (TerminateException) {
        }

        $infosPath = $this->albumsRoot . '/mon-album/infos.txt';
        $this->assertFileExists($infosPath);
        $lines = explode("\n", (string) file_get_contents($infosPath));
        $opts  = json_decode($lines[5] ?? '{}', true);
        $this->assertIsArray($opts);
        $this->assertSame('custom', $opts['mode']);
        $this->assertTrue($opts['download']);
        $this->assertFalse($opts['source']);
        $this->assertTrue($opts['share']);
    }

    // =========================================================================
    // POST public edit_folder — écrit share_options en ligne 5
    // =========================================================================

    public function testHandlePublicPostEditFolderWritesShareOptions(): void
    {
        $existingAlbum = $this->albumsRoot . '/mon-album';
        mkdir($existingAlbum, 0o775, true);
        file_put_contents($existingAlbum . '/infos.txt', "Mon Album\nDesc\n18-\n\n0");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_POST['action']            = 'edit_folder';
        $_POST['path']              = 'liste_albums/mon-album';
        $_POST['new_name']          = 'Mon Album';
        $_POST['description']       = 'Desc';
        $_POST['more_info_url']     = '';
        $_POST['share_options_mode'] = 'custom';
        // opt_share_download non envoyé (décoché)
        $_POST['opt_share_source']  = 'on';
        // opt_share_share non envoyé (décoché)

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePublic();
        } catch (TerminateException) {
        }

        $lines = explode("\n", (string) file_get_contents($existingAlbum . '/infos.txt'));
        $opts  = json_decode($lines[5] ?? '{}', true);
        $this->assertIsArray($opts);
        $this->assertSame('custom', $opts['mode']);
        $this->assertFalse($opts['download']);
        $this->assertTrue($opts['source']);
        $this->assertFalse($opts['share']);
    }

    // =========================================================================
    // POST privé create_folder — écrit share_options en ligne 5
    // =========================================================================

    public function testHandlePrivatePostCreateFolderWritesShareOptions(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $fileSvc = $this->createMock(FileService::class);
        $fileSvc->method('sanitizeFilename')->willReturn('secret-album');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['action']           = 'create_folder';
        $_POST['path']             = 'liste_albums_prives';
        $_POST['new_name']         = 'secret-album';
        $_POST['description']      = '';
        $_POST['more_info_url']    = '';
        // Tous les opts share décochés (non envoyés)

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class), null, $fileSvc)
                ->handlePrivate();
        } catch (TerminateException) {
        }

        $infosPath = $this->privateRoot . '/secret-album/infos.txt';
        $this->assertFileExists($infosPath);
        $lines = explode("\n", (string) file_get_contents($infosPath));
        $opts  = json_decode($lines[5] ?? '{}', true);
        $this->assertIsArray($opts);
        $this->assertSame(['mode' => 'global'], $opts);
    }

    // =========================================================================
    // POST privé edit_folder — écrit share_options en ligne 5
    // =========================================================================

    public function testHandlePrivatePostEditFolderWritesShareOptions(): void
    {
        $existingAlbum = $this->privateRoot . '/secret-album';
        mkdir($existingAlbum, 0o775, true);
        file_put_contents($existingAlbum . '/infos.txt', "Secret\nDesc\n18-\n\n0");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD']   = 'POST';
        $_POST['action']             = 'edit_folder';
        $_POST['path']               = 'liste_albums_prives/secret-album';
        $_POST['new_name']           = 'Secret';
        $_POST['description']        = 'Desc';
        $_POST['more_info_url']      = '';
        $_POST['share_options_mode'] = 'custom';
        $_POST['opt_share_download'] = 'on';
        $_POST['opt_share_source']   = 'on';
        $_POST['opt_share_share']    = 'on';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePrivate();
        } catch (TerminateException) {
        }

        $lines = explode("\n", (string) file_get_contents($existingAlbum . '/infos.txt'));
        $opts  = json_decode($lines[5] ?? '{}', true);
        $this->assertIsArray($opts);
        $this->assertSame('custom', $opts['mode']);
        $this->assertTrue($opts['download']);
        $this->assertTrue($opts['source']);
        $this->assertTrue($opts['share']);
    }

    public function testHandlePublicRendersParentFolderWithStoredShareOptions(): void
    {
        $parent = $this->albumsRoot . '/parent';
        $child  = $parent . '/child';
        mkdir($child, 0o775, true);
        file_put_contents(
            $parent . '/infos.txt',
            "Parent\nDesc\n18-\n\n0\n" . json_encode(['download' => false, 'source' => true, 'share' => false])
        );
        file_put_contents($child . '/infos.txt', "Child\nDesc\n18-\n\n0");

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $capturedData = null;
        $viewMock     = $this->createMock(ViewRenderer::class);
        $viewMock->expects($this->once())->method('render')
            ->with('pages/tree-public', $this->callback(function (array $data) use (&$capturedData): bool {
                $capturedData = $data;

                return true;
            }));

        $_GET['path'] = 'liste_albums';

        $this->makeController($authMock, $viewMock)->handlePublic();

        $this->assertIsArray($capturedData);
        $this->assertStringContainsString(
            "editFolder('liste_albums/parent', 'Parent', 'Desc', false, '', false, false, false, true, false, 'custom', true)",
            (string) $capturedData['tree']
        );
        $this->assertStringContainsString(
            "createSubfolder('liste_albums/parent', 'custom', false, true, false)",
            (string) $capturedData['tree']
        );
    }

    public function testHandlePublicPostEditFolderAppliesShareOptionsToSubfoldersWhenRequested(): void
    {
        $parent     = $this->albumsRoot . '/parent';
        $child      = $parent . '/child';
        $grandChild = $child . '/grand-child';
        mkdir($grandChild, 0o775, true);
        file_put_contents($parent . '/infos.txt', "Parent\nDesc\n18-\n\n0\n" . json_encode(['download' => true, 'source' => true, 'share' => true]));
        file_put_contents($child . '/infos.txt', "Child\nChild desc\n18-\n\n0\n" . json_encode(['download' => true, 'source' => false, 'share' => true]));
        file_put_contents($grandChild . '/infos.txt', "Grand Child\nGrand desc\n18+\n\n1\n" . json_encode(['download' => true, 'source' => true, 'share' => true]));

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD']                   = 'POST';
        $_POST['action']                             = 'edit_folder';
        $_POST['path']                               = 'liste_albums/parent';
        $_POST['new_name']                           = 'Parent';
        $_POST['description']                        = 'Desc';
        $_POST['more_info_url']                      = '';
        $_POST['share_options_mode']                 = 'custom';
        $_POST['opt_share_source']                   = 'on';
        $_POST['apply_share_options_to_subfolders']  = 'on';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePublic();
        } catch (TerminateException) {
        }

        $childLines = explode("\n", (string) file_get_contents($child . '/infos.txt'));
        $childOpts  = json_decode($childLines[5] ?? '{}', true);
        $grandLines = explode("\n", (string) file_get_contents($grandChild . '/infos.txt'));
        $grandOpts  = json_decode($grandLines[5] ?? '{}', true);

        $this->assertSame('Child', $childLines[0]);
        $this->assertSame(['mode' => 'custom', 'download' => false, 'source' => true, 'share' => false], $childOpts);
        $this->assertSame('Grand Child', $grandLines[0]);
        $this->assertSame('1', $grandLines[4]);
        $this->assertSame(['mode' => 'custom', 'download' => false, 'source' => true, 'share' => false], $grandOpts);
    }

    public function testHandlePublicPostEditFolderAppliesGlobalShareModeToSubfoldersWhenRequested(): void
    {
        $parent = $this->albumsRoot . '/global-parent';
        $child  = $parent . '/child';
        mkdir($child, 0o775, true);
        file_put_contents($parent . '/infos.txt', "Parent\nDesc\n18-\n\n0\n" . json_encode(['mode' => 'custom', 'download' => false, 'source' => false, 'share' => false]));
        file_put_contents($child . '/infos.txt', "Child\nChild desc\n18+\n\n1\n" . json_encode(['mode' => 'custom', 'download' => true, 'source' => false, 'share' => true]));

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD']                  = 'POST';
        $_POST['action']                            = 'edit_folder';
        $_POST['path']                              = 'liste_albums/global-parent';
        $_POST['new_name']                          = 'Parent';
        $_POST['description']                       = 'Desc';
        $_POST['more_info_url']                     = '';
        $_POST['share_options_mode']                = 'global';
        $_POST['apply_share_options_to_subfolders'] = 'on';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePublic();
        } catch (TerminateException) {
        }

        $childLines = explode("\n", (string) file_get_contents($child . '/infos.txt'));
        $childOpts  = json_decode($childLines[5] ?? '{}', true);

        $this->assertSame('Child', $childLines[0]);
        $this->assertSame('18+', $childLines[2]);
        $this->assertSame('1', $childLines[4]);
        $this->assertSame(['mode' => 'global'], $childOpts);
    }

    public function testHandlePrivatePostEditFolderAppliesShareOptionsToSubfoldersWhenRequested(): void
    {
        $parent = $this->privateRoot . '/parent';
        $child  = $parent . '/child';
        mkdir($child, 0o775, true);
        file_put_contents($parent . '/infos.txt', "Parent\nDesc\n18-\n\n0\n" . json_encode(['download' => true, 'source' => true, 'share' => true]));
        file_put_contents($child . '/infos.txt', "Child\nChild desc\n18-\n\n0\n" . json_encode(['download' => false, 'source' => false, 'share' => false]));

        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD']                  = 'POST';
        $_POST['action']                            = 'edit_folder';
        $_POST['path']                              = 'liste_albums_prives/parent';
        $_POST['new_name']                          = 'Parent';
        $_POST['description']                       = 'Desc';
        $_POST['more_info_url']                     = '';
        $_POST['share_options_mode']                = 'custom';
        $_POST['opt_share_download']                = 'on';
        $_POST['opt_share_share']                   = 'on';
        $_POST['apply_share_options_to_subfolders'] = 'on';

        try {
            $this->makeController($authMock, $this->createMock(ViewRenderer::class))->handlePrivate();
        } catch (TerminateException) {
        }

        $childLines = explode("\n", (string) file_get_contents($child . '/infos.txt'));
        $childOpts  = json_decode($childLines[5] ?? '{}', true);

        $this->assertSame('Child', $childLines[0]);
        $this->assertSame(['mode' => 'custom', 'download' => true, 'source' => false, 'share' => true], $childOpts);
    }

    // =========================================================================
    // Factory
    // =========================================================================

    private function makeController(
        AuthService $auth,
        ViewRenderer $view,
        ?LogRepository $logMock = null,
        ?FileService $fileSvc = null,
        ?AlbumIdentifierRepository $albumIdentRepo = null,
    ): TreeController {
        $config       = $this->makeConfig();
        $log          = $logMock ?? $this->createMock(LogRepository::class);
        $albumService = new AlbumService($this->albumsRoot, $this->privateRoot);
        $fileSvc ??= $this->createMock(FileService::class);

        return new TreeController(
            $config,
            $this->tmpDir,
            $auth,
            $albumService,
            $fileSvc,
            $log,
            $albumIdentRepo ?? $this->createMock(AlbumIdentifierRepository::class),
            $this->createMock(ShareKeyRepository::class),
            $view,
            $this->createMock(InfoPageRepository::class),
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
        $fileSvc ??= $this->createMock(FileService::class);

        return new TreeController(
            $config,
            $this->tmpDir,
            $auth,
            $albumService,
            $fileSvc,
            $log,
            $albumIdentRepo ?? $this->createMock(AlbumIdentifierRepository::class),
            $shareKeyRepo   ?? $this->createMock(ShareKeyRepository::class),
            $view,
            $this->createMock(InfoPageRepository::class),
        );
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_tree_cfg_' . uniqid();
        mkdir($tmp, 0o775, true);
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
