<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Service;

use ICO\Service\MarkdownService;
use PHPUnit\Framework\TestCase;

class MarkdownServiceTest extends TestCase
{
    public function testConvertsBasicMarkdownToHtml(): void
    {
        $service = new MarkdownService();

        $html = $service->toHtml('# Titre' . "\n\n" . 'Un **paragraphe** en gras.');

        $this->assertStringContainsString('<h1>Titre</h1>', $html);
        $this->assertStringContainsString('<strong>paragraphe</strong>', $html);
    }

    public function testPassesThroughRawHtmlForPagesCreatedBeforeMarkdown(): void
    {
        $service = new MarkdownService();

        $html = $service->toHtml('<div class="custom"><p>Contenu HTML existant</p></div>');

        $this->assertStringContainsString('<div class="custom">', $html);
        $this->assertStringContainsString('<p>Contenu HTML existant</p>', $html);
    }
}
