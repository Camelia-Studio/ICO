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
    // getSlideshowInterval
    // -------------------------------------------------------------------------

    public function testFromFileReadsSlideshowIntervalFromLine4(): void
    {
        $configFile  = $this->tmpDir . '/config.txt';
        $versionFile = $this->tmpDir . '/version.txt';

        file_put_contents($configFile, "Mon Site\nDesc\nbase\n8");
        file_put_contents($versionFile, '1.0.0');

        $config = Config::fromFile($configFile, $versionFile);

        $this->assertSame(8, $config->getSlideshowInterval());
    }

    public function testFromFileDefaultsSlideshowIntervalTo5WhenLineMissing(): void
    {
        $configFile  = $this->tmpDir . '/config.txt';
        $versionFile = $this->tmpDir . '/version.txt';

        file_put_contents($configFile, "Mon Site\nDesc\nbase");
        file_put_contents($versionFile, '1.0.0');

        $config = Config::fromFile($configFile, $versionFile);

        $this->assertSame(5, $config->getSlideshowInterval());
    }

    public function testFromFileDefaultsSlideshowIntervalTo5WhenLineInvalid(): void
    {
        $configFile  = $this->tmpDir . '/config.txt';
        $versionFile = $this->tmpDir . '/version.txt';

        file_put_contents($configFile, "Mon Site\nDesc\nbase\nabc");
        file_put_contents($versionFile, '1.0.0');

        $config = Config::fromFile($configFile, $versionFile);

        $this->assertSame(5, $config->getSlideshowInterval());
    }

    public function testFromFileDefaultsSlideshowIntervalTo5WhenLineIsZeroOrNegative(): void
    {
        $configFile  = $this->tmpDir . '/config.txt';
        $versionFile = $this->tmpDir . '/version.txt';

        file_put_contents($configFile, "Mon Site\nDesc\nbase\n0");
        file_put_contents($versionFile, '1.0.0');

        $config = Config::fromFile($configFile, $versionFile);

        $this->assertSame(5, $config->getSlideshowInterval());
    }

    // -------------------------------------------------------------------------
    // getDefaultShareOptions
    // -------------------------------------------------------------------------

    public function testGetDefaultShareOptionsDefaultsAllTrueWhenLine4Absent(): void
    {
        $configFile  = $this->tmpDir . '/config.txt';
        $versionFile = $this->tmpDir . '/version.txt';

        file_put_contents($configFile, "Mon Site\nDesc\nbase\n5");
        file_put_contents($versionFile, '1.0.0');

        $opts = Config::fromFile($configFile, $versionFile)->getDefaultShareOptions();

        $this->assertTrue($opts['download']);
        $this->assertTrue($opts['source']);
        $this->assertTrue($opts['share']);
        $this->assertTrue($opts['rss']);
    }

    public function testGetDefaultShareOptionsReadsFromLine4(): void
    {
        $configFile  = $this->tmpDir . '/config.txt';
        $versionFile = $this->tmpDir . '/version.txt';

        file_put_contents(
            $configFile,
            "Mon Site\nDesc\nbase\n5\n" . json_encode([
                'download' => false,
                'source'   => true,
                'share'    => false,
                'rss'      => false,
            ])
        );
        file_put_contents($versionFile, '1.0.0');

        $opts = Config::fromFile($configFile, $versionFile)->getDefaultShareOptions();

        $this->assertFalse($opts['download']);
        $this->assertTrue($opts['source']);
        $this->assertFalse($opts['share']);
        $this->assertFalse($opts['rss']);
    }

    public function testGetDefaultShareOptionsDefaultsAllTrueForInvalidJson(): void
    {
        $configFile  = $this->tmpDir . '/config.txt';
        $versionFile = $this->tmpDir . '/version.txt';

        file_put_contents($configFile, "Mon Site\nDesc\nbase\n5\nnot-valid-json");
        file_put_contents($versionFile, '1.0.0');

        $opts = Config::fromFile($configFile, $versionFile)->getDefaultShareOptions();

        $this->assertTrue($opts['download']);
        $this->assertTrue($opts['source']);
        $this->assertTrue($opts['share']);
        $this->assertTrue($opts['rss']);
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

    // -------------------------------------------------------------------------
    // getSessionLifetime
    // -------------------------------------------------------------------------

    public function testGetSessionLifetimeReturns7Days(): void
    {
        $config = Config::fromFile(
            $this->tmpDir . '/noconfig.txt',
            $this->tmpDir . '/noversion.txt',
        );

        $this->assertSame(604800, $config->getSessionLifetime());
    }

    // -------------------------------------------------------------------------
    // configureSession — flags de cookie
    // -------------------------------------------------------------------------

    public function testConfigureSessionSetsSevenDayLifetimeAndSecureFlags(): void
    {
        $config = Config::fromFile(
            $this->tmpDir . '/noconfig.txt',
            $this->tmpDir . '/noversion.txt',
        );

        $config->configureSession();

        $params = session_get_cookie_params();

        $this->assertSame(604800, $params['lifetime']);
        $this->assertTrue($params['httponly']);
        $this->assertSame('Lax', $params['samesite']);
    }
}
