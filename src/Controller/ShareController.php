<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\View\ViewRenderer;

/**
 * Contrôleur de la page de partage d'image.
 *
 * Affiche une image avec ses actions : partager, intégrer, télécharger, recherche SauceNAO.
 * Prépare les données de vue et détecte si l'image est privée pour
 * conditionner l'affichage du bouton "Intégrer".
 */
class ShareController
{
    public function __construct(
        private readonly Config             $config,
        private readonly ShareKeyRepository $shareKeyRepo,
        private readonly ViewRenderer       $view,
    ) {
    }

    // -------------------------------------------------------------------------
    // Action principale
    // -------------------------------------------------------------------------

    /**
     * Rend la vue de partage.
     * Redirige vers index.php si l'URL d'image est absente/invalide.
     */
    public function show(Request $request): void
    {
        $imageUrl = (string) $request->query('image', '');

        if ($imageUrl === '') {
            Response::redirect('index.php')->send();
            throw new TerminateException();
        }

        $isPrivateImage = false;
        $isVideo        = $this->isVideoUrl($imageUrl);
        $filename       = basename((string) parse_url($imageUrl, PHP_URL_PATH));
        $shareOptions   = ['download' => true, 'source' => true, 'share' => true];

        // Image privée (proxy images.php)
        if (str_contains($imageUrl, 'images.php')) {
            parse_str((string) parse_url($imageUrl, PHP_URL_QUERY), $params);
            $path = $params['path'] ?? '';
            $key  = $params['key']  ?? '';

            // Le vrai nom de fichier est dans path=, pas dans le path de l'URL proxy
            if ($path !== '') {
                $filename = basename($path);
            }

            if (str_contains($path, 'liste_albums_prives')) {
                $isPrivateImage = true;
                $shareKeyData   = $key !== '' ? $this->shareKeyRepo->findValidByKey($key) : null;

                // Accès refusé aux non-admins sans clé valide
                if (!isset($_SESSION['admin_id']) && ($key === '' || $shareKeyData === null)) {
                    Response::redirect('index.php')->send();
                    throw new TerminateException();
                }

                // Options de la clé appliquées quelle que soit la session
                if ($shareKeyData !== null) {
                    $decoded = json_decode((string) ($shareKeyData['options'] ?? '{}'), true);
                    if (is_array($decoded)) {
                        $shareOptions = array_merge($shareOptions, $decoded);
                    }
                }
            }
        }

        // Vidéo privée (proxy videos.php)
        if (str_contains($imageUrl, 'videos.php')) {
            parse_str((string) parse_url($imageUrl, PHP_URL_QUERY), $params);
            $path = $params['path'] ?? '';
            $key  = $params['key']  ?? '';

            if ($path !== '') {
                $filename = basename($path);
            }

            if (str_contains($path, 'liste_albums_prives')) {
                $isPrivateImage = true;
                $shareKeyData   = $key !== '' ? $this->shareKeyRepo->findValidByKey($key) : null;

                if (!isset($_SESSION['admin_id']) && ($key === '' || $shareKeyData === null)) {
                    Response::redirect('index.php')->send();
                    throw new TerminateException();
                }

                if ($shareKeyData !== null) {
                    $decoded = json_decode((string) ($shareKeyData['options'] ?? '{}'), true);
                    if (is_array($decoded)) {
                        $shareOptions = array_merge($shareOptions, $decoded);
                    }
                }
            }
        }

        $siteTitle       = $this->config->getSiteTitle();
        $siteDescription = $this->config->getSiteDescription();
        $protocol        = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host            = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl         = $protocol . '://' . $host;
        $absoluteImage   = preg_match('/^https?:\/\//', $imageUrl)
            ? $imageUrl
            : $baseUrl . '/' . ltrim($imageUrl, '/');
        $absolutePage    = $baseUrl . ($_SERVER['REQUEST_URI'] ?? '');

        $this->view->render('pages/share', [
            'image_url'        => $imageUrl,
            'filename'         => $filename,
            'is_private_image' => $isPrivateImage,
            'is_video'         => $isVideo,
            'allow_download'   => (bool) ($shareOptions['download'] ?? true),
            'allow_source'     => (bool) ($shareOptions['source'] ?? true),
            'allow_share'      => (bool) ($shareOptions['share'] ?? true),
            'site_title'       => $siteTitle,
            'site_description' => $siteDescription,
            'absolute_image'   => $absoluteImage,
            'absolute_page'    => $absolutePage,
            'og_title'         => $filename . ' — ' . $siteTitle,
            'og_description'   => $siteDescription !== '' ? $siteDescription : $siteTitle,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function isVideoUrl(string $url): bool
    {
        if (str_contains($url, 'videos.php')) {
            return true;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'webm'], true);
    }
}
