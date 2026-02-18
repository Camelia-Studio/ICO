<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;

/**
 * Contrôleur de la page de personnalisation du site.
 *
 * Accès réservé aux admins connectés.
 * En POST : valide et sauvegarde config.txt, log l'action.
 * En GET  : affiche le formulaire pré-rempli.
 */
class SettingsController
{
    public function __construct(
        private readonly Config         $config,
        private readonly AuthService    $authService,
        private readonly LogRepository  $logRepo,
        private readonly string         $configFile,
    ) {}

    // -------------------------------------------------------------------------
    // Action principale (GET + POST)
    // -------------------------------------------------------------------------

    /**
     * Gère l'affichage et la soumission du formulaire de personnalisation.
     *
     * Retourne null si l'admin n'est pas connecté (= redirection vers login).
     *
     * @return array{
     *   site_title: string,
     *   site_description: string,
     *   project_path: string,
     *   success_message: string,
     *   error_message: string,
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

            // Après POST réussi on redirige (PRG pattern)
            if ($successMessage !== '') {
                $_SESSION['success_message'] = $successMessage;
                Response::redirect('personnalisation.php')->send();
                exit;
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

        return [
            'site_title'      => $currentConfig['title'],
            'site_description' => $currentConfig['description'],
            'project_path'    => $currentConfig['path'],
            'success_message' => $successMessage,
            'error_message'   => $errorMessage,
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
        $siteTitle       = trim((string) $request->post('site_title', ''));
        $siteDescription = trim((string) $request->post('site_description', ''));
        $projectPath     = trim((string) $request->post('project_path', ''));

        if ($siteTitle === '') {
            return ['', 'Le titre du site est requis.'];
        }

        $configContent = $siteTitle . "\n" . $siteDescription . "\n" . $projectPath;

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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Relit config.txt pour avoir les valeurs fraîches après une mise à jour.
     *
     * @return array{title: string, description: string, path: string}
     */
    private function readCurrentConfig(): array
    {
        if (!file_exists($this->configFile)) {
            return ['title' => 'ICO', 'description' => '', 'path' => ''];
        }

        $lines = explode("\n", (string) file_get_contents($this->configFile));

        return [
            'title'       => trim($lines[0] ?? 'ICO'),
            'description' => trim($lines[1] ?? ''),
            'path'        => trim($lines[2] ?? ''),
        ];
    }
}
