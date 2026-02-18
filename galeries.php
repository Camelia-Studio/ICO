<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Controller\GalleryController;
use ICO\Database\Database;
use ICO\Http\Request;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\FileService;
use ICO\View\ViewRenderer;

$config = Config::fromFile(__DIR__ . '/config.txt', __DIR__ . '/version.txt');
$config->configureSession();
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$basePath  = $config->getBasePath();
$baseUrl   = $protocol . $_SERVER['HTTP_HOST'] . ($basePath !== '' ? '/' . $basePath : '');

$pdo  = Database::getInstance(__DIR__ . '/database.sqlite')->getPdo();

$albumService   = new AlbumService(
    __DIR__ . '/liste_albums',
    __DIR__ . '/liste_albums_prives',
    $config->getAllowedExtensions(),
);
$fileService    = new FileService();
$shareKeyRepo   = new ShareKeyRepository($pdo);

$controller = new GalleryController($config, $albumService, $fileService, $shareKeyRepo, __DIR__, $baseUrl);
$request    = Request::fromGlobals();
$data       = $controller->show($request);

if ($data === null) {
    header('Location: index.php');
    exit;
}

$view = new ViewRenderer(__DIR__ . '/src/View');
$view->render('pages/gallery-public', array_merge($data, ['version' => $config->getVersion()]));
