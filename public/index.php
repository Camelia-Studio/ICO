<?php

declare(strict_types=1);

/**
 * Front controller — Phase 4.
 *
 * Ce fichier est le point d'entrée unique une fois que les règles
 * .htaccess racine seront activées (Phase 4 → activation en production).
 *
 * Pour l'instant (Phase 4) il est fonctionnel mais non encore activé
 * en production (les règles .htaccess racine restent commentées).
 *
 * Fonctionnement :
 *   1. Autoload Composer
 *   2. Construction de la Request depuis les superglobales
 *   3. Résolution via le Router → chemin absolu du fichier PHP racine
 *   4. Dispatch par require (transparence totale, l'existant tourne tel quel)
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
