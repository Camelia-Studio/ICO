<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use ICO\Config\Config;
use ICO\Controller\HomeController;
use ICO\Http\Request;
use ICO\View\ViewRenderer;

$config = Config::fromFile(__DIR__ . '/config.txt', __DIR__ . '/version.txt');

$controller = new HomeController($config, __DIR__);
$request    = Request::fromGlobals();
$data       = $controller->index($request);

$view = new ViewRenderer(__DIR__ . '/src/View');
$view->render('pages/home', array_merge($data, ['version' => $config->getVersion()]));
