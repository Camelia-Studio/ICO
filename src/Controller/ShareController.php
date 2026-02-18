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

        // Image privée (proxy images.php)
        if (str_contains($imageUrl, 'images.php')) {
            parse_str((string) parse_url($imageUrl, PHP_URL_QUERY), $params);
            $path = $params['path'] ?? '';
            $key  = $params['key']  ?? '';

            if (str_contains($path, 'liste_albums_prives')) {
                $isPrivateImage = true;

                if (!isset($_SESSION['admin_id'])) {
                    // Vérification de la clé de partage
                    if ($key === '' || $this->shareKeyRepo->findValidByKey($key) === null) {
                        Response::redirect('index.php')->send();
                        throw new TerminateException();
                    }
                } else {
                    // Admin : on substitue la clé par la session admin dans l'URL
                    $imageUrl = preg_replace('/&key=[^&]*/', '', $imageUrl)
                        . '&admin_session=' . session_id();
                }
            }
        }

        $filename = basename((string) parse_url($imageUrl, PHP_URL_PATH));

        $this->view->render('pages/share', [
            'image_url'        => $imageUrl,
            'filename'         => $filename,
            'is_private_image' => $isPrivateImage,
            'site_title'       => $this->config->getSiteTitle(),
        ]);
    }
}
