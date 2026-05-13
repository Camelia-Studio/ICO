<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\FeedController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Service\AlbumService;
use ICO\Service\PathService;
use PHPUnit\Framework\TestCase;

class FeedControllerTest extends TestCase
{
    private string $tmpDir;

    private string $albumsRoot;

    protected function setUp(): void
    {
        $this->tmpDir     = sys_get_temp_dir() . '/ico_feed_test_' . uniqid();
        $this->albumsRoot = $this->tmpDir . '/liste_albums';
        mkdir($this->albumsRoot, 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
    }

    public function testAlbumFeedReturnsValidRssXml(): void
    {
        $album = $this->albumsRoot . '/my-album';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Mon Album\nDescription test\n18-");
        file_put_contents($album . '/photo.jpg', 'fake');

        $request = new Request('GET', '/rss.php', ['path' => 'liste_albums/my-album']);

        ob_start();
        $this->makeController()->album($request);
        $output = (string) ob_get_clean();

        $this->assertStringStartsWith('<?xml', $output);
        $this->assertStringContainsString('<rss version="2.0"', $output);
        $this->assertStringContainsString('Mon Album', $output);
        $this->assertStringContainsString('<language>fr-FR</language>', $output);
        $this->assertStringContainsString('<item>', $output);
        $this->assertStringContainsString('photo', $output);
    }

    public function testAlbumFeedContainsEnclosureForEachImage(): void
    {
        $album = $this->albumsRoot . '/enclosure-test';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Test\n\n18-");
        file_put_contents($album . '/a.jpg', 'data');
        file_put_contents($album . '/b.png', 'data2');

        $request = new Request('GET', '/rss.php', ['path' => 'liste_albums/enclosure-test']);

        ob_start();
        $this->makeController()->album($request);
        $output = (string) ob_get_clean();

        $this->assertSame(2, substr_count($output, '<item>'));
        $this->assertStringContainsString('image/jpeg', $output);
        $this->assertStringContainsString('image/png', $output);
    }

    public function testAlbumFeedEmptyAlbumHasNoItems(): void
    {
        $album = $this->albumsRoot . '/empty-album';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Album Vide\n\n18-");

        $request = new Request('GET', '/rss.php', ['path' => 'liste_albums/empty-album']);

        ob_start();
        $this->makeController()->album($request);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('<rss', $output);
        $this->assertStringNotContainsString('<item>', $output);
    }

    public function testAlbumFeedInvalidPathThrowsTerminateException(): void
    {
        $this->expectException(TerminateException::class);
        $request = new Request('GET', '/rss.php', ['path' => '/etc/passwd']);
        $this->makeController()->album($request);
    }

    public function testAlbumFeedEmptyPathThrowsTerminateException(): void
    {
        $this->expectException(TerminateException::class);
        $request = new Request('GET', '/rss.php', []);
        $this->makeController()->album($request);
    }

    public function testAlbumFeedDescriptionFallsBackToTitle(): void
    {
        $album = $this->albumsRoot . '/no-desc';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Titre Seul\n\n18-");

        $request = new Request('GET', '/rss.php', ['path' => 'liste_albums/no-desc']);

        ob_start();
        $this->makeController()->album($request);
        $output = (string) ob_get_clean();

        $this->assertSame(2, substr_count($output, 'Titre Seul'));
    }

    public function testAlbumFeedContainsSelfLink(): void
    {
        $album = $this->albumsRoot . '/self-link';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Self\n\n18-");

        $request = new Request('GET', '/rss.php', ['path' => 'liste_albums/self-link']);

        ob_start();
        $this->makeController()->album($request);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('rel="self"', $output);
        $this->assertStringContainsString('rss.php', $output);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeController(): FeedController
    {
        $tmp = sys_get_temp_dir() . '/ico_feed_cfg_' . uniqid();
        mkdir($tmp, 0o775, true);
        file_put_contents($tmp . '/config.txt', "Test Site\nDesc\n");
        file_put_contents($tmp . '/version.txt', '1.0.0');
        $config = Config::fromFile($tmp . '/config.txt', $tmp . '/version.txt');
        unlink($tmp . '/config.txt');
        unlink($tmp . '/version.txt');
        rmdir($tmp);

        return new FeedController(
            $config,
            new AlbumService($this->albumsRoot, $this->tmpDir . '/priv'),
            new PathService($this->tmpDir, 'http://localhost'),
        );
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
