<?php

declare(strict_types=1);

namespace ICO;

use ICO\Config\Config;
use ICO\Controller\AdminController;
use ICO\Controller\AlbumController;
use ICO\Controller\GalleryController;
use ICO\Controller\HomeController;
use ICO\Controller\ImageController;
use ICO\Controller\LogController;
use ICO\Controller\SettingsController;
use ICO\Controller\ShareController;
use ICO\Controller\ShareKeyController;
use ICO\Controller\TreeController;
use ICO\Controller\TreeImageController;
use ICO\Controller\UserController;
use ICO\Database\Database;
use ICO\Repository\AdminRepository;
use ICO\Repository\AlbumIdentifierRepository;
use ICO\Repository\LogRepository;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\Service\FileService;
use ICO\Service\PasswordValidator;
use ICO\Service\UpdateService;
use ICO\View\ViewRenderer;
use PDO;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Configure et construit le container de services Symfony DI.
 *
 * Toutes les dépendances de l'application sont déclarées ici.
 * Usage :
 *   $container = Container::build($projectRoot, $config);
 *   $controller = $container->get(AdminController::class);
 */
final class Container
{
    /**
     * Construit et compile le ContainerBuilder avec tous les services.
     *
     * @param string $projectRoot Chemin absolu vers la racine du projet (sans slash final)
     * @param Config $config      Instance Config déjà construite
     */
    public static function build(string $projectRoot, Config $config): ContainerBuilder
    {
        $container = new ContainerBuilder();

        // -------------------------------------------------------------------------
        // Paramètres scalaires
        // -------------------------------------------------------------------------

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = $config->getBasePath();
        $baseUrl  = $protocol . $host . ($basePath !== '' ? '/' . $basePath : '');

        $container->setParameter('project_root', $projectRoot);
        $container->setParameter('base_url', $baseUrl);
        $container->setParameter('config_file', $projectRoot . '/config.txt');
        $container->setParameter('albums_root', $projectRoot . '/liste_albums');
        $container->setParameter('private_root', $projectRoot . '/liste_albums_prives');
        $container->setParameter('allowed_exts', $config->getAllowedExtensions());
        $container->setParameter('db_path', $projectRoot . '/database.sqlite');
        $container->setParameter('views_dir', $projectRoot . '/src/View');
        $container->setParameter('current_version', $config->getVersion());

        // -------------------------------------------------------------------------
        // Infrastructure
        // -------------------------------------------------------------------------

        $container->register(Config::class)
            ->setSynthetic(true);
        $container->set(Config::class, $config);

        $container->register(PDO::class)
            ->setSynthetic(true);
        $container->set(PDO::class, Database::getInstance($projectRoot . '/database.sqlite')->getPdo());

        $container->register(ViewRenderer::class)
            ->addArgument('%views_dir%');

        // -------------------------------------------------------------------------
        // Repositories
        // -------------------------------------------------------------------------

        $container->register(AdminRepository::class)
            ->addArgument(new Reference(PDO::class));

        $container->register(AlbumIdentifierRepository::class)
            ->addArgument(new Reference(PDO::class));

        $container->register(ShareKeyRepository::class)
            ->addArgument(new Reference(PDO::class));

        $container->register(LogRepository::class)
            ->addArgument(new Reference(PDO::class));

        // -------------------------------------------------------------------------
        // Services
        // -------------------------------------------------------------------------

        $container->register(AuthService::class)
            ->addArgument(new Reference(AdminRepository::class));

        $container->register(AlbumService::class)
            ->addArgument('%albums_root%')
            ->addArgument('%private_root%')
            ->addArgument('%allowed_exts%');

        $container->register(FileService::class);

        $container->register(PasswordValidator::class);

        $container->register(UpdateService::class)
            ->addArgument('%current_version%');

        // -------------------------------------------------------------------------
        // Controllers — public car récupérés via $container->get() dans index.php
        // -------------------------------------------------------------------------

        $container->register(AdminController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(AuthService::class))
            ->addArgument(new Reference(AdminRepository::class))
            ->addArgument(new Reference(PasswordValidator::class))
            ->addArgument(new Reference(UpdateService::class))
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(UserController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(AuthService::class))
            ->addArgument(new Reference(AdminRepository::class))
            ->addArgument(new Reference(LogRepository::class))
            ->addArgument(new Reference(PasswordValidator::class))
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(TreeController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(AuthService::class))
            ->addArgument(new Reference(AlbumService::class))
            ->addArgument(new Reference(FileService::class))
            ->addArgument(new Reference(LogRepository::class))
            ->addArgument(new Reference(AlbumIdentifierRepository::class))
            ->addArgument(new Reference(ShareKeyRepository::class))
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(TreeImageController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(AuthService::class))
            ->addArgument(new Reference(AlbumService::class))
            ->addArgument(new Reference(LogRepository::class))
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(HomeController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument('%project_root%')
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(AlbumController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(AlbumService::class))
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(GalleryController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(AlbumService::class))
            ->addArgument(new Reference(FileService::class))
            ->addArgument(new Reference(ShareKeyRepository::class))
            ->addArgument('%project_root%')
            ->addArgument('%base_url%')
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(ImageController::class)
            ->setPublic(true)
            ->addArgument(new Reference(AlbumService::class))
            ->addArgument(new Reference(ShareKeyRepository::class));

        $container->register(SettingsController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(AuthService::class))
            ->addArgument(new Reference(LogRepository::class))
            ->addArgument('%config_file%')
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(ShareKeyController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(AuthService::class))
            ->addArgument(new Reference(ShareKeyRepository::class))
            ->addArgument(new Reference(AlbumIdentifierRepository::class))
            ->addArgument(new Reference(AlbumService::class))
            ->addArgument(new Reference(LogRepository::class))
            ->addArgument('%base_url%')
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(LogController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(AuthService::class))
            ->addArgument(new Reference(LogRepository::class))
            ->addArgument(new Reference(AdminRepository::class))
            ->addArgument(new Reference(ViewRenderer::class));

        $container->register(ShareController::class)
            ->setPublic(true)
            ->addArgument(new Reference(Config::class))
            ->addArgument(new Reference(ShareKeyRepository::class))
            ->addArgument(new Reference(ViewRenderer::class));

        $container->compile();

        return $container;
    }
}
