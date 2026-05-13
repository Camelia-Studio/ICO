<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Service\AlbumService;
use ICO\Service\PathService;

/**
 * Contrôleur des flux RSS par album public.
 *
 * GET /rss.php?path=liste_albums/mon-album
 */
class FeedController
{
    public function __construct(
        private readonly Config       $config,
        private readonly AlbumService $albumService,
        private readonly PathService  $pathService,
    ) {
    }

    public function album(Request $request): void
    {
        $rawPath     = (string) $request->query('path', '');
        $currentPath = $rawPath !== ''
            ? realpath($this->pathService->toAbsolute(ltrim($rawPath, '/')))
            : false;

        if ($currentPath === false || !$this->albumService->isSecurePath($currentPath)) {
            Response::html('Not Found', 404)->send();
            throw new TerminateException();
        }

        $info    = $this->albumService->getAlbumInfo($currentPath);
        $images  = $this->albumService->getLatestImages($currentPath, PHP_INT_MAX);
        $relPath = $this->pathService->toRelative($currentPath);
        $baseUrl = $this->pathService->getBaseUrl();

        $albumUrl = $baseUrl . '/galeries.php?path=' . urlencode($relPath);
        $feedUrl  = $baseUrl . '/rss.php?path=' . urlencode($rawPath);

        Response::xml($this->buildFeed($info, $images, $albumUrl, $feedUrl))->send();
    }

    /**
     * @param array{title: string, description: string, mature_content: bool, more_info_url: string} $info
     * @param string[] $images Chemins absolus vers les images
     */
    private function buildFeed(array $info, array $images, string $albumUrl, string $feedUrl): string
    {
        $title       = $this->esc($info['title']);
        $description = $this->esc($info['description'] !== '' ? $info['description'] : $info['title']);
        $siteTitle   = $this->esc($this->config->getSiteTitle());
        $albumUrlEsc = $this->esc($albumUrl);
        $feedUrlEsc  = $this->esc($feedUrl);
        $buildDate   = date(DATE_RSS);

        $items = '';

        foreach ($images as $imagePath) {
            $imageUrl    = $this->pathService->toUrl($imagePath);
            $itemTitle   = $this->esc(pathinfo($imagePath, PATHINFO_FILENAME));
            $itemUrl     = $this->esc($imageUrl);
            $rawCtime    = filectime($imagePath);
            $pubDate     = date(DATE_RSS, $rawCtime !== false ? $rawCtime : time());
            $rawSize     = filesize($imagePath);
            $size        = $rawSize !== false ? max(0, $rawSize) : 0;
            $mime        = $this->mimeType(pathinfo($imagePath, PATHINFO_EXTENSION));

            $items .= '<item>'
                . sprintf('<title>%s</title>', $itemTitle)
                . sprintf('<link>%s</link>', $itemUrl)
                . sprintf('<guid isPermaLink="true">%s</guid>', $itemUrl)
                . sprintf('<pubDate>%s</pubDate>', $pubDate)
                . sprintf('<enclosure url="%s" length="%d" type="%s"/>', $itemUrl, $size, $mime)
                . '</item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'
            . '<channel>'
            . sprintf('<title>%s — %s</title>', $title, $siteTitle)
            . sprintf('<link>%s</link>', $albumUrlEsc)
            . sprintf('<description>%s</description>', $description)
            . '<language>fr-FR</language>'
            . sprintf('<lastBuildDate>%s</lastBuildDate>', $buildDate)
            . sprintf('<atom:link href="%s" rel="self" type="application/rss+xml"/>', $feedUrlEsc)
            . $items
            . '</channel>'
            . '</rss>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function mimeType(string $ext): string
    {
        return match (strtolower($ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'application/octet-stream',
        };
    }
}
