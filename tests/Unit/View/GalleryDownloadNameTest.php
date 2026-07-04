<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\View;

use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class GalleryDownloadNameTest extends TestCase
{
    private ViewRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ViewRenderer(dirname(__DIR__, 3) . '/src/View');
        $this->renderer->addGlobal('version', 'test');
        $this->renderer->addGlobal('favicon_file', 'favicon.png');
        $this->renderer->addGlobal('social_links', []);
    }

    public function testPrivateGalleryExposesOriginalFilenameForLightboxDownload(): void
    {
        $output = $this->render('pages/gallery-private', [
            'error_title'        => null,
            'error_message'      => null,
            'album_data'         => [
                'title'          => 'Album privé',
                'description'    => '',
                'mature_content' => false,
                'more_info_url'  => '',
            ],
            'images'             => [[
                'url'          => 'images.php?path=liste_albums_prives%2Fsecret%2Fscreenshot-2026-07-04-at-15.44.22.png&key=abc',
                'share_url'    => 'partage.php?image=encoded',
                'filename'     => 'screenshot-2026-07-04-at-15.44.22.png',
                'is_top'       => false,
                'aspect_ratio' => 1.0,
                'type'         => 'image',
                'mime'         => null,
            ]],
            'header_image'       => null,
            'share_key'          => 'abc',
            'site_title'         => 'ICO',
            'slideshow_interval' => 5,
            'allow_download'     => true,
            'allow_share'        => true,
            'allow_source'       => true,
        ]);

        $this->assertStringContainsString('data-download-name="screenshot-2026-07-04-at-15.44.22.png"', $output);
    }

    public function testPublicGalleryExposesOriginalFilenameForLightboxDownload(): void
    {
        $output = $this->render('pages/gallery-public', [
            'album_info'         => [
                'title'          => 'Album public',
                'description'    => '',
                'mature_content' => false,
                'more_info_url'  => '',
                'zip_download'   => false,
            ],
            'images'             => [[
                'url'          => 'liste_albums/public/screenshot-2026-07-04-at-15.44.22.png',
                'filename'     => 'screenshot-2026-07-04-at-15.44.22.png',
                'is_top'       => false,
                'aspect_ratio' => 1.0,
                'type'         => 'image',
                'mime'         => null,
            ]],
            'header_image'       => null,
            'parent_path'        => 'liste_albums',
            'breadcrumbs'        => [['label' => 'Accueil', 'url' => 'albums.php']],
            'site_title'         => 'ICO',
            'rss_url'            => 'rss.php?path=liste_albums%2Fpublic',
            'zip_url'            => 'zip.php?path=liste_albums%2Fpublic',
            'slideshow_interval' => 5,
            'allow_download'     => true,
            'allow_share'        => true,
            'allow_source'       => true,
        ]);

        $this->assertStringContainsString('data-download-name="screenshot-2026-07-04-at-15.44.22.png"', $output);
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
}
