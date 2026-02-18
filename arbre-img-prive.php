<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Controller\TreeImageController;
use ICO\Database\Database;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;

$config = Config::fromFile(__DIR__ . '/config.txt', __DIR__ . '/version.txt');
$config->configureSession();
session_start();

$pdo  = Database::getInstance(__DIR__ . '/database.sqlite')->getPdo();
$view = new ViewRenderer(__DIR__ . '/src/View');

(new TreeImageController(
    $config,
    new AuthService(new AdminRepository($pdo)),
    new AlbumService(__DIR__ . '/liste_albums', __DIR__ . '/liste_albums_prives', $config->getAllowedExtensions()),
    new LogRepository($pdo),
    $view,
))->handlePrivate();
