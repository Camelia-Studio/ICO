<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;

/**
 * Contrôleur du proxy d'images privées.
 *
 * Proxy sécurisé : vérifie le chemin puis l'authentification avant de servir le fichier.
 *
 * Deux modes d'authentification :
 *  - admin_session : paramètre GET contenant un session_id d'admin
 *  - key           : clé de partage validée via ShareKeyRepository
 */
class ImageController
{
    public function __construct(
        private readonly AlbumService      $albumService,
        private readonly ShareKeyRepository $shareKeyRepo,
    ) {}

    // -------------------------------------------------------------------------
    // Action principale
    // -------------------------------------------------------------------------

    /**
     * Sert une image privée après contrôle d'accès.
     * Termine l'exécution via exit() car c'est une réponse binaire.
     */
    public function serve(Request $request): never
    {
        $path         = (string) $request->query('path', '');
        $key          = (string) $request->query('key', '');
        $adminSession = (string) $request->query('admin_session', '');

        // Vérification du chemin (doit être sous liste_albums_prives)
        if (!$this->albumService->isSecurePrivatePath($path) || !file_exists($path)) {
            Response::html('', 404)->send();
            throw new TerminateException();
        }

        // Authentification
        if ($adminSession !== '') {
            if (!$this->checkAdminSession($adminSession)) {
                Response::html('', 403)->send();
                throw new TerminateException();
            }
        } else {
            if ($key === '' || $this->shareKeyRepo->findValidByKey($key) === null) {
                Response::html('', 403)->send();
                throw new TerminateException();
            }
        }

        // Envoi du fichier
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile($path);
        throw new TerminateException();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Vérifie qu'un session_id donné correspond à une session admin active.
     */
    private function checkAdminSession(string $sessionId): bool
    {
        // Sauvegarde de la session courante (cas tests / contexte existant)
        $currentId = session_id();

        if ($currentId !== '' && $currentId !== $sessionId) {
            session_write_close();
        }

        session_id($sessionId);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $valid = isset($_SESSION['admin_id']);

        if ($currentId !== '' && $currentId !== $sessionId) {
            session_write_close();
            session_id($currentId);
            session_start();
        }

        return $valid;
    }
}
