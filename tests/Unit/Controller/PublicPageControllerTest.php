<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\PublicPageController;
use ICO\Http\Request;
use ICO\Repository\InfoPageRepository;
use ICO\Service\MarkdownService;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class PublicPageControllerTest extends TestCase
{
    public function testShowConvertsMarkdownContentToHtml(): void
    {
        $repoMock = $this->createMock(InfoPageRepository::class);
        $repoMock->method('findBySlug')->willReturn([
            'id'      => 1,
            'title'   => 'Ma page',
            'slug'    => 'ma-page',
            'content' => '# Titre' . "\n\n" . 'Un **paragraphe**.',
        ]);

        $capturedData = null;
        $viewMock     = $this->createMock(ViewRenderer::class);
        $viewMock->method('render')->willReturnCallback(
            function (string $tpl, array $data) use (&$capturedData): void {
                $capturedData = $data;
            }
        );

        $controller = new PublicPageController($this->makeConfig(), $repoMock, $viewMock, new MarkdownService());
        $controller->show(new Request('GET', '/page.php', ['slug' => 'ma-page']));

        $this->assertStringContainsString('<h1>Titre</h1>', $capturedData['content_html']);
        $this->assertStringContainsString('<strong>paragraphe</strong>', $capturedData['content_html']);
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_publicpage_cfg_' . uniqid();
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
