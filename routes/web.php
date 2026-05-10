<?php

declare(strict_types=1);

use ICO\Controller\AdminController;
use ICO\Controller\AlbumController;
use ICO\Controller\GalleryController;
use ICO\Controller\HomeController;
use ICO\Controller\ImageController;
use ICO\Controller\InfoPageController;
use ICO\Controller\LogController;
use ICO\Controller\PublicPageController;
use ICO\Controller\SettingsController;
use ICO\Controller\ShareController;
use ICO\Controller\ShareKeyController;
use ICO\Controller\TreeController;
use ICO\Controller\TreeImageController;
use ICO\Controller\UserController;
use ICO\Http\Router;

/**
 * Table des routes de l'application.
 *
 * Chaque route est associée à un callable [ControllerClass::class, 'method'].
 * Le front controller résout la route, récupère le controller depuis le container
 * et invoque la méthode.
 */
return static function (Router $router): void {
    // --- Public ------------------------------------------------------------------
    $router->get('/',                       [HomeController::class,     'index']);
    $router->get('/index.php',              [HomeController::class,     'index']);
    $router->get('/albums.php',             [AlbumController::class,    'index']);
    $router->get('/galeries.php',           [GalleryController::class,  'show']);
    $router->get('/galeries-privees.php',   [GalleryController::class,  'showPrivate']);
    $router->get('/images.php',             [ImageController::class,    'serve']);
    $router->get('/partage.php',            [ShareController::class,    'show']);
    $router->get('/page.php',               [PublicPageController::class, 'show']);

    // --- Admin — arbre -----------------------------------------------------------
    $router->get('/arbre.php',              [TreeController::class,      'handlePublic']);
    $router->post('/arbre.php',             [TreeController::class,      'handlePublic']);
    $router->get('/arbre-prive.php',        [TreeController::class,      'handlePrivate']);
    $router->post('/arbre-prive.php',       [TreeController::class,      'handlePrivate']);
    $router->get('/arbre-img.php',          [TreeImageController::class, 'handlePublic']);
    $router->post('/arbre-img.php',         [TreeImageController::class, 'handlePublic']);
    $router->get('/arbre-img-prive.php',    [TreeImageController::class, 'handlePrivate']);
    $router->post('/arbre-img-prive.php',   [TreeImageController::class, 'handlePrivate']);

    // --- Admin — gestion ---------------------------------------------------------
    $router->get('/admin.php',              [AdminController::class,     'handle']);
    $router->post('/admin.php',             [AdminController::class,     'handle']);
    $router->get('/utilisateurs.php',       [UserController::class,      'handle']);
    $router->post('/utilisateurs.php',      [UserController::class,      'handle']);
    $router->get('/clefs.php',              [ShareKeyController::class,  'index']);
    $router->post('/clefs.php',             [ShareKeyController::class,  'index']);
    $router->get('/logs.php',               [LogController::class,       'index']);
    $router->get('/personnalisation.php',   [SettingsController::class,  'index']);
    $router->post('/personnalisation.php',  [SettingsController::class,  'index']);
    $router->get('/pages-info.php',         [InfoPageController::class,  'handle']);
    $router->post('/pages-info.php',        [InfoPageController::class,  'handle']);
};
