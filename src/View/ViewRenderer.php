<?php

declare(strict_types=1);

namespace ICO\View;

use ICO\Controller\LogController;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Moteur de rendu Twig.
 *
 * Interface publique identique à l'ancien ViewRenderer PHP natif —
 * aucun changement requis dans les contrôleurs ni dans Container.php.
 *
 * Usage dans un contrôleur :
 *   $this->view->render('pages/admin-login', ['error' => null]);
 */
class ViewRenderer
{
    private Environment $twig;

    public function __construct(
        private readonly string $viewsDir,
    ) {
        $loader = new FilesystemLoader($this->viewsDir);

        $this->twig = new Environment($loader, [
            'cache'            => dirname($this->viewsDir, 2) . '/var/twig-cache',
            'auto_reload'      => true,
            'autoescape'       => 'html',
            'strict_variables' => true,
        ]);

        $this->twig->addFilter(new TwigFilter(
            'log_action_class',
            static fn(string $type): string => LogController::getActionClass($type),
        ));

        $this->twig->addFilter(new TwigFilter(
            'format_date',
            static fn(string $date, string $fmt = 'd/m/Y'): string => date($fmt, (int) strtotime($date)),
        ));

        $this->twig->addFunction(new TwigFunction(
            'flash_messages',
            static function (): array {
                $msgs = [];
                foreach (['success_message' => 'success', 'error_message' => 'error'] as $key => $type) {
                    if (isset($_SESSION[$key])) {
                        $msgs[] = ['type' => $type, 'text' => $_SESSION[$key]];
                        unset($_SESSION[$key]);
                    }
                }
                return $msgs;
            },
        ));
    }

    /**
     * Déclare une variable globale disponible dans toutes les vues.
     */
    public function addGlobal(string $key, mixed $value): void
    {
        $this->twig->addGlobal($key, $value);
    }

    /**
     * Rend une vue page complète.
     *
     * @param string               $view Chemin relatif depuis $viewsDir, sans extension (ex: 'pages/admin-login')
     * @param array<string, mixed> $data Variables passées à la vue
     */
    public function render(string $view, array $data = []): void
    {
        echo $this->twig->render($view . '.html.twig', $data);
    }

    /**
     * Conservé pour compatibilité — non appelé depuis les vues Twig
     * (les partials sont inclus via {% include %}).
     *
     * @param string               $partial Chemin relatif depuis $viewsDir, sans extension
     * @param array<string, mixed> $data    Variables supplémentaires
     */
    public function renderLayout(string $partial, array $data = []): void
    {
        echo $this->twig->render($partial . '.html.twig', $data);
    }
}
