<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Controller\ImageController;
use ICO\Database\Database;
use ICO\Http\Request;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;

$config = Config::fromFile(__DIR__ . '/config.txt', __DIR__ . '/version.txt');
$config->configureSession();
session_start();

$pdo  = Database::getInstance(__DIR__ . '/database.sqlite')->getPdo();

$albumService = new AlbumService(
    __DIR__ . '/liste_albums',
    __DIR__ . '/liste_albums_prives',
    $config->getAllowedExtensions(),
);
$shareKeyRepo = new ShareKeyRepository($pdo);

$controller = new ImageController($albumService, $shareKeyRepo);
$request    = Request::fromGlobals();
$controller->serve($request);
