<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

final class AdminMenuStylesheetTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/css/admin-menu.css');

        $this->assertIsString($css);
        $this->css = $css;
    }

    public function testMenuIconBaseRuleSetsCurrentColorForFilledIcons(): void
    {
        $rule = $this->extractRule('.menu-icon svg');

        $this->assertStringContainsString('color: #fff;', $rule);
    }

    public function testMenuIconHoverRuleUpdatesCurrentColorForFilledIcons(): void
    {
        $rule = $this->extractRule('.admin-menu-item:hover .menu-icon svg');

        $this->assertStringContainsString('color: #2196f3;', $rule);
    }

    private function extractRule(string $selector): string
    {
        $pattern = '~' . preg_quote($selector, '~') . '\s*\{(?<body>.*?)\}~s';

        $this->assertMatchesRegularExpression($pattern, $this->css);
        preg_match($pattern, $this->css, $matches);

        return $matches['body'];
    }
}
