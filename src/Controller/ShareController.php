<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Repository\ShareKeyRepository;

/**
 * Contrôleur de la page de partage d'image.
 *
 * Remplace partage.php (affichage d'une image avec actions : partager,
 * intégrer, télécharger, recherche SauceNAO).
 *
 * Prépare les données de vue et détecte si l'image est privée pour
 * conditionner l'affichage du bouton "Intégrer".
 */
class ShareController
{
    public function __construct(
        private readonly Config             $config,
        private readonly ShareKeyRepository $shareKeyRepo,
    ) {}

    // -------------------------------------------------------------------------
    // Action principale
    // -------------------------------------------------------------------------

    /**
     * Prépare les données pour la vue de partage.
     *
     * Retourne null si l'URL d'image est absente/invalide (= redirection vers index).
     *
     * @return array{
     *   image_url: string,
     *   filename: string,
     *   is_private_image: bool,
     *   site_title: string,
     * }|null
     */
    public function show(Request $request): ?array
    {
        $imageUrl = (string) $request->query('image', '');

        if ($imageUrl === '') {
            return null;
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
                        return null; // pas d'accès → redirection
                    }
                } else {
                    // Admin : on substitue la clé par la session admin dans l'URL
                    $imageUrl = preg_replace('/&key=[^&]*/', '', $imageUrl)
                        . '&admin_session=' . session_id();
                }
            }
        }

        $filename = basename((string) parse_url($imageUrl, PHP_URL_PATH));

        return [
            'image_url'        => $imageUrl,
            'filename'         => $filename,
            'is_private_image' => $isPrivateImage,
            'site_title'       => $this->config->getSiteTitle(),
        ];
    }
}
