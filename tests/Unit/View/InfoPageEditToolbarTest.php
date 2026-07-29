<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\View;

use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class InfoPageEditToolbarTest extends TestCase
{
    private ViewRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ViewRenderer(dirname(__DIR__, 3) . '/src/View');
        $this->renderer->addGlobal('version', 'test');
        $this->renderer->addGlobal('favicon_file', 'favicon.png');
        $this->renderer->addGlobal('social_links', []);
    }

    public function testEditFormRendersMarkdownToolbarWiredToContentTextarea(): void
    {
        $output = $this->render([
            'page'          => null,
            'albums'        => null,
            'error_message' => '',
            'site_title'    => 'ICO',
        ]);

        $this->assertStringContainsString('class="markdown-toolbar" data-target="content"', $output);
        $this->assertStringContainsString('data-md-action="bold"', $output);
        $this->assertStringContainsString('data-md-action="heading"', $output);
        $this->assertStringContainsString('data-md-action="link"', $output);
        $this->assertStringContainsString('<script src="js/markdown-toolbar.js"></script>', $output);
        $this->assertStringContainsString('id="content" name="content"', $output);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(array $data): string
    {
        ob_start();
        $this->renderer->render('pages/info-page-edit', $data);

        return (string) ob_get_clean();
    }
}
