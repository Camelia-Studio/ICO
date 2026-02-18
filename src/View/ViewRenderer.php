<?php

declare(strict_types=1);

namespace ICO\View;

/**
 * Moteur de rendu de vues PHP natives.
 *
 * Utilise ob_start / ob_get_clean + extract() pour injecter les données
 * dans la portée locale de chaque fichier de vue.
 *
 * Le layout est géré via deux emplacements :
 *   - renderLayout('layout/header', $data) → ouvre le HTML
 *   - renderLayout('layout/footer', $data) → ferme le HTML
 * Ces deux appels sont faits depuis les fichiers de vue eux-mêmes.
 *
 * Usage dans un controller :
 *   $this->view->render('pages/admin-login', ['error' => null]);
 *
 * Usage dans une vue pages/*.php :
 *   <?php $this->renderLayout('layout/header', ['pageTitle' => 'Connexion']); ?>
 *   ... contenu ...
 *   <?php $this->renderLayout('layout/footer', ['version' => $version]); ?>
 */
class ViewRenderer
{
    public function __construct(
        private readonly string $viewsDir,
    ) {}

    /**
     * Rend une vue en injectant $data comme variables locales.
     *
     * @param string               $view Chemin relatif depuis $viewsDir, sans .php (ex: 'pages/admin-login')
     * @param array<string, mixed> $data Variables à extraire dans la portée de la vue
     */
    public function render(string $view, array $data = []): void
    {
        $file = $this->viewsDir . '/' . $view . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Vue introuvable : {$file}");
        }

        // On passe $this pour que les vues puissent appeler $this->renderLayout(...)
        $renderer = $this;
        extract($data, EXTR_SKIP);

        require $file;
    }

    /**
     * Inclut un fichier de layout ou de partial.
     *
     * @param string               $partial Chemin relatif depuis $viewsDir, sans .php
     * @param array<string, mixed> $data    Variables supplémentaires
     */
    public function renderLayout(string $partial, array $data = []): void
    {
        $file = $this->viewsDir . '/' . $partial . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Partial introuvable : {$file}");
        }

        extract($data, EXTR_SKIP);
        require $file;
    }
}
