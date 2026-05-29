<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;

/**
 * Contrôleur de la page de personnalisation du site.
 *
 * Accès réservé aux admins connectés.
 * En POST : valide et sauvegarde config.txt, log l'action.
 * En GET  : affiche le formulaire pré-rempli.
 */
class SettingsController
{
    private const int MAX_FAVICON_SIZE = 1_048_576; // 1 Mo

    public function __construct(
        private readonly AuthService   $authService,
        private readonly LogRepository $logRepo,
        private readonly string        $configFile,
        private readonly string        $projectRoot,
        private readonly ViewRenderer  $view,
    ) {
    }

    // -------------------------------------------------------------------------
    // Action principale (GET + POST)
    // -------------------------------------------------------------------------

    /**
     * Gère l'affichage et la soumission du formulaire de personnalisation.
     * Redirige vers login si l'admin n'est pas connecté.
     */
    public function index(Request $request): void
    {
        if (!$this->authService->isLoggedIn()) {
            Response::redirect('admin.php?action=login')->send();
            throw new TerminateException();
        }

        $successMessage = '';
        $errorMessage   = '';

        if ($request->isPost()) {
            [$successMessage, $errorMessage] = $this->handlePost($request);

            // Après POST réussi on redirige (PRG pattern)
            if ($successMessage !== '') {
                $_SESSION['success_message'] = $successMessage;
                Response::redirect('personnalisation.php')->send();
                throw new TerminateException();
            }
        }

        // Récupération des messages flash de session
        if (isset($_SESSION['success_message'])) {
            $successMessage = $_SESSION['success_message'];
            unset($_SESSION['success_message']);
        }

        if (isset($_SESSION['error_message'])) {
            $errorMessage = $_SESSION['error_message'];
            unset($_SESSION['error_message']);
        }

        // Relecture de la config courante (peut avoir été mise à jour)
        $currentConfig = $this->readCurrentConfig();

        $this->view->render('pages/settings', [
            'site_title'         => $currentConfig['title'],
            'site_description'   => $currentConfig['description'],
            'project_path'       => $currentConfig['path'],
            'slideshow_interval' => $currentConfig['slideshow_interval'],
            'has_custom_favicon' => file_exists($this->projectRoot . '/favicon-custom.png'),
            'success_message'    => $successMessage,
            'error_message'      => $errorMessage,
        ]);
    }

    // -------------------------------------------------------------------------
    // Traitement POST
    // -------------------------------------------------------------------------

    /**
     * @return array{0: string, 1: string} [successMessage, errorMessage]
     */
    private function handlePost(Request $request): array
    {
        if ($request->post('action') === 'reset_favicon') {
            return $this->resetFavicon();
        }

        $siteTitle         = trim((string) $request->post('site_title', ''));
        $siteDescription   = trim((string) $request->post('site_description', ''));
        $projectPath       = trim((string) $request->post('project_path', ''));
        $rawInterval       = (int) $request->post('slideshow_interval', '5');
        $slideshowInterval = $rawInterval >= 1 ? $rawInterval : 5;

        if ($siteTitle === '') {
            return ['', 'Le titre du site est requis.'];
        }

        $faviconError = $this->handleFaviconUpload();
        if ($faviconError !== '') {
            return ['', $faviconError];
        }

        $configContent = $siteTitle . "\n" . $siteDescription . "\n" . $projectPath . "\n" . $slideshowInterval;

        if (file_put_contents($this->configFile, $configContent) === false) {
            return ['', 'Erreur lors de la sauvegarde de la configuration.'];
        }

        $adminId = $this->authService->getLoggedInAdminId();
        if ($adminId !== null) {
            $this->logRepo->log(
                $adminId,
                'UPDATE_SETTINGS',
                'Modification des paramètres du site',
            );
        }

        return ['Configuration mise à jour avec succès.', ''];
    }

    /**
     * Supprime favicon-custom.png pour revenir au favicon d'origine.
     *
     * @return array{0: string, 1: string}
     */
    private function resetFavicon(): array
    {
        $customFavicon = $this->projectRoot . '/favicon-custom.png';

        if (file_exists($customFavicon) && !unlink($customFavicon)) {
            return ['', 'Impossible de supprimer le favicon personnalisé.'];
        }

        $adminId = $this->authService->getLoggedInAdminId();
        if ($adminId !== null) {
            $this->logRepo->log($adminId, 'UPDATE_SETTINGS', 'Remise du favicon par défaut');
        }

        return ['Favicon remis par défaut.', ''];
    }

    /**
     * Traite l'upload du favicon si un fichier a été fourni.
     * Ne fait rien si aucun fichier n'est envoyé.
     * Retourne une chaîne d'erreur, ou '' en cas de succès / absence de fichier.
     */
    private function handleFaviconUpload(): string
    {
        $file = $_FILES['favicon'] ?? null;

        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Erreur lors du téléversement du favicon.';
        }

        if ($file['size'] > self::MAX_FAVICON_SIZE) {
            return 'Le favicon ne peut pas dépasser 1 Mo.';
        }

        $imageInfo = @getimagesize((string) $file['tmp_name']);
        if ($imageInfo === false || $imageInfo[2] !== IMAGETYPE_PNG) {
            return 'Le favicon doit être une image PNG.';
        }

        $dest = $this->projectRoot . '/favicon-custom.png';
        if (!$this->moveUploadedFile((string) $file['tmp_name'], $dest)) {
            return 'Impossible de sauvegarder le favicon.';
        }

        return '';
    }

    /**
     * Déplace le fichier uploadé vers sa destination.
     * Séparé pour permettre les tests (surcharge possible).
     */
    protected function moveUploadedFile(string $tmp, string $dest): bool
    {
        return move_uploaded_file($tmp, $dest);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Relit config.txt pour avoir les valeurs fraîches après une mise à jour.
     *
     * @return array{title: string, description: string, path: string, slideshow_interval: int}
     */
    private function readCurrentConfig(): array
    {
        if (!file_exists($this->configFile)) {
            return ['title' => 'ICO', 'description' => '', 'path' => '', 'slideshow_interval' => 5];
        }

        $lines       = explode("\n", (string) file_get_contents($this->configFile));
        $rawInterval = (int) trim($lines[3] ?? '');

        return [
            'title'              => trim($lines[0]),
            'description'        => trim($lines[1] ?? ''),
            'path'               => trim($lines[2] ?? ''),
            'slideshow_interval' => $rawInterval >= 1 ? $rawInterval : 5,
        ];
    }
}
