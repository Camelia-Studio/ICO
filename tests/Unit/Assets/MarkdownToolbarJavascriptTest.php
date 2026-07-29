<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

final class MarkdownToolbarJavascriptTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/js/markdown-toolbar.js');

        $this->assertIsString($script);
        $this->script = $script;
    }

    public function testBoldActionWrapsSelectionWithDoubleAsterisks(): void
    {
        $this->assertStringContainsString("bold:    ta => wrapSelection(ta, '**', '**', 'texte en gras'),", $this->script);
    }

    public function testHeadingActionPrefixesLinesWithHashes(): void
    {
        $this->assertStringContainsString("heading: ta => prefixLines(ta, '## '),", $this->script);
    }

    public function testLinkActionWrapsSelectionInMarkdownLinkSyntax(): void
    {
        $this->assertStringContainsString("link:    ta => wrapSelection(ta, '[', '](https://)', 'texte du lien'),", $this->script);
    }

    public function testOrderedListActionUsesNumberedPrefixing(): void
    {
        $this->assertStringContainsString('ol:      ta => prefixLines(ta,', $this->script);
        $this->assertStringContainsString(', true),', $this->script);
    }

    public function testToolbarButtonsAreWiredToTargetTextareaViaDataset(): void
    {
        $this->assertStringContainsString("document.getElementById(toolbar.dataset.target)", $this->script);
        $this->assertStringContainsString('data-md-action', $this->script);
    }

    public function testActionsDispatchInputEventSoLivePreviewStaysInSync(): void
    {
        $this->assertStringContainsString("dispatchEvent(new Event('input', { bubbles: true }));", $this->script);
    }
}
