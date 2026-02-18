<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Controller\ShareController;
use ICO\Database\Database;
use ICO\Http\Request;
use ICO\Repository\ShareKeyRepository;
use ICO\View\ViewRenderer;

$config = Config::fromFile(__DIR__ . '/config.txt', __DIR__ . '/version.txt');
$config->configureSession();
session_start();

$pdo  = Database::getInstance(__DIR__ . '/database.sqlite')->getPdo();

$shareKeyRepo = new ShareKeyRepository($pdo);

$controller = new ShareController($config, $shareKeyRepo);
$request    = Request::fromGlobals();
$data       = $controller->show($request);

if ($data === null) {
    header('Location: index.php');
    exit;
}

$view = new ViewRenderer(__DIR__ . '/src/View');
$view->render('pages/share', array_merge($data, ['version' => $config->getVersion()]));
