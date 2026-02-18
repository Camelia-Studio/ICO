<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\View;

use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        file_put_contents($this->tmpDir . '/hello.php', '<p>Hello</p>');

        ob_start();
        $this->renderer->render('hello');
        $output = ob_get_clean();

        $this->assertSame('<p>Hello</p>', $output);
    }

    public function testRenderInjectsDataVariables(): void
    {
        file_put_contents($this->tmpDir . '/greet.php', '<?php echo $name; ?>');

        ob_start();
        $this->renderer->render('greet', ['name' => 'Alice']);
        $output = ob_get_clean();

        $this->assertSame('Alice', $output);
    }

    public function testRenderExposesRendererAsSelf(): void
    {
        // La vue reçoit $renderer = $this (le ViewRenderer)
        file_put_contents($this->tmpDir . '/self.php', '<?php echo get_class($renderer); ?>');

        ob_start();
        $this->renderer->render('self');
        $output = ob_get_clean();

        $this->assertSame(ViewRenderer::class, $output);
    }

    public function testRenderThrowsWhenViewMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Vue introuvable/');

        $this->renderer->render('nonexistent/page');
    }

    public function testRenderSkipsExistingVariableExtract(): void
    {
        // EXTR_SKIP : si $name est déjà défini par une variable de portée, le post n'écrase pas.
        // Ici on vérifie simplement que le rendu ne plante pas avec EXTR_SKIP.
        file_put_contents($this->tmpDir . '/safe.php', '<?php echo $value; ?>');

        ob_start();
        $this->renderer->render('safe', ['value' => 'ok']);
        $output = ob_get_clean();

        $this->assertSame('ok', $output);
    }

    // -------------------------------------------------------------------------
    // renderLayout
    // -------------------------------------------------------------------------

    public function testRenderLayoutOutputsPartialContent(): void
    {
        mkdir($this->tmpDir . '/layout', 0o775, true);
        file_put_contents($this->tmpDir . '/layout/header.php', '<header>ICO</header>');

        ob_start();
        $this->renderer->renderLayout('layout/header');
        $output = ob_get_clean();

        $this->assertSame('<header>ICO</header>', $output);
    }

    public function testRenderLayoutInjectsData(): void
    {
        mkdir($this->tmpDir . '/layout', 0o775, true);
        file_put_contents($this->tmpDir . '/layout/footer.php', '<?php echo $version; ?>');

        ob_start();
        $this->renderer->renderLayout('layout/footer', ['version' => '1.0.0']);
        $output = ob_get_clean();

        $this->assertSame('1.0.0', $output);
    }

    public function testRenderLayoutThrowsWhenPartialMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Partial introuvable/');

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
