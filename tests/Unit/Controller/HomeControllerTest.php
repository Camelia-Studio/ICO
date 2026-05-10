<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\HomeController;
use ICO\Http\Request;
use ICO\Service\PathService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class HomeControllerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ico_home_test_' . uniqid();
        mkdir($this->tmpDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
    }

    public function testIndexRendersViewWithEmptyCarousel(): void
    {
        $config = $this->makeConfig();
        $view   = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->with('pages/home', $this->callback(
                fn (array $d): bool =>
                isset($d['carousel_images'], $d['site_title'], $d['site_description'])
                && is_array($d['carousel_images'])
            ));

        $controller = new HomeController($config, new PathService($this->tmpDir, 'http://localhost'), $view);
        $controller->index($this->makeRequest());
    }

    public function testIndexCreatesCarouselDirIfMissing(): void
    {
        $config = $this->makeConfig();
        $view   = $this->createMock(ViewRenderer::class);
        $view->method('render');

        $controller = new HomeController($config, new PathService($this->tmpDir, 'http://localhost'), $view);
        $controller->index($this->makeRequest());

        $this->assertDirectoryExists($this->tmpDir . '/img_carrousel');
    }

    public function testIndexReturnsCarouselImagesWhenPresent(): void
    {
        $carouselDir = $this->tmpDir . '/img_carrousel';
        mkdir($carouselDir, 0o775, true);
        file_put_contents($carouselDir . '/photo.jpg', '');
        file_put_contents($carouselDir . '/photo2.png', '');

        $config = $this->makeConfig();

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $controller = new HomeController($config, new PathService($this->tmpDir, 'http://localhost'), $view);
        $controller->index($this->makeRequest());

        $this->assertCount(2, $capturedData['carousel_images']);
        foreach ($capturedData['carousel_images'] as $url) {
            $this->assertStringStartsWith('http://localhost/', $url);
            $this->assertStringNotContainsString($this->tmpDir, $url);
        }
    }

    public function testIndexCarouselLimitedToFive(): void
    {
        $carouselDir = $this->tmpDir . '/img_carrousel';
        mkdir($carouselDir, 0o775, true);
        for ($i = 1; $i <= 8; $i++) {
            file_put_contents($carouselDir . sprintf('/photo%d.jpg', $i), '');
        }

        $config = $this->makeConfig();

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $controller = new HomeController($config, new PathService($this->tmpDir, 'http://localhost'), $view);
        $controller->index($this->makeRequest());

        $this->assertLessThanOrEqual(5, count($capturedData['carousel_images']));
    }

    public function testIndexCarouselTopImageAppearsFirst(): void
    {
        $carouselDir = $this->tmpDir . '/img_carrousel';
        mkdir($carouselDir, 0o775, true);
        file_put_contents($carouselDir . '/older.jpg', '');
        sleep(1);
        file_put_contents($carouselDir . '/newer--top--.jpg', '');

        $config = $this->makeConfig();

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->expects($this->once())->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $controller = new HomeController($config, new PathService($this->tmpDir, 'http://localhost'), $view);
        $controller->index($this->makeRequest());

        $this->assertCount(2, $capturedData['carousel_images']);
        $this->assertStringContainsString('newer--top--', $capturedData['carousel_images'][0]);
    }

    public function testIndexIgnoresNonImageFilesInCarousel(): void
    {
        $carouselDir = $this->tmpDir . '/img_carrousel';
        mkdir($carouselDir, 0o775, true);
        file_put_contents($carouselDir . '/photo.jpg', '');
        file_put_contents($carouselDir . '/notes.txt', '');
        file_put_contents($carouselDir . '/data.json', '');

        $config = $this->makeConfig();

        $capturedData = null;
        $view = $this->createMock(ViewRenderer::class);
        $view->method('render')
            ->willReturnCallback(function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            });

        $controller = new HomeController($config, new PathService($this->tmpDir, 'http://localhost'), $view);
        $controller->index($this->makeRequest());

        $this->assertCount(1, $capturedData['carousel_images']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_home_cfg_' . uniqid();
        mkdir($tmp, 0o775, true);
        file_put_contents($tmp . '/config.txt', "Mon Site\nDescription\n");
        file_put_contents($tmp . '/version.txt', '1.0.0');
        $config = Config::fromFile($tmp . '/config.txt', $tmp . '/version.txt');
        unlink($tmp . '/config.txt');
        unlink($tmp . '/version.txt');
        rmdir($tmp);
        return $config;
    }

    private function makeRequest(): Request
    {
        return Request::fromGlobals();
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
