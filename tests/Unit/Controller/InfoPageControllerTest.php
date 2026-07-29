<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\InfoPageController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\InfoPageRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\Service\MarkdownService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class InfoPageControllerTest extends TestCase
{
    public function testPreviewOutputsConvertedMarkdownFragment(): void
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);

        $controller = new InfoPageController(
            $this->makeConfig(),
            $authMock,
            $this->createMock(InfoPageRepository::class),
            $this->createMock(LogRepository::class),
            'http://localhost',
            $this->createMock(ViewRenderer::class),
            new AlbumService('/tmp', '/tmp'),
            '/tmp',
            '/tmp',
            new MarkdownService(),
        );

        $request = new Request('POST', '/pages-info.php', ['action' => 'preview'], [
            'content' => '# Titre',
        ]);

        ob_start();
        try {
            $controller->handle($request);
        } catch (TerminateException) {
        }

        $output = ob_get_clean();

        $this->assertStringContainsString('<h1>Titre</h1>', $output);
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_infopage_cfg_' . uniqid();
        mkdir($tmp, 0o775, true);
        file_put_contents($tmp . '/config.txt', "Test Site\n\njpg,png,gif\n");
        file_put_contents($tmp . '/version.txt', '1.0.0');
        $config = Config::fromFile($tmp . '/config.txt', $tmp . '/version.txt');
        unlink($tmp . '/config.txt');
        unlink($tmp . '/version.txt');
        rmdir($tmp);

        return $config;
    }
}
