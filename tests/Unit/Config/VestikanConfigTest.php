<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Config;

use ICO\Config\VestikanConfig;
use PHPUnit\Framework\TestCase;

class VestikanConfigTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/vestikan_config_test_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testFromFileReturnsUnconfiguredWhenFileMissing(): void
    {
        $config = VestikanConfig::fromFile($this->tmpFile);

        $this->assertFalse($config->isConfigured());
    }

    public function testFromFileReturnsUnconfiguredWhenFileDoesNotReturnArray(): void
    {
        file_put_contents($this->tmpFile, "<?php\nreturn 'not-an-array';\n");

        $config = VestikanConfig::fromFile($this->tmpFile);

        $this->assertFalse($config->isConfigured());
    }

    public function testFromFileReturnsUnconfiguredWhenKeyMissing(): void
    {
        file_put_contents($this->tmpFile, "<?php\nreturn [\n"
            . "    'base_url' => 'https://vestikan.example',\n"
            . "    'client_id' => 'vk_client_test',\n"
            . "    'client_secret' => 'secret',\n"
            . "];\n");

        $config = VestikanConfig::fromFile($this->tmpFile);

        $this->assertFalse($config->isConfigured());
    }

    public function testFromFileReturnsUnconfiguredWhenKeyEmpty(): void
    {
        file_put_contents($this->tmpFile, "<?php\nreturn [\n"
            . "    'base_url' => '',\n"
            . "    'client_id' => 'vk_client_test',\n"
            . "    'client_secret' => 'secret',\n"
            . "    'redirect_uri' => 'https://ico.example/admin.php?action=vestikan_callback',\n"
            . "];\n");

        $config = VestikanConfig::fromFile($this->tmpFile);

        $this->assertFalse($config->isConfigured());
    }

    public function testFromFileReturnsConfiguredInstance(): void
    {
        file_put_contents($this->tmpFile, "<?php\nreturn [\n"
            . "    'base_url' => 'https://vestikan.example',\n"
            . "    'client_id' => 'vk_client_test',\n"
            . "    'client_secret' => 'secret',\n"
            . "    'redirect_uri' => 'https://ico.example/admin.php?action=vestikan_callback',\n"
            . "];\n");

        $config = VestikanConfig::fromFile($this->tmpFile);

        $this->assertTrue($config->isConfigured());
        $this->assertSame([
            'base_url'      => 'https://vestikan.example',
            'client_id'     => 'vk_client_test',
            'client_secret' => 'secret',
            'redirect_uri'  => 'https://ico.example/admin.php?action=vestikan_callback',
        ], $config->toArray());
    }
}
