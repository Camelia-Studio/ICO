<?php

declare(strict_types=1);

namespace ICO\Http;

/**
 * Routeur : résout une URI entrante vers un handler (fichier PHP ou callable controller).
 *
 * Garantit la transparence totale des URLs (les `.php` restent identiques).
 * Le dispatch effectif est fait par le front controller.
 * Les routes sont enregistrées via routes/web.php au démarrage.
 *
 * Exemple (callable) :
 *   $router = new Router('/var/www/ico', 'mon-ico');
 *   $router->get('/albums.php', [AlbumController::class, 'index']);
 *   $handler = $router->resolve(new Request('GET', '/mon-ico/albums.php'));
 *   // $handler === [AlbumController::class, 'index']
 *
 * Exemple (fichier) :
 *   $router->add('/custom.php', 'custom.php');
 *   $handler = $router->resolve(new Request('GET', '/custom.php'));
 *   // $handler === '/var/www/ico/custom.php'
 */
class Router
{
    /**
     * Table de routage : chemin URI (sans base path ni query string) → handler.
     * Le handler est soit un chemin de fichier PHP relatif (string), soit un callable
     * sous forme de tableau [ClassName::class, 'method'].
     * La clé est normalisée : commence par "/" et ne contient pas le sous-dossier.
     *
     * @var array<string, string|array{0: class-string, 1: string}>
     */
    private array $routes = [];

    /**
     * @param string $projectRoot Chemin absolu vers la racine du projet (sans slash final)
     * @param string $basePath    Sous-dossier d'installation, ex "mon-ico" (peut être vide)
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $basePath = '',
    ) {
    }

    // -------------------------------------------------------------------------
    // Enregistrement des routes
    // -------------------------------------------------------------------------

    /**
     * Enregistre une route (toute méthode HTTP).
     *
     * @param string                               $path     Chemin URI normalisé, ex "/albums.php"
     * @param string|array{0: class-string, 1: string} $handler  Fichier PHP relatif ou [ControllerClass::class, 'method']
     */
    public function add(string $path, string|array $handler): void
    {
        $this->routes['/' . ltrim($path, '/')] = $handler;
    }

    /**
     * Enregistre une route GET.
     *
     * @param string                               $path     Chemin URI normalisé, ex "/albums.php"
     * @param string|array{0: class-string, 1: string} $handler  Fichier PHP relatif ou [ControllerClass::class, 'method']
     */
    public function get(string $path, string|array $handler): void
    {
        $this->add($path, $handler);
    }

    /**
     * Enregistre une route POST.
     *
     * @param string                               $path     Chemin URI normalisé, ex "/admin.php"
     * @param string|array{0: class-string, 1: string} $handler  Fichier PHP relatif ou [ControllerClass::class, 'method']
     */
    public function post(string $path, string|array $handler): void
    {
        $this->add($path, $handler);
    }

    // -------------------------------------------------------------------------
    // Résolution
    // -------------------------------------------------------------------------

    /**
     * Résout une requête vers son handler.
     *
     * - Si le handler enregistré est un tableau [ClassName::class, 'method'], le retourne tel quel.
     * - Si le handler est une string (fichier relatif), retourne le chemin absolu.
     * - Retourne null si aucune route ne correspond.
     *
     * @return string|array{0: class-string, 1: string}|null
     */
    public function resolve(Request $request): string|array|null
    {
        $uri = $this->stripBasePath($request->getUri());

        // Correspondance exacte
        if (isset($this->routes[$uri])) {
            $handler = $this->routes[$uri];

            if (is_array($handler)) {
                return $handler;
            }

            return $this->projectRoot . '/' . $handler;
        }

        return null;
    }

    /**
     * Retourne la liste des routes enregistrées (chemin → handler).
     *
     * @return array<string, string|array{0: class-string, 1: string}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Retire le préfixe basePath de l'URI.
     * Ex : basePath="mon-ico", uri="/mon-ico/albums.php" → "/albums.php"
     */
    private function stripBasePath(string $uri): string
    {
        if ($this->basePath === '') {
            return $uri;
        }

        $prefix = '/' . ltrim($this->basePath, '/');

        if (str_starts_with($uri, $prefix . '/')) {
            return substr($uri, strlen($prefix));
        }

        // URI == exactement le basePath sans slash final → racine
        if ($uri === $prefix) {
            return '/';
        }

        return $uri;
    }
}
