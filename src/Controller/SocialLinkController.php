<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Repository\LogRepository;
use ICO\Repository\SocialLinkRepository;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;

/**
 * Contrôleur de gestion des liens sociaux (admin).
 *
 * Actions disponibles via ?action= :
 *   list   (défaut) — liste de tous les liens
 *   new              — formulaire d'ajout
 *   edit             — formulaire d'édition (?id=X)
 *   save   (POST)    — création ou mise à jour
 *   delete (POST)    — suppression
 */
class SocialLinkController
{
    public function __construct(
        private readonly Config               $config,
        private readonly AuthService          $authService,
        private readonly SocialLinkRepository $socialLinkRepo,
        private readonly LogRepository        $logRepo,
        private readonly ViewRenderer         $view,
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
            'new'     => $this->showForm($request),
            'edit'    => $this->showForm($request),
            'save'    => $this->save($request),
            'delete'  => $this->delete($request),
            'reorder' => $this->reorder($request),
            default   => $this->list(),
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

        $this->view->render('pages/social-links', [
            'links'           => $this->socialLinkRepo->findAll(),
            'success_message' => $successMessage,
            'error_message'   => $errorMessage,
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
        $link = null;

        if ($id > 0) {
            $link = $this->socialLinkRepo->findById($id);
            if ($link === null) {
                $_SESSION['error_message'] = 'Lien introuvable.';
                Response::redirect('liens-sociaux.php')->send();
                throw new TerminateException();
            }
        }

        $errorMessage = $_SESSION['error_message'] ?? '';
        unset($_SESSION['error_message']);

        $this->view->render('pages/social-link-edit', [
            'link'          => $link,
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
            Response::redirect('liens-sociaux.php')->send();
            throw new TerminateException();
        }

        $id       = (int) $request->post('id', 0);
        $label    = trim((string) $request->post('label', ''));
        $url      = trim((string) $request->post('url', ''));
        $isActive = $request->post('is_active') === '1';

        if ($label === '') {
            $_SESSION['error_message'] = 'Le nom est requis.';
            $redirect = $id > 0 ? 'liens-sociaux.php?action=edit&id=' . $id : 'liens-sociaux.php?action=new';
            Response::redirect($redirect)->send();
            throw new TerminateException();
        }

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $_SESSION['error_message'] = 'Une URL valide est requise.';
            $redirect = $id > 0 ? 'liens-sociaux.php?action=edit&id=' . $id : 'liens-sociaux.php?action=new';
            Response::redirect($redirect)->send();
            throw new TerminateException();
        }

        $adminId = $this->authService->getLoggedInAdminId();

        if ($id > 0) {
            $existing     = $this->socialLinkRepo->findById($id);
            $displayOrder = $existing !== null ? (int) $existing['display_order'] : $this->socialLinkRepo->nextDisplayOrder();

            $this->socialLinkRepo->update($id, $label, $url, $displayOrder, $isActive);
            if ($adminId !== null) {
                $this->logRepo->log($adminId, 'UPDATE_SOCIAL_LINK', sprintf('Modification du lien « %s »', $label), $url);
            }

            $_SESSION['success_message'] = sprintf('Lien « %s » mis à jour.', $label);
        } else {
            $this->socialLinkRepo->create($label, $url, $this->socialLinkRepo->nextDisplayOrder(), $isActive);
            if ($adminId !== null) {
                $this->logRepo->log($adminId, 'CREATE_SOCIAL_LINK', sprintf('Ajout du lien « %s »', $label), $url);
            }

            $_SESSION['success_message'] = sprintf('Lien « %s » ajouté.', $label);
        }

        Response::redirect('liens-sociaux.php')->send();
        throw new TerminateException();
    }

    // -------------------------------------------------------------------------
    // Réordonnancement (glisser/déposer)
    // -------------------------------------------------------------------------

    private function reorder(Request $request): void
    {
        if (!$request->isPost()) {
            Response::redirect('liens-sociaux.php')->send();
            throw new TerminateException();
        }

        /** @var string[] $rawIds */
        $rawIds     = array_filter((array) $request->post('order', []), is_string(...));
        $orderedIds = array_map(intval(...), $rawIds);

        $this->socialLinkRepo->reorder($orderedIds);

        Response::redirect('liens-sociaux.php')->send();
        throw new TerminateException();
    }

    // -------------------------------------------------------------------------
    // Suppression
    // -------------------------------------------------------------------------

    private function delete(Request $request): void
    {
        if (!$request->isPost()) {
            Response::redirect('liens-sociaux.php')->send();
            throw new TerminateException();
        }

        $id = (int) $request->post('id', 0);

        if ($id <= 0) {
            $_SESSION['error_message'] = 'Identifiant invalide.';
            Response::redirect('liens-sociaux.php')->send();
            throw new TerminateException();
        }

        $link = $this->socialLinkRepo->findById($id);

        if ($link === null || !$this->socialLinkRepo->delete($id)) {
            $_SESSION['error_message'] = 'Impossible de supprimer le lien.';
            Response::redirect('liens-sociaux.php')->send();
            throw new TerminateException();
        }

        $adminId = $this->authService->getLoggedInAdminId();
        if ($adminId !== null) {
            $this->logRepo->log(
                $adminId,
                'DELETE_SOCIAL_LINK',
                sprintf('Suppression du lien « %s »', $link['label']),
                (string) $link['url'],
            );
        }

        $_SESSION['success_message'] = sprintf('Lien « %s » supprimé.', $link['label']);
        Response::redirect('liens-sociaux.php')->send();
        throw new TerminateException();
    }
}
