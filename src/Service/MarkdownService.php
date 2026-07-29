<?php

declare(strict_types=1);

namespace ICO\Service;

use League\CommonMark\CommonMarkConverter;

/**
 * Convertit du Markdown en HTML pour le contenu des pages "en savoir plus".
 *
 * Configuré en `html_input => allow` : le HTML déjà présent dans les pages
 * créées avant l'introduction du Markdown continue de s'afficher à l'identique,
 * sans migration de données.
 */
class MarkdownService
{
    private readonly CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'allow',
        ]);
    }

    public function toHtml(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }
}
