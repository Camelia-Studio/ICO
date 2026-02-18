<?php

declare(strict_types=1);

/**
 * Front controller — point d'entrée unique.
 *
 * Fonctionnement :
 *   1. Autoload Composer
 *   2. Construction de la Request depuis les superglobales
 *   3. Résolution via le Router → chemin absolu du fichier PHP racine
 *   4. Dispatch par require
 *   5. 404 si aucune route ne correspond
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\Router;

// --- Configuration -----------------------------------------------------------

$config = Config::fromFile(
    dirname(__DIR__) . '/config.txt',
    dirname(__DIR__) . '/version.txt',
);

// --- Requête -----------------------------------------------------------------

$request = Request::fromGlobals();

// --- Routeur -----------------------------------------------------------------

$router = new Router(
    projectRoot: dirname(__DIR__),
    basePath:    $config->getBasePath(),
);

$routes = require dirname(__DIR__) . '/routes/web.php';
$routes($router);

$handler = $router->resolve($request);

// --- Dispatch ----------------------------------------------------------------

if ($handler === null || !file_exists($handler)) {
    Response::html('<h1>404 — Page introuvable</h1>', 404)->send();
    exit;
}

// Les fichiers racine utilisent des chemins relatifs depuis la racine du projet.
// On s'assure que le CWD est bien la racine.
chdir(dirname(__DIR__));

require $handler;
