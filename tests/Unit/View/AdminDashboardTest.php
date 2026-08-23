<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\View;

use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class AdminDashboardTest extends TestCase
{
    private ViewRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ViewRenderer(dirname(__DIR__, 3) . '/src/View');
        $this->renderer->addGlobal('version', 'test');
        $this->renderer->addGlobal('favicon_file', 'favicon.png');
        $this->renderer->addGlobal('social_links', []);
    }

    public function testPrivateGalleryButtonUsesExpectedLabelAndUrl(): void
    {
        ob_start();
        $this->renderer->render('pages/admin-dashboard', [
            'isFirst'         => false,
            'updateAvailable' => false,
            'updateStatus'    => null,
            'menuItemClass'   => 'admin-menu-item disabled',
        ]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString(
            '<a href="galeries-privees.php?path=liste_albums_prives" class="action-button">Voir la galerie privée</a>',
            $output,
        );
    }
}
