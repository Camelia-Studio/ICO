<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Controller\UserController;
use ICO\Database\Database;
use ICO\Repository\AdminRepository;
use ICO\Repository\LogRepository;
use ICO\Service\AuthService;
use ICO\Service\PasswordValidator;
use ICO\View\ViewRenderer;

$config = Config::fromFile(__DIR__ . '/config.txt', __DIR__ . '/version.txt');
$config->configureSession();
session_start();

$pdo       = Database::getInstance(__DIR__ . '/database.sqlite')->getPdo();
$adminRepo = new AdminRepository($pdo);
$view      = new ViewRenderer(__DIR__ . '/src/View');

(new UserController(
    $config,
    new AuthService($adminRepo),
    $adminRepo,
    new LogRepository($pdo),
    new PasswordValidator(),
    $view,
))->handle();
