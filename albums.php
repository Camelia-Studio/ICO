<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Controller\AlbumController;
use ICO\Http\Request;
use ICO\Service\AlbumService;
use ICO\View\ViewRenderer;

$config = Config::fromFile(__DIR__ . '/config.txt', __DIR__ . '/version.txt');
$config->configureSession();
session_start();

$albumService = new AlbumService(
    __DIR__ . '/liste_albums',
    __DIR__ . '/liste_albums_prives',
    $config->getAllowedExtensions(),
);

$controller = new AlbumController($config, $albumService);
$request    = Request::fromGlobals();
$data       = $controller->index($request);

if ($data === null) {
    header('Location: index.php');
    exit;
}

$view = new ViewRenderer(__DIR__ . '/src/View');
$view->render('pages/albums', array_merge($data, ['version' => $config->getVersion()]));
