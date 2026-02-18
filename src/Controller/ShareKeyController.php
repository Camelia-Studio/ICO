<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\LogRepository;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;

/**
 * Contrôleur de gestion des clés de partage.
 *
 * Remplace clefs.php (liste, suppression, nettoyage des clés expirées).
 * Accès réservé aux admins connectés.
 */
class ShareKeyController
{
    public function __construct(
        private readonly Config                    $config,
        private readonly AuthService               $authService,
        private readonly ShareKeyRepository        $shareKeyRepo,
        private readonly AlbumIdentifierRepository $albumIdentifierRepo,
        private readonly AlbumService              $albumService,
        private readonly LogRepository             $logRepo,
        private readonly string                    $baseUrl,
    ) {}

    // -------------------------------------------------------------------------
    // Action principale (GET + POST)
    // -------------------------------------------------------------------------

    /**
     * Prépare les données pour la vue de gestion des clés de partage.
     *
     * Retourne null si l'admin n'est pas connecté (= redirection vers login).
     *
     * @return array{
     *   keys: list<array<string, mixed>>,
     *   albums: list<array<string, mixed>>,
     *   filter: string,
     *   album_filter: string,
     *   success_message: string,
     *   error_message: string,
     *   site_title: string,
     *   base_url: string,
     * }|null
     */
    public function index(Request $request): ?array
    {
        if (!$this->authService->isLoggedIn()) {
            return null;
        }

        $successMessage = '';
        $errorMessage   = '';

        if ($request->isPost()) {
            [$successMessage, $errorMessage] = $this->handlePost($request);
        }

        $filter      = (string) $request->query('filter', 'active');
        $albumFilter = (string) $request->query('album', '');

        $keys   = $this->shareKeyRepo->findAll($filter, $albumFilter);
        $albums = $this->albumIdentifierRepo->findAll();

        // Enrichir les clés avec les infos album et l'URL de partage
        $enrichedKeys = [];
        foreach ($keys as $key) {
            $albumInfo = $this->albumService->getAlbumInfo($key['path']);
            $enrichedKeys[] = array_merge($key, [
                'album_title'    => $albumInfo['title'],
                'album_mature'   => $albumInfo['mature_content'],
                'share_url'      => $this->baseUrl . '/galeries-privees.php?key=' . urlencode($key['key_value']),
                'is_expired'     => strtotime($key['expires_at']) <= time(),
            ]);
        }

        return [
            'keys'            => $enrichedKeys,
            'albums'          => $albums,
            'filter'          => $filter,
            'album_filter'    => $albumFilter,
            'success_message' => $successMessage,
            'error_message'   => $errorMessage,
            'site_title'      => $this->config->getSiteTitle(),
            'base_url'        => $this->baseUrl,
        ];
    }

    // -------------------------------------------------------------------------
    // Traitement POST
    // -------------------------------------------------------------------------

    /**
     * @return array{0: string, 1: string} [successMessage, errorMessage]
     */
    private function handlePost(Request $request): array
    {
        $action  = (string) $request->post('action', '');
        $adminId = $this->authService->getLoggedInAdminId();

        return match ($action) {
            'delete_key'    => $this->deleteKey($request, $adminId),
            'clean_expired' => $this->cleanExpired($adminId),
            default         => ['', ''],
        };
    }

    /** @return array{0: string, 1: string} */
    private function deleteKey(Request $request, ?int $adminId): array
    {
        $keyId = (int) $request->post('key_id', 0);

        if ($keyId <= 0) {
            return ['', 'Identifiant de clé invalide.'];
        }

        if (!$this->shareKeyRepo->deleteById($keyId)) {
            return ['', 'Erreur lors de la suppression de la clé.'];
        }

        if ($adminId !== null) {
            $this->logRepo->log($adminId, 'DELETE_SHARE_KEY', "Suppression d'une clé de partage", "ID: {$keyId}");
        }

        return ['Clé supprimée avec succès.', ''];
    }

    /** @return array{0: string, 1: string} */
    private function cleanExpired(?int $adminId): array
    {
        $count = $this->shareKeyRepo->deleteExpired();

        if ($adminId !== null && $count > 0) {
            $this->logRepo->log(
                $adminId,
                'CLEAN_EXPIRED_KEYS',
                "Nettoyage de {$count} clé(s) de partage expirée(s)",
            );
        }

        $msg = $count > 0
            ? "{$count} clé(s) expirée(s) supprimée(s)."
            : 'Aucune clé expirée à supprimer.';

        return [$msg, ''];
    }
}
