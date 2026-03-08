<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Service;

use ICO\Service\AlbumService;
use PHPUnit\Framework\TestCase;

class AlbumServiceTest extends TestCase
{
    private string $tmpDir;

    private string $albumsRoot;

    private string $privateRoot;

    private string $carouselRoot;

    private AlbumService $service;

    protected function setUp(): void
    {
        $this->tmpDir       = sys_get_temp_dir() . '/ico_album_test_' . uniqid();
        $this->albumsRoot   = $this->tmpDir . '/liste_albums';
        $this->privateRoot  = $this->tmpDir . '/liste_albums_prives';
        $this->carouselRoot = $this->tmpDir . '/img_carrousel';

        mkdir($this->albumsRoot, 0o775, true);
        mkdir($this->privateRoot, 0o775, true);
        mkdir($this->carouselRoot, 0o775, true);

        $this->service = new AlbumService($this->albumsRoot, $this->privateRoot, $this->carouselRoot);
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // getAlbumInfo — sans fichier infos.txt
    // -------------------------------------------------------------------------

    public function testGetAlbumInfoReturnsDefaultsWithoutInfosFile(): void
    {
        $albumPath = $this->albumsRoot . '/my-album';
        mkdir($albumPath, 0o775, true);

        $info = $this->service->getAlbumInfo($albumPath);

        $this->assertSame('my-album', $info['title']);
        $this->assertSame('', $info['description']);
        $this->assertFalse($info['mature_content']);
        $this->assertSame('', $info['more_info_url']);
    }

    // -------------------------------------------------------------------------
    // getAlbumInfo — avec fichier infos.txt complet
    // -------------------------------------------------------------------------

    public function testGetAlbumInfoReadsInfosFile(): void
    {
        $albumPath = $this->albumsRoot . '/my-album';
        mkdir($albumPath, 0o775, true);
        file_put_contents($albumPath . '/infos.txt', "Mon Album\nSuperbe description\n18+\nhttps://example.com");

        $info = $this->service->getAlbumInfo($albumPath);

        $this->assertSame('Mon Album', $info['title']);
        $this->assertSame('Superbe description', $info['description']);
        $this->assertTrue($info['mature_content']);
        $this->assertSame('https://example.com', $info['more_info_url']);
    }

    public function testGetAlbumInfoMatureContentFalseWhenNotExactly18Plus(): void
    {
        $albumPath = $this->albumsRoot . '/album2';
        mkdir($albumPath, 0o775, true);
        file_put_contents($albumPath . '/infos.txt', "Titre\nDesc\ngeneral\n");

        $info = $this->service->getAlbumInfo($albumPath);

        $this->assertFalse($info['mature_content']);
    }

    public function testGetAlbumInfoUsesBasenameTitleWhenFirstLineEmpty(): void
    {
        $albumPath = $this->albumsRoot . '/folder-name';
        mkdir($albumPath, 0o775, true);
        file_put_contents($albumPath . '/infos.txt', "\nDes\n\n");

        $info = $this->service->getAlbumInfo($albumPath);

        $this->assertSame('folder-name', $info['title']);
    }

    // -------------------------------------------------------------------------
    // getLatestImages
    // -------------------------------------------------------------------------

    public function testGetLatestImagesReturnsEmptyForNonDir(): void
    {
        $images = $this->service->getLatestImages($this->tmpDir . '/nope');

        $this->assertSame([], $images);
    }

    public function testGetLatestImagesFindsImages(): void
    {
        $album = $this->albumsRoot . '/photos';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/a.jpg', '');
        file_put_contents($album . '/b.png', '');
        file_put_contents($album . '/c.txt', '');

        $images = $this->service->getLatestImages($album);

        $this->assertCount(2, $images);
        foreach ($images as $img) {
            $this->assertMatchesRegularExpression('/\.(jpg|png)$/', $img);
        }
    }

    public function testGetLatestImagesRespectsLimit(): void
    {
        $album = $this->albumsRoot . '/many';
        mkdir($album, 0o775, true);
        for ($i = 1; $i <= 6; $i++) {
            file_put_contents($album . sprintf('/img%d.jpg', $i), '');
        }

        $images = $this->service->getLatestImages($album, 3);

        $this->assertCount(3, $images);
    }

    public function testGetLatestImagesIgnoresNonImageFiles(): void
    {
        $album = $this->albumsRoot . '/mixed';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/doc.pdf', '');
        file_put_contents($album . '/readme.md', '');

        $images = $this->service->getLatestImages($album);

        $this->assertSame([], $images);
    }

    // -------------------------------------------------------------------------
    // getImagesRecursively
    // -------------------------------------------------------------------------

    public function testGetImagesRecursivelyReturnsEmptyForNonDir(): void
    {
        $result = $this->service->getImagesRecursively($this->tmpDir . '/nope');

        $this->assertSame([], $result);
    }

    public function testGetImagesRecursivelyFindsNestedImages(): void
    {
        $root  = $this->albumsRoot . '/nested';
        $sub   = $root . '/sub';
        mkdir($sub, 0o775, true);
        file_put_contents($root . '/top.jpg', '');
        file_put_contents($sub . '/deep.png', '');

        $images = $this->service->getImagesRecursively($root);

        $paths = array_column($images, 'path');
        $this->assertCount(2, $images);
        $this->assertContains($root . '/top.jpg', $paths);
        $this->assertContains($sub . '/deep.png', $paths);
    }

    public function testGetImagesRecursivelyReturnsIsMatureFlag(): void
    {
        $album = $this->albumsRoot . '/mature-test';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/infos.txt', "Titre\nDesc\n18+\n");
        file_put_contents($album . '/img.jpg', '');

        $images = $this->service->getImagesRecursively($album);

        $this->assertCount(1, $images);
        $this->assertTrue($images[0]['is_mature']);
    }

    public function testGetImagesRecursivelyRespectsLimit(): void
    {
        $album = $this->albumsRoot . '/big';
        mkdir($album, 0o775, true);
        for ($i = 1; $i <= 10; $i++) {
            file_put_contents($album . sprintf('/img%d.gif', $i), '');
        }

        $images = $this->service->getImagesRecursively($album, 4);

        $this->assertCount(4, $images);
    }

    // -------------------------------------------------------------------------
    // hasSubfolders
    // -------------------------------------------------------------------------

    public function testHasSubfoldersReturnsFalseForNonDir(): void
    {
        $this->assertFalse($this->service->hasSubfolders($this->tmpDir . '/nope'));
    }

    public function testHasSubfoldersReturnsTrueWhenSubdirExists(): void
    {
        $parent = $this->albumsRoot . '/parent';
        mkdir($parent . '/child', 0o775, true);

        $this->assertTrue($this->service->hasSubfolders($parent));
    }

    public function testHasSubfoldersReturnsFalseWithNoSubdir(): void
    {
        $album = $this->albumsRoot . '/flat';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/photo.jpg', '');

        $this->assertFalse($this->service->hasSubfolders($album));
    }

    // -------------------------------------------------------------------------
    // hasImages
    // -------------------------------------------------------------------------

    public function testHasImagesReturnsFalseForNonDir(): void
    {
        $this->assertFalse($this->service->hasImages($this->tmpDir . '/nope'));
    }

    public function testHasImagesReturnsTrueWhenImageExists(): void
    {
        $album = $this->albumsRoot . '/withimg';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/photo.jpeg', '');

        $this->assertTrue($this->service->hasImages($album));
    }

    public function testHasImagesReturnsFalseWithOnlyNonImageFiles(): void
    {
        $album = $this->albumsRoot . '/noimg';
        mkdir($album, 0o775, true);
        file_put_contents($album . '/readme.txt', '');
        file_put_contents($album . '/data.csv', '');

        $this->assertFalse($this->service->hasImages($album));
    }

    // -------------------------------------------------------------------------
    // isSecurePath
    // -------------------------------------------------------------------------

    public function testIsSecurePathReturnsTrueForSubdir(): void
    {
        $sub = $this->albumsRoot . '/photos';
        mkdir($sub, 0o775, true);

        $this->assertTrue($this->service->isSecurePath($sub));
    }

    public function testIsSecurePathReturnsFalseForNonExistentPath(): void
    {
        $this->assertFalse($this->service->isSecurePath($this->albumsRoot . '/nope'));
    }

    public function testIsSecurePathReturnsFalseForOutsideRoot(): void
    {
        // Le tmpDir lui-même est parent de albumsRoot → pas sécurisé
        $this->assertFalse($this->service->isSecurePath($this->tmpDir));
    }

    public function testIsSecurePathReturnsTrueForAlbumsRootItself(): void
    {
        $this->assertTrue($this->service->isSecurePath($this->albumsRoot));
    }

    public function testIsSecurePathReturnsTrueForCarouselSubdir(): void
    {
        $sub = $this->carouselRoot . '/slide1';
        mkdir($sub, 0o775, true);

        $this->assertTrue($this->service->isSecurePath($sub));
    }

    public function testIsSecurePathReturnsTrueForCarouselRootItself(): void
    {
        $this->assertTrue($this->service->isSecurePath($this->carouselRoot));
    }

    // -------------------------------------------------------------------------
    // isSecurePrivatePath
    // -------------------------------------------------------------------------

    public function testIsSecurePrivatePathReturnsTrueForPrivateSubdir(): void
    {
        $sub = $this->privateRoot . '/secret';
        mkdir($sub, 0o775, true);

        $this->assertTrue($this->service->isSecurePrivatePath($sub));
    }

    public function testIsSecurePrivatePathReturnsFalseForPublicDir(): void
    {
        $this->assertFalse($this->service->isSecurePrivatePath($this->albumsRoot));
    }

    public function testIsSecurePrivatePathReturnsFalseForNonExistent(): void
    {
        $this->assertFalse($this->service->isSecurePrivatePath($this->privateRoot . '/nope'));
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

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
