<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Controller\ShareKeyController;
use ICO\Database\Database;
use ICO\Http\Request;
use ICO\Repository\AdminRepository;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\LogRepository;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;

$config = Config::fromFile(__DIR__ . '/config.txt', __DIR__ . '/version.txt');
$config->configureSession();
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$basePath  = $config->getBasePath();
$baseUrl   = $protocol . $_SERVER['HTTP_HOST'] . ($basePath !== '' ? '/' . $basePath : '');

$pdo  = Database::getInstance(__DIR__ . '/database.sqlite')->getPdo();

$adminRepo           = new AdminRepository($pdo);
$logRepo             = new LogRepository($pdo);
$shareKeyRepo        = new ShareKeyRepository($pdo);
$albumIdentifierRepo = new AlbumIdentifierRepository($pdo);
$authService         = new AuthService($adminRepo);
$albumService        = new AlbumService(
    __DIR__ . '/liste_albums',
    __DIR__ . '/liste_albums_prives',
    $config->getAllowedExtensions(),
);

$controller = new ShareKeyController(
    $config,
    $authService,
    $shareKeyRepo,
    $albumIdentifierRepo,
    $albumService,
    $logRepo,
    $baseUrl,
);
$request = Request::fromGlobals();
$data    = $controller->index($request);

if ($data === null) {
    header('Location: admin.php?action=login');
    exit;
}

$view = new ViewRenderer(__DIR__ . '/src/View');
$view->render('pages/share-keys', array_merge($data, ['version' => $config->getVersion()]));
