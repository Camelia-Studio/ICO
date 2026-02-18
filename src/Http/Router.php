<?php

declare(strict_types=1);

namespace ICO\Http;

/**
 * Routeur : résout une URI entrante vers le chemin absolu du fichier PHP racine.
 *
 * Garantit la transparence totale des URLs (les `.php` restent identiques).
 * Le dispatch effectif (require du fichier) est fait par le front controller.
 * Les routes sont enregistrées via routes/web.php au démarrage.
 *
 * Exemple :
 *   $router = new Router('/var/www/ico', 'mon-ico');
 *   $router->get('/albums.php', 'albums.php');
 *   $handler = $router->resolve(new Request('GET', '/mon-ico/albums.php'));
 *   // $handler === '/var/www/ico/albums.php'
 */
class Router
{
    /**
     * Table de routage : chemin URI (sans base path ni query string) → fichier PHP relatif.
     * La clé est normalisée : commence par "/" et ne contient pas le sous-dossier.
     *
     * @var array<string, string>
     */
    private array $routes = [];

    /**
     * @param string $projectRoot Chemin absolu vers la racine du projet (sans slash final)
     * @param string $basePath    Sous-dossier d'installation, ex "mon-ico" (peut être vide)
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $basePath = '',
    ) {}

    // -------------------------------------------------------------------------
    // Enregistrement des routes
    // -------------------------------------------------------------------------

    /**
     * Enregistre une route (toute méthode HTTP).
     *
     * @param string $path     Chemin URI normalisé, ex "/albums.php"
     * @param string $handler  Chemin relatif du fichier PHP racine, ex "albums.php"
     */
    public function add(string $path, string $handler): void
    {
        $this->routes['/' . ltrim($path, '/')] = $handler;
    }

    /**
     * Enregistre une route GET.
     *
     * @param string $path     Chemin URI normalisé, ex "/albums.php"
     * @param string $handler  Chemin relatif du fichier PHP racine, ex "albums.php"
     */
    public function get(string $path, string $handler): void
    {
        $this->add($path, $handler);
    }

    /**
     * Enregistre une route POST.
     *
     * @param string $path     Chemin URI normalisé, ex "/admin.php"
     * @param string $handler  Chemin relatif du fichier PHP racine, ex "admin.php"
     */
    public function post(string $path, string $handler): void
    {
        $this->add($path, $handler);
    }

    // -------------------------------------------------------------------------
    // Résolution
    // -------------------------------------------------------------------------

    /**
     * Résout une requête vers le chemin absolu du fichier PHP à exécuter.
     *
     * Retire le sous-dossier (basePath) du début de l'URI avant la résolution.
     * Retourne null si aucune route ne correspond.
     */
    public function resolve(Request $request): ?string
    {
        $uri = $this->stripBasePath($request->getUri());

        // Correspondance exacte
        if (isset($this->routes[$uri])) {
            return $this->projectRoot . '/' . $this->routes[$uri];
        }

        return null;
    }

    /**
     * Retourne la liste des routes enregistrées (chemin → handler relatif).
     *
     * @return array<string, string>
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
