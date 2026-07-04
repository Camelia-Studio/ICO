<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

final class LightboxJavascriptTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/js/lightbox.js');

        $this->assertIsString($script);
        $this->script = $script;
    }

    public function testClosingLightboxExitsFullscreenBeforeHidingViewer(): void
    {
        $closeBody      = $this->extractCloseMethodBody();
        $exitPosition   = strpos($closeBody, '#exitFullscreenIfActive');
        $hiddenPosition = strpos($closeBody, "classList.remove('is-open')");

        $this->assertNotFalse($exitPosition, 'close() must leave browser fullscreen before closing the viewer.');
        $this->assertNotFalse($hiddenPosition, 'close() must still hide the viewer.');
        $this->assertLessThan($hiddenPosition, $exitPosition);
        $this->assertStringContainsString('document.exitFullscreen?.()', $this->script);
    }

    private function extractCloseMethodBody(): string
    {
        $pattern = '~\n    (?:async\s+)?close\(\) \{\n(?<body>.*?)\n    \}~s';

        $this->assertMatchesRegularExpression($pattern, $this->script);
        preg_match($pattern, $this->script, $matches);

        return $matches['body'];
    }
}
