<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Service;

use ICO\Service\FileService;
use PHPUnit\Framework\TestCase;

class FileServiceTest extends TestCase
{
    private FileService $service;

    protected function setUp(): void
    {
        $this->service = new FileService();
    }

    // -------------------------------------------------------------------------
    // sanitizeFilename
    // -------------------------------------------------------------------------

    public function testSanitizeFilenameReplacesSpecialChars(): void
    {
        // L'espace est remplacé par un tiret ; le point est conservé (autorisé)
        $this->assertSame('hello-world.txt', $this->service->sanitizeFilename('hello world.txt'));
    }

    public function testSanitizeFilenameAllowsAlphanumDotDashUnderscore(): void
    {
        $this->assertSame('my_file-1.jpg', $this->service->sanitizeFilename('my_file-1.jpg'));
    }

    public function testSanitizeFilenameStripsLeadingDot(): void
    {
        $result = $this->service->sanitizeFilename('.htaccess');

        $this->assertStringStartsNotWith('.', $result);
    }

    public function testSanitizeFilenameTruncatesTo255Chars(): void
    {
        $long = str_repeat('a', 300);

        $this->assertSame(255, strlen($this->service->sanitizeFilename($long)));
    }

    public function testSanitizeFilenameReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', $this->service->sanitizeFilename(''));
    }

    // -------------------------------------------------------------------------
    // formatFileSize
    // -------------------------------------------------------------------------

    public function testFormatFileSizeBytes(): void
    {
        $this->assertSame('512 B', $this->service->formatFileSize(512));
    }

    public function testFormatFileSizeKilobytes(): void
    {
        $this->assertSame('1 KB', $this->service->formatFileSize(1024));
    }

    public function testFormatFileSizeMegabytes(): void
    {
        $this->assertSame('1 MB', $this->service->formatFileSize(1024 * 1024));
    }

    public function testFormatFileSizeZero(): void
    {
        $this->assertSame('0 B', $this->service->formatFileSize(0));
    }

    public function testFormatFileSizeRoundsToOneDecimal(): void
    {
        // 1.5 KB = 1536 bytes
        $this->assertSame('1.5 KB', $this->service->formatFileSize(1536));
    }

    // -------------------------------------------------------------------------
    // getSecureImageSize
    // -------------------------------------------------------------------------

    public function testGetSecureImageSizeReturnsNullForNonExistentFile(): void
    {
        $this->assertNull($this->service->getSecureImageSize('/nonexistent/file.jpg'));
    }

    // -------------------------------------------------------------------------
    // generateSecureId
    // -------------------------------------------------------------------------

    public function testGenerateSecureIdDefaultLength(): void
    {
        $id = $this->service->generateSecureId();

        $this->assertSame(32, strlen($id));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $id);
    }

    public function testGenerateSecureIdCustomLength(): void
    {
        $id = $this->service->generateSecureId(16);

        $this->assertSame(16, strlen($id));
    }

    public function testGenerateSecureIdProducesUniqueValues(): void
    {
        $a = $this->service->generateSecureId();
        $b = $this->service->generateSecureId();

        $this->assertNotSame($a, $b);
    }

    // -------------------------------------------------------------------------
    // generateShareKey
    // -------------------------------------------------------------------------

    public function testGenerateShareKeyDefaultLength(): void
    {
        $key = $this->service->generateShareKey();

        $this->assertSame(64, strlen($key));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $key);
    }

    // -------------------------------------------------------------------------
    // generateAlbumIdentifier
    // -------------------------------------------------------------------------

    public function testGenerateAlbumIdentifierDefaultLength(): void
    {
        $id = $this->service->generateAlbumIdentifier();

        $this->assertSame(32, strlen($id));
    }

    // -------------------------------------------------------------------------
    // deleteDirectoryRecursively
    // -------------------------------------------------------------------------

    public function testDeleteDirectoryRecursivelyReturnsFalseForNonExistentDir(): void
    {
        $this->assertFalse($this->service->deleteDirectoryRecursively('/nonexistent/path'));
    }

    public function testDeleteDirectoryRecursivelyDeletesNestedStructure(): void
    {
        // Crée une arborescence temporaire
        $base    = sys_get_temp_dir() . '/ico_test_' . uniqid();
        $sub     = $base . '/sub';
        $file    = $sub . '/file.txt';

        mkdir($sub, 0777, true);
        file_put_contents($file, 'test');

        $result = $this->service->deleteDirectoryRecursively($base);

        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($base);
    }
}
