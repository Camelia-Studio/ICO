<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Repository\InfoPageRepository;
use ICO\View\ViewRenderer;

/**
 * Contrôleur d'affichage public des pages "en savoir plus".
 *
 * URL : /page.php?slug=<slug>
 */
class PublicPageController
{
    public function __construct(
        private readonly Config             $config,
        private readonly InfoPageRepository $infoPageRepo,
        private readonly ViewRenderer       $view,
    ) {
    }

    public function show(Request $request): void
    {
        $slug = trim((string) $request->query('slug', ''));

        if ($slug === '') {
            Response::redirect('index.php')->send();
            throw new TerminateException();
        }

        $page = $this->infoPageRepo->findBySlug($slug);

        if ($page === null) {
            http_response_code(404);
            $this->view->render('pages/public-page', [
                'page'        => null,
                'site_title'  => $this->config->getSiteTitle(),
                'version'     => $this->config->getVersion(),
            ]);
            return;
        }

        $this->view->render('pages/public-page', [
            'page'       => $page,
            'site_title' => $this->config->getSiteTitle(),
            'version'    => $this->config->getVersion(),
        ]);
    }
}
