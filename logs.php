<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Controller\LogController;
use ICO\Database\Database;
use ICO\Http\Request;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\View\ViewRenderer;

$config = Config::fromFile(__DIR__ . '/config.txt', __DIR__ . '/version.txt');
$config->configureSession();
session_start();

$pdo  = Database::getInstance(__DIR__ . '/database.sqlite')->getPdo();

$adminRepo   = new AdminRepository($pdo);
$logRepo     = new LogRepository($pdo);
$authService = new AuthService($adminRepo);

$controller = new LogController($config, $authService, $logRepo, $adminRepo);
$request    = Request::fromGlobals();
$data       = $controller->index($request);

if ($data === null) {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: admin.php?action=login');
        exit;
    }
    header('Location: admin.php');
    exit;
}

$view = new ViewRenderer(__DIR__ . '/src/View');
$view->render('pages/logs', array_merge($data, ['version' => $config->getVersion()]));
