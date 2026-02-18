<?php

declare(strict_types=1);

use ICO\Http\Router;

/**
 * Table des routes de l'application.
 *
 * Reçoit le Router en paramètre et enregistre toutes les routes publiques
 * et d'administration. Chargé par le front controller (public/index.php).
 */
return static function (Router $router): void {
    // --- Public ------------------------------------------------------------------
    $router->get('/',                       'index.php');
    $router->get('/index.php',              'index.php');
    $router->get('/albums.php',             'albums.php');
    $router->get('/galeries.php',           'galeries.php');
    $router->get('/galeries-privees.php',   'galeries-privees.php');
    $router->get('/images.php',             'images.php');
    $router->get('/partage.php',            'partage.php');

    // --- Admin — arbre -----------------------------------------------------------
    $router->get('/arbre.php',              'arbre.php');
    $router->post('/arbre.php',             'arbre.php');
    $router->get('/arbre-prive.php',        'arbre-prive.php');
    $router->post('/arbre-prive.php',       'arbre-prive.php');
    $router->get('/arbre-img.php',          'arbre-img.php');
    $router->post('/arbre-img.php',         'arbre-img.php');
    $router->get('/arbre-img-prive.php',    'arbre-img-prive.php');
    $router->post('/arbre-img-prive.php',   'arbre-img-prive.php');

    // --- Admin — gestion ---------------------------------------------------------
    $router->get('/admin.php',              'admin.php');
    $router->post('/admin.php',             'admin.php');
    $router->get('/utilisateurs.php',       'utilisateurs.php');
    $router->post('/utilisateurs.php',      'utilisateurs.php');
    $router->get('/clefs.php',              'clefs.php');
    $router->post('/clefs.php',             'clefs.php');
    $router->get('/logs.php',               'logs.php');
    $router->get('/personnalisation.php',   'personnalisation.php');
    $router->post('/personnalisation.php',  'personnalisation.php');
};
