<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\View;

use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Error\LoaderError;

class ViewRendererTest extends TestCase
{
    private string $tmpDir;

    private ViewRenderer $renderer;

    protected function setUp(): void
    {
        $this->tmpDir   = sys_get_temp_dir() . '/ico_view_test_' . uniqid();
        mkdir($this->tmpDir, 0o775, true);
        $this->renderer = new ViewRenderer($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // render
    // -------------------------------------------------------------------------

    public function testRenderOutputsFileContent(): void
    {
        file_put_contents($this->tmpDir . '/hello.html.twig', '<p>Hello</p>');

        ob_start();
        $this->renderer->render('hello');
        $output = ob_get_clean();

        $this->assertSame('<p>Hello</p>', $output);
    }

    public function testRenderInjectsDataVariables(): void
    {
        file_put_contents($this->tmpDir . '/greet.html.twig', '{{ name }}');

        ob_start();
        $this->renderer->render('greet', ['name' => 'Alice']);
        $output = ob_get_clean();

        $this->assertSame('Alice', $output);
    }

    public function testRenderAutoEscapesHtml(): void
    {
        file_put_contents($this->tmpDir . '/escape.html.twig', '{{ value }}');

        ob_start();
        $this->renderer->render('escape', ['value' => '<script>alert(1)</script>']);
        $output = ob_get_clean();

        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
    }

    public function testRenderThrowsWhenViewMissing(): void
    {
        $this->expectException(LoaderError::class);

        $this->renderer->render('nonexistent/page');
    }

    // -------------------------------------------------------------------------
    // addGlobal
    // -------------------------------------------------------------------------

    public function testAddGlobalMakesVariableAvailableInAllViews(): void
    {
        $this->renderer->addGlobal('site_title', 'MonSite');
        file_put_contents($this->tmpDir . '/title.html.twig', '{{ site_title }}');

        ob_start();
        $this->renderer->render('title');
        $output = ob_get_clean();

        $this->assertSame('MonSite', $output);
    }

    public function testLocalVariableOverridesGlobal(): void
    {
        $this->renderer->addGlobal('version', 'global');
        file_put_contents($this->tmpDir . '/ver.html.twig', '{{ version }}');

        ob_start();
        $this->renderer->render('ver', ['version' => 'local']);
        $output = ob_get_clean();

        $this->assertSame('local', $output);
    }

    // -------------------------------------------------------------------------
    // renderLayout
    // -------------------------------------------------------------------------

    public function testRenderLayoutOutputsPartialContent(): void
    {
        mkdir($this->tmpDir . '/layout', 0o775, true);
        file_put_contents($this->tmpDir . '/layout/header.html.twig', '<header>ICO</header>');

        ob_start();
        $this->renderer->renderLayout('layout/header');
        $output = ob_get_clean();

        $this->assertSame('<header>ICO</header>', $output);
    }

    public function testRenderLayoutInjectsData(): void
    {
        mkdir($this->tmpDir . '/layout', 0o775, true);
        file_put_contents($this->tmpDir . '/layout/footer.html.twig', '{{ version }}');

        ob_start();
        $this->renderer->renderLayout('layout/footer', ['version' => '1.0.0']);
        $output = ob_get_clean();

        $this->assertSame('1.0.0', $output);
    }

    public function testRenderLayoutThrowsWhenPartialMissing(): void
    {
        $this->expectException(LoaderError::class);

        $this->renderer->renderLayout('layout/nonexistent');
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirRecursive($path) : unlink($path);
        }

        rmdir($dir);
    }
}
