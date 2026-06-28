<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\View;

use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class ShareToolbarIconTest extends TestCase
{
    private const string SHARE_ICON_PATH = '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"';

    private const string SAUCENAO_ICON_PATH = '<path d="M11.625 16.5a1.875 1.875 0 1 0 0-3.75 1.875 1.875 0 0 0 0 3.75Z"';

    private ViewRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ViewRenderer(dirname(__DIR__, 3) . '/src/View');
        $this->renderer->addGlobal('version', 'test');
        $this->renderer->addGlobal('favicon_file', 'favicon.png');
        $this->renderer->addGlobal('social_links', []);
    }

    public function testSharePageUsesDedicatedShareAndSauceNaoIcons(): void
    {
        $output = $this->render('pages/share', [
            'site_title'       => 'ICO',
            'absolute_page'    => 'https://example.test/partage.php?image=image.jpg',
            'og_title'         => 'Image partagee',
            'og_description'   => 'Image partagee depuis ICO',
            'absolute_image'   => 'https://example.test/image.jpg',
            'image_url'        => 'https://example.test/image.jpg',
            'is_video'         => false,
            'filename'         => 'image.jpg',
            'is_private_image' => false,
            'allow_share'      => true,
            'allow_download'   => true,
            'allow_source'     => true,
        ]);

        $this->assertStringContainsString(self::SHARE_ICON_PATH, $this->extractActionMarkup($output, 'Partager'));
        $this->assertStringContainsString(self::SAUCENAO_ICON_PATH, $this->extractActionMarkup($output, 'Source ?'));
    }

    public function testLightboxUsesDedicatedShareAndSauceNaoIcons(): void
    {
        $output = $this->renderLayout('partials/lightbox', [
            'slideshow_interval' => 5,
            'allow_download'     => true,
            'allow_share'        => true,
            'allow_source'       => true,
        ]);

        $this->assertStringContainsString(self::SHARE_ICON_PATH, $this->extractElementMarkup($output, 'lb-share-btn'));
        $this->assertStringContainsString(self::SAUCENAO_ICON_PATH, $this->extractElementMarkup($output, 'lb-source-btn'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data): string
    {
        ob_start();
        $this->renderer->render($view, $data);

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderLayout(string $view, array $data): string
    {
        ob_start();
        $this->renderer->renderLayout($view, $data);

        return (string) ob_get_clean();
    }

    private function extractActionMarkup(string $html, string $label): string
    {
        $pattern = '~<(?:button|a)\b[^>]*class="action-button"[^>]*>.*?' . preg_quote($label, '~') . '.*?</(?:button|a)>~s';

        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $matches);

        return $matches[0];
    }

    private function extractElementMarkup(string $html, string $class): string
    {
        $pattern = '~<(?:button|a)\b[^>]*\b' . preg_quote($class, '~') . '\b[^>]*>.*?</(?:button|a)>~s';

        $this->assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $matches);

        return $matches[0];
    }
}
