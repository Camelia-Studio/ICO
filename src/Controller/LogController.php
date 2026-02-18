<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;

/**
 * Contrôleur des logs administrateurs.
 *
 * Liste des logs avec filtres et pagination.
 * Accès réservé au premier administrateur créé.
 */
class LogController
{
    /** Nombre de logs par page. */
    private const PER_PAGE = 50;

    /** Traduction des types d'actions. */
    private const ACTION_TRANSLATIONS = [
        'ADD_USER'               => 'Ajouter un utilisateur',
        'EDIT_USER'              => 'Modifier un utilisateur',
        'DELETE_USER'            => 'Supprimer un utilisateur',
        'CREATE_FOLDER'          => 'Créer un dossier',
        'EDIT_FOLDER'            => 'Modifier un dossier',
        'DELETE_FOLDER'          => 'Supprimer un dossier',
        'CREATE_PRIVATE_FOLDER'  => 'Créer un dossier privé',
        'EDIT_PRIVATE_FOLDER'    => 'Modifier un dossier privé',
        'DELETE_PRIVATE_FOLDER'  => 'Supprimer un dossier privé',
        'UPLOAD_IMAGES'          => 'Téléverser des images',
        'DELETE_IMAGES'          => 'Supprimer des images',
        'MOVE_IMAGES'            => 'Déplacer des images',
        'UPLOAD_PRIVATE_IMAGES'  => 'Téléverser des images privées',
        'DELETE_PRIVATE_IMAGES'  => 'Supprimer des images privées',
        'GENERATE_SHARE_LINK'    => 'Générer un lien de partage',
        'CLEAN_EXPIRED_KEYS'     => 'Nettoyer les clés expirées',
        'DELETE_SHARE_KEY'       => 'Supprimer une clé de partage',
        'UPDATE_SETTINGS'        => 'Modifier les paramètres',
    ];

    public function __construct(
        private readonly Config          $config,
        private readonly AuthService     $authService,
        private readonly LogRepository   $logRepo,
        private readonly AdminRepository $adminRepo,
        private readonly ViewRenderer    $view,
    ) {}

    // -------------------------------------------------------------------------
    // Action principale
    // -------------------------------------------------------------------------

    /**
     * Rend la vue des logs.
     * Redirige si l'accès est refusé (non connecté ou non premier admin).
     */
    public function index(Request $request): void
    {
        if (!$this->authService->isLoggedIn()) {
            Response::redirect('admin.php?action=login')->send();
            exit;
        }

        // Seul le premier admin peut consulter les logs
        $adminId      = $this->authService->getLoggedInAdminId();
        $firstAdminId = $this->adminRepo->findFirstAdminId();

        if ($adminId !== $firstAdminId) {
            $_SESSION['error_message'] = 'Accès non autorisé. Seul le premier administrateur peut consulter les logs.';
            Response::redirect('admin.php')->send();
            exit;
        }

        // Purge automatique des logs > 1 mois
        $this->logRepo->deleteOlderThan('-1 month');

        // Filtres
        $filters = [
            'action_type' => (string) $request->query('action_type', ''),
            'admin_id'    => (int)    $request->query('admin', 0),
            'date_range'  => (string) $request->query('date_range', ''),
        ];

        // Pagination
        $page   = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $total      = $this->logRepo->count($filters);
        $totalPages = $total > 0 ? (int) ceil($total / self::PER_PAGE) : 1;

        $logs = $this->logRepo->findAll($filters, self::PER_PAGE, $offset);

        $this->view->render('pages/logs', [
            'logs'               => $logs,
            'admins'             => $this->adminRepo->findAll(),
            'action_types'       => $this->logRepo->findDistinctActionTypes(),
            'action_translations' => self::ACTION_TRANSLATIONS,
            'filters'            => $filters,
            'page'               => $page,
            'total_pages'        => $totalPages,
            'total'              => $total,
            'site_title'         => $this->config->getSiteTitle(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper (utilisé par la vue)
    // -------------------------------------------------------------------------

    /**
     * Retourne la classe CSS CSS correspondant au type d'action pour le coloriage du tableau.
     */
    public static function getActionClass(string $actionType): string
    {
        $lower = strtolower($actionType);

        if (str_contains($lower, 'create') || str_contains($lower, 'add')
            || str_contains($lower, 'upload') || str_contains($lower, 'generate')
        ) {
            return 'log-action-create';
        }

        if (str_contains($lower, 'edit') || str_contains($lower, 'update')
            || str_contains($lower, 'modify') || str_contains($lower, 'move')
        ) {
            return 'log-action-edit';
        }

        if (str_contains($lower, 'delete') || str_contains($lower, 'remove')
            || str_contains($lower, 'clean')
        ) {
            return 'log-action-delete';
        }

        return '';
    }
}
