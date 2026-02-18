<?php

declare(strict_types=1);

/**
 * Front controller — point d'entrée unique.
 *
 * Fonctionnement :
 *   1. Autoload Composer
 *   2. Construction de la Config et de la Request
 *   3. Session (configureSession + session_start)
 *   4. Construction du container Symfony DI
 *   5. Résolution via le Router → handler [ControllerClass, 'method']
 *   6. Récupération du controller depuis le container + invocation
 *   7. 404 si aucune route ne correspond
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Container;
use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\Router;

// --- Configuration -----------------------------------------------------------

$projectRoot = dirname(__DIR__);

$config = Config::fromFile(
    $projectRoot . '/config.txt',
    $projectRoot . '/version.txt',
);

// --- Session -----------------------------------------------------------------

$config->configureSession();
session_start();

// --- Requête -----------------------------------------------------------------

$request = Request::fromGlobals();

// --- Container ---------------------------------------------------------------

$container = Container::build($projectRoot, $config);

// --- Routeur -----------------------------------------------------------------

$router = new Router(
    projectRoot: $projectRoot,
    basePath:    $config->getBasePath(),
);

$routes = require $projectRoot . '/routes/web.php';
$routes($router);

$handler = $router->resolve($request);

// --- Dispatch ----------------------------------------------------------------

if ($handler === null) {
    Response::html('<h1>404 — Page introuvable</h1>', 404)->send();
    exit;
}

// Handler callable [ControllerClass::class, 'method']
if (is_array($handler)) {
    [$controllerClass, $method] = $handler;
    $controller = $container->get($controllerClass);
    $controller->$method($request);
    exit;
}

// Handler fichier (compatibilité rétrograde — ne devrait plus être utilisé)
if (file_exists($handler)) {
    chdir($projectRoot);
    require $handler;
    exit;
}

Response::html('<h1>404 — Page introuvable</h1>', 404)->send();
