<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude(['View/pages', 'View/layout', 'View/partials'])
    ->name('*.php');

return (new Config())
    ->setRules([
        '@PSR12'            => true,
        '@PHP83Migration'   => true,
        'strict_param'      => true,
        'array_syntax'      => ['syntax' => 'short'],
        'ordered_imports'   => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
