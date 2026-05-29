<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Controller\SettingsController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase
{
    private string $tmpDir;

    private string $configFile;

    protected function setUp(): void
    {
        $this->tmpDir     = sys_get_temp_dir() . '/ico_settings_test_' . uniqid();
        mkdir($this->tmpDir, 0o775, true);
        $this->configFile = $this->tmpDir . '/config.txt';

        file_put_contents($this->configFile, "ICO\nDescription\nbase-path");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/personnalisation.php';
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
    // GET — redirect when not logged in
    // =========================================================================

    public function testIndexRedirectsWhenNotLoggedIn(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $this->expectException(TerminateException::class);

        $controller = $this->makeController(auth: $auth);
        $controller->index(new Request('GET', '/personnalisation.php'));
    }

    // =========================================================================
    // GET — renders form with current config values
    // =========================================================================

    public function testIndexRendersFormWithCurrentConfig(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/settings', $this->callback(
                fn (array $d): bool =>
                $d['site_title'] === 'ICO'
                && $d['site_description'] === 'Description'
                && $d['project_path'] === 'base-path'
            ));

        $controller = $this->makeController(auth: $auth, view: $view);
        $controller->index(new Request('GET', '/personnalisation.php'));
    }

    // =========================================================================
    // GET — renders form with slideshow_interval from config
    // =========================================================================

    public function testIndexRendersFormWithSlideshowInterval(): void
    {
        file_put_contents($this->configFile, "ICO\nDescription\nbase-path\n8");

        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/settings', $this->callback(
                fn (array $d): bool => $d['slideshow_interval'] === 8
            ));

        $controller = $this->makeController(auth: $auth, view: $view);
        $controller->index(new Request('GET', '/personnalisation.php'));
    }

    // =========================================================================
    // POST — saves slideshow_interval to line 4
    // =========================================================================

    public function testPostSavesSlideshowIntervalToLine4(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $log = $this->createMock(LogRepository::class);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_FILES['favicon'] = ['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => ''];

        try {
            $controller = $this->makeController(auth: $auth, log: $log);
            $controller->index(new Request('POST', '/personnalisation.php', body: [
                'site_title'         => 'Titre',
                'site_description'   => 'Desc',
                'project_path'       => '',
                'slideshow_interval' => '12',
            ]));
        } catch (TerminateException) {
        }

        $saved = explode("\n", (string) file_get_contents($this->configFile));
        $this->assertSame('12', $saved[3]);
    }

    public function testPostDefaultsSlideshowIntervalTo5WhenInvalid(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $log = $this->createMock(LogRepository::class);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_FILES['favicon'] = ['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => ''];

        try {
            $controller = $this->makeController(auth: $auth, log: $log);
            $controller->index(new Request('POST', '/personnalisation.php', body: [
                'site_title'         => 'Titre',
                'site_description'   => '',
                'project_path'       => '',
                'slideshow_interval' => '0',
            ]));
        } catch (TerminateException) {
        }

        $saved = explode("\n", (string) file_get_contents($this->configFile));
        $this->assertSame('5', $saved[3]);
    }

    // =========================================================================
    // POST — saves config, no favicon
    // =========================================================================

    public function testPostSavesConfigWithoutFavicon(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $log = $this->createMock(LogRepository::class);
        $log->expects($this->once())->method('log')
            ->with(1, 'UPDATE_SETTINGS', $this->anything());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_FILES['favicon'] = ['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => ''];

        $this->expectException(TerminateException::class);

        $controller = $this->makeController(auth: $auth, log: $log);
        $controller->index(new Request('POST', '/personnalisation.php', body: [
            'site_title'       => 'Nouveau Titre',
            'site_description' => 'Nouvelle description',
            'project_path'     => 'sous-chemin',
        ]));

        $saved = explode("\n", (string) file_get_contents($this->configFile));
        $this->assertSame('Nouveau Titre', $saved[0]);
        $this->assertSame('Nouvelle description', $saved[1]);
        $this->assertSame('sous-chemin', $saved[2]);
    }

    // =========================================================================
    // POST — rejects empty site_title
    // =========================================================================

    public function testPostRejectsEmptySiteTitle(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/settings', $this->callback(
                fn (array $d): bool => $d['error_message'] !== ''
            ));

        $controller = $this->makeController(auth: $auth, view: $view);
        $controller->index(new Request('POST', '/personnalisation.php', body: [
            'site_title'       => '',
            'site_description' => '',
            'project_path'     => '',
        ]));
    }

    // =========================================================================
    // POST — rejects favicon with wrong MIME type
    // =========================================================================

    public function testPostRejectsFaviconWithWrongMimeType(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $tmpFavicon = $this->tmpDir . '/fake.png';
        file_put_contents($tmpFavicon, 'this is not a png');

        $_FILES['favicon'] = [
            'name'     => 'fake.png',
            'type'     => 'image/png',
            'tmp_name' => $tmpFavicon,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 17,
        ];

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/settings', $this->callback(
                fn (array $d): bool => str_contains((string) $d['error_message'], 'PNG')
            ));

        $controller = $this->makeController(auth: $auth, view: $view);
        $controller->index(new Request('POST', '/personnalisation.php', body: [
            'site_title'       => 'ICO',
            'site_description' => '',
            'project_path'     => '',
        ]));
    }

    // =========================================================================
    // POST — rejects favicon exceeding size limit
    // =========================================================================

    public function testPostRejectsFaviconTooLarge(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_FILES['favicon'] = [
            'name'     => 'big.png',
            'type'     => 'image/png',
            'tmp_name' => '/tmp/big.png',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 2_000_000,
        ];

        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/settings', $this->callback(
                fn (array $d): bool => str_contains((string) $d['error_message'], '1 Mo')
            ));

        $controller = $this->makeController(auth: $auth, view: $view);
        $controller->index(new Request('POST', '/personnalisation.php', body: [
            'site_title'       => 'ICO',
            'site_description' => '',
            'project_path'     => '',
        ]));
    }

    // =========================================================================
    // POST — saves favicon when valid PNG provided
    // =========================================================================

    public function testPostSavesValidFavicon(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $log = $this->createMock(LogRepository::class);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Créer un PNG minimal valide (1×1 pixel transparent)
        $pngData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            true
        );
        $tmpFavicon = $this->tmpDir . '/valid.png';
        file_put_contents($tmpFavicon, $pngData);

        $_FILES['favicon'] = [
            'name'     => 'valid.png',
            'type'     => 'image/png',
            'tmp_name' => $tmpFavicon,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen((string) $pngData),
        ];

        // Surcharge moveUploadedFile pour éviter la vérification is_uploaded_file
        $controller = $this->getMockBuilder(SettingsController::class)
            ->setConstructorArgs([
                $auth,
                $log,
                $this->configFile,
                $this->tmpDir,
                $this->createMock(ViewRenderer::class),
            ])
            ->onlyMethods(['moveUploadedFile'])
            ->getMock();

        $controller->method('moveUploadedFile')
            ->willReturnCallback(fn (string $tmp, string $dest): bool => copy($tmp, $dest));

        $this->expectException(TerminateException::class);

        $controller->index(new Request('POST', '/personnalisation.php', body: [
            'site_title'       => 'ICO',
            'site_description' => '',
            'project_path'     => '',
        ]));

        $this->assertFileExists($this->tmpDir . '/favicon-custom.png');
    }

    // =========================================================================
    // POST — reset favicon supprime favicon-custom.png
    // =========================================================================

    public function testPostResetFaviconDeletesCustomFile(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('getLoggedInAdminId')->willReturn(1);

        $log = $this->createMock(LogRepository::class);
        $log->expects($this->once())->method('log')
            ->with(1, 'UPDATE_SETTINGS', $this->anything());

        file_put_contents($this->tmpDir . '/favicon-custom.png', 'fake');
        $this->assertFileExists($this->tmpDir . '/favicon-custom.png');

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController(auth: $auth, log: $log);
        $controller->index(new Request('POST', '/personnalisation.php', body: [
            'action' => 'reset_favicon',
        ]));

        $this->assertFileDoesNotExist($this->tmpDir . '/favicon-custom.png');
    }

    public function testPostResetFaviconSucceedsWhenNoCustomFileExists(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->expectException(TerminateException::class);

        $controller = $this->makeController(auth: $auth);
        $controller->index(new Request('POST', '/personnalisation.php', body: [
            'action' => 'reset_favicon',
        ]));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeController(
        ?AuthService   $auth = null,
        ?LogRepository $log  = null,
        ?ViewRenderer  $view = null,
    ): SettingsController {
        return new SettingsController(
            $auth ?? $this->createMock(AuthService::class),
            $log  ?? $this->createMock(LogRepository::class),
            $this->configFile,
            $this->tmpDir,
            $view ?? $this->createMock(ViewRenderer::class),
        );
    }

    private function removeDirRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach ((array) scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeDirRecursive($full) : unlink($full);
        }

        rmdir($path);
    }
}
