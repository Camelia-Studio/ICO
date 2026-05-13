<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Repository\InfoPageRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;

/**
 * Contrôleur de gestion des pages "en savoir plus" (admin).
 *
 * Actions disponibles via ?action= :
 *   list   (défaut) — liste de toutes les pages
 *   new              — formulaire de création
 *   edit             — formulaire d'édition (?id=X)
 *   save   (POST)    — création ou mise à jour
 *   delete (POST)    — suppression
 */
class InfoPageController
{
    public function __construct(
        private readonly Config              $config,
        private readonly AuthService         $authService,
        private readonly InfoPageRepository  $infoPageRepo,
        private readonly LogRepository       $logRepo,
        private readonly string              $baseUrl,
        private readonly ViewRenderer        $view,
        private readonly AlbumService        $albumService,
        private readonly string              $albumsRoot,
        private readonly string              $projectRoot,
    ) {
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function handle(Request $request): void
    {
        if (!$this->authService->isLoggedIn()) {
            Response::redirect('admin.php?action=login')->send();
            throw new TerminateException();
        }

        $action = (string) $request->query('action', 'list');

        match ($action) {
            'new'    => $this->showForm($request),
            'edit'   => $this->showForm($request),
            'save'   => $this->save($request),
            'delete' => $this->delete($request),
            default  => $this->list(),
        };
    }

    // -------------------------------------------------------------------------
    // Liste
    // -------------------------------------------------------------------------

    private function list(): void
    {
        $successMessage = $_SESSION['success_message'] ?? '';
        $errorMessage   = $_SESSION['error_message']   ?? '';
        unset($_SESSION['success_message'], $_SESSION['error_message']);

        $this->view->render('pages/info-pages-list', [
            'pages'           => $this->infoPageRepo->findAll(),
            'success_message' => $successMessage,
            'error_message'   => $errorMessage,
            'base_url'        => $this->baseUrl,
            'site_title'      => $this->config->getSiteTitle(),
            'version'         => $this->config->getVersion(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Formulaire création / édition
    // -------------------------------------------------------------------------

    private function showForm(Request $request): void
    {
        $id   = (int) $request->query('id', 0);
        $page = null;

        if ($id > 0) {
            $page = $this->infoPageRepo->findById($id);
            if ($page === null) {
                $_SESSION['error_message'] = 'Page introuvable.';
                Response::redirect('pages-info.php')->send();
                throw new TerminateException();
            }
        }

        $errorMessage = $_SESSION['error_message'] ?? '';
        unset($_SESSION['error_message']);

        $albums = null;
        if ($page !== null) {
            $albums = $this->scanAlbumsWithLinkStatus($page['slug']);
        }

        $this->view->render('pages/info-page-edit', [
            'page'          => $page,
            'albums'        => $albums,
            'error_message' => $errorMessage,
            'site_title'    => $this->config->getSiteTitle(),
            'version'       => $this->config->getVersion(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Sauvegarde (création ou mise à jour)
    // -------------------------------------------------------------------------

    private function save(Request $request): void
    {
        if (!$request->isPost()) {
            Response::redirect('pages-info.php')->send();
            throw new TerminateException();
        }

        $id          = (int) $request->post('id', 0);
        $title       = trim((string) $request->post('title', ''));
        $slug        = trim((string) $request->post('slug', ''));
        $content     = (string) $request->post('content', '');
        $isPublished = $request->post('is_published') === '1';

        // Validation
        if ($title === '') {
            $_SESSION['error_message'] = 'Le titre est requis.';
            $redirect = $id > 0 ? 'pages-info.php?action=edit&id=' . $id : 'pages-info.php?action=new';
            Response::redirect($redirect)->send();
            throw new TerminateException();
        }

        $slug = $slug === '' ? $this->generateSlug($title) : $this->sanitizeSlug($slug);

        if ($slug === '') {
            $_SESSION['error_message'] = 'Le slug généré est invalide.';
            $redirect = $id > 0 ? 'pages-info.php?action=edit&id=' . $id : 'pages-info.php?action=new';
            Response::redirect($redirect)->send();
            throw new TerminateException();
        }

        if ($this->infoPageRepo->slugExists($slug, $id > 0 ? $id : null)) {
            $_SESSION['error_message'] = sprintf('Le slug « %s » est déjà utilisé par une autre page.', $slug);
            $redirect = $id > 0 ? 'pages-info.php?action=edit&id=' . $id : 'pages-info.php?action=new';
            Response::redirect($redirect)->send();
            throw new TerminateException();
        }

        $adminId = $this->authService->getLoggedInAdminId();

        /** @var string[] $checkedPaths */
        $checkedPaths = array_filter((array) $request->post('album_links', []), 'is_string');

        if ($id > 0) {
            $oldPage    = $this->infoPageRepo->findById($id);
            $oldSlug    = $oldPage !== null ? (string) $oldPage['slug'] : $slug;
            $this->infoPageRepo->update($id, $title, $slug, $content, $isPublished);
            $this->syncAlbumLinks($oldSlug, $slug, $checkedPaths);

            if ($adminId !== null) {
                $this->logRepo->log($adminId, 'UPDATE_INFO_PAGE', sprintf('Modification de la page « %s »', $title), $slug);
            }

            $_SESSION['success_message'] = sprintf('Page « %s » mise à jour.', $title);
        } else {
            $this->infoPageRepo->create($title, $slug, $content, $isPublished);
            $this->syncAlbumLinks('', $slug, $checkedPaths);

            if ($adminId !== null) {
                $this->logRepo->log($adminId, 'CREATE_INFO_PAGE', sprintf('Création de la page « %s »', $title), $slug);
            }

            $_SESSION['success_message'] = sprintf('Page « %s » créée.', $title);
        }

        Response::redirect('pages-info.php')->send();
        throw new TerminateException();
    }

    // -------------------------------------------------------------------------
    // Suppression
    // -------------------------------------------------------------------------

    private function delete(Request $request): void
    {
        if (!$request->isPost()) {
            Response::redirect('pages-info.php')->send();
            throw new TerminateException();
        }

        $id = (int) $request->post('id', 0);

        if ($id <= 0) {
            $_SESSION['error_message'] = 'Identifiant invalide.';
            Response::redirect('pages-info.php')->send();
            throw new TerminateException();
        }

        $page = $this->infoPageRepo->findById($id);

        if ($page === null || !$this->infoPageRepo->delete($id)) {
            $_SESSION['error_message'] = 'Impossible de supprimer la page.';
            Response::redirect('pages-info.php')->send();
            throw new TerminateException();
        }

        $adminId = $this->authService->getLoggedInAdminId();
        if ($adminId !== null) {
            $this->logRepo->log(
                $adminId,
                'DELETE_INFO_PAGE',
                sprintf('Suppression de la page « %s »', $page['title']),
                (string) $page['slug'],
            );
        }

        $_SESSION['success_message'] = sprintf('Page « %s » supprimée.', $page['title']);
        Response::redirect('pages-info.php')->send();
        throw new TerminateException();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function generateSlug(string $title): string
    {
        return $this->sanitizeSlug($title);
    }

    private function sanitizeSlug(string $value): string
    {
        $slug = mb_strtolower($value, 'UTF-8');
        $slug = strtr($slug, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    private function pageUrl(string $slug): string
    {
        return 'page.php?slug=' . $slug;
    }

    /**
     * Retourne la liste de tous les albums feuilles avec leur statut de liaison à cette page.
     *
     * @return array<int, array{title: string, rel_path: string, more_info_url: string, linked: bool, other_link: bool}>
     */
    private function scanAlbumsWithLinkStatus(string $slug): array
    {
        $pageUrl = $this->pageUrl($slug);
        $albums  = $this->albumService->getAllLeafAlbums($this->albumsRoot, $this->projectRoot);

        return array_map(static function (array $album) use ($pageUrl): array {
            $album['linked']     = $album['more_info_url'] === $pageUrl;
            $album['other_link'] = $album['more_info_url'] !== '' && $album['more_info_url'] !== $pageUrl;

            return $album;
        }, $albums);
    }

    /**
     * Met à jour les infos.txt des albums selon les checkboxes soumises.
     *
     * @param string[] $checkedPaths chemins relatifs des albums cochés
     */
    private function syncAlbumLinks(string $oldSlug, string $newSlug, array $checkedPaths): void
    {
        $oldUrl  = $oldSlug !== '' ? $this->pageUrl($oldSlug) : '';
        $newUrl  = $this->pageUrl($newSlug);
        $albums  = $this->albumService->getAllLeafAlbums($this->albumsRoot, $this->projectRoot);

        foreach ($albums as $album) {
            $isChecked  = in_array($album['rel_path'], $checkedPaths, true);
            $wasLinked  = $album['more_info_url'] === $oldUrl || $album['more_info_url'] === $newUrl;

            if ($isChecked && !$wasLinked) {
                $this->writeAlbumMoreInfoUrl($album['abs_path'], $newUrl);
            } elseif ($isChecked && $wasLinked && $album['more_info_url'] !== $newUrl) {
                // slug a changé : mettre à jour l'URL
                $this->writeAlbumMoreInfoUrl($album['abs_path'], $newUrl);
            } elseif (!$isChecked && $wasLinked) {
                $this->writeAlbumMoreInfoUrl($album['abs_path'], '');
            }
        }
    }

    private function writeAlbumMoreInfoUrl(string $absPath, string $moreInfoUrl): void
    {
        if (!$this->albumService->isSecurePath($absPath)) {
            return;
        }

        $info        = $this->albumService->getAlbumInfo($absPath);
        $matureStr   = $info['mature_content'] ? '18+' : '18-';
        $content     = $info['title'] . "\n" . $info['description'] . "\n" . $matureStr . "\n" . $moreInfoUrl;

        file_put_contents($absPath . '/infos.txt', $content);
    }
}
