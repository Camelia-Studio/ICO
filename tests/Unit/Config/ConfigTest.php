<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Config;

use ICO\Config\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ico_config_test_' . uniqid();
        mkdir($this->tmpDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // fromFile — avec fichiers présents
    // -------------------------------------------------------------------------

    public function testFromFileReadsAllLines(): void
    {
        $configFile  = $this->tmpDir . '/config.txt';
        $versionFile = $this->tmpDir . '/version.txt';

        file_put_contents($configFile, "Mon Site\nUne description\nmon-ico");
        file_put_contents($versionFile, '1.2.3');

        $config = Config::fromFile($configFile, $versionFile);

        $this->assertSame('Mon Site', $config->getSiteTitle());
        $this->assertSame('Une description', $config->getSiteDescription());
        $this->assertSame('mon-ico', $config->getBasePath());
        $this->assertSame('1.2.3', $config->getVersion());
    }

    public function testFromFileTrimsTitleAndDescription(): void
    {
        $configFile  = $this->tmpDir . '/config.txt';
        $versionFile = $this->tmpDir . '/version.txt';

        file_put_contents($configFile, "  Mon Site  \n  Description  \n  sous-dossier  ");
        file_put_contents($versionFile, '  2.0.0  ');

        $config = Config::fromFile($configFile, $versionFile);

        $this->assertSame('Mon Site', $config->getSiteTitle());
        $this->assertSame('Description', $config->getSiteDescription());
        $this->assertSame('sous-dossier', $config->getBasePath());
        $this->assertSame('2.0.0', $config->getVersion());
    }

    // -------------------------------------------------------------------------
    // fromFile — fichiers absents
    // -------------------------------------------------------------------------

    public function testFromFileUsesDefaultsWhenConfigFileMissing(): void
    {
        $config = Config::fromFile(
            $this->tmpDir . '/noconfig.txt',
            $this->tmpDir . '/noversion.txt',
        );

        $this->assertSame('ICO', $config->getSiteTitle());
        $this->assertSame('', $config->getSiteDescription());
        $this->assertSame('', $config->getBasePath());
        $this->assertSame('inconnue', $config->getVersion());
    }

    public function testFromFileUsesDefaultVersionWhenVersionFileMissing(): void
    {
        $configFile = $this->tmpDir . '/config.txt';
        file_put_contents($configFile, "Titre\nDesc\n");

        $config = Config::fromFile($configFile, $this->tmpDir . '/noversion.txt');

        $this->assertSame('Titre', $config->getSiteTitle());
        $this->assertSame('inconnue', $config->getVersion());
    }

    public function testFromFileReadsVersionFileOnly(): void
    {
        $versionFile = $this->tmpDir . '/version.txt';
        file_put_contents($versionFile, '3.1.0');

        $config = Config::fromFile($this->tmpDir . '/noconfig.txt', $versionFile);

        $this->assertSame('ICO', $config->getSiteTitle());
        $this->assertSame('3.1.0', $config->getVersion());
    }

    // -------------------------------------------------------------------------
    // getAllowedExtensions
    // -------------------------------------------------------------------------

    public function testGetAllowedExtensionsReturnsExpectedList(): void
    {
        $config = Config::fromFile(
            $this->tmpDir . '/noconfig.txt',
            $this->tmpDir . '/noversion.txt',
        );

        $extensions = $config->getAllowedExtensions();

        $this->assertContains('jpg', $extensions);
        $this->assertContains('jpeg', $extensions);
        $this->assertContains('png', $extensions);
        $this->assertContains('gif', $extensions);
    }

    // -------------------------------------------------------------------------
    // configureSession — vérifie l'absence d'erreur
    // -------------------------------------------------------------------------

    public function testConfigureSessionDoesNotThrow(): void
    {
        $config = Config::fromFile(
            $this->tmpDir . '/noconfig.txt',
            $this->tmpDir . '/noversion.txt',
        );

        // On ne vérifie pas le résultat (ini_set / session_set_cookie_params
        // peuvent échouer silencieusement en CLI), on s'assure juste que ça ne lève pas.
        $this->expectNotToPerformAssertions();
        $config->configureSession();
    }
}
