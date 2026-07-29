<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Controller;

use ICO\Config\Config;
use ICO\Controller\SocialLinkController;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Repository\LogRepository;
use ICO\Repository\SocialLinkRepository;
use ICO\Service\AuthService;
use ICO\Tests\Support\DatabaseTestTrait;
use ICO\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class SocialLinkControllerTest extends TestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    public function testReorderPersistsNewOrder(): void
    {
        $repo = new SocialLinkRepository($this->pdo);
        $idA  = $repo->create('A', 'https://a.example', 0, true);
        $idB  = $repo->create('B', 'https://b.example', 1, true);

        $controller = $this->makeController($repo);
        $request    = new Request('POST', '/liens-sociaux.php', ['action' => 'reorder'], [
            'order' => [(string) $idB, (string) $idA],
        ]);

        $this->expectException(TerminateException::class);

        try {
            $controller->handle($request);
        } finally {
            $labels = array_column($repo->findAll(), 'label');
            $this->assertSame(['B', 'A'], $labels);
        }
    }

    public function testSaveNewLinkAppendsAtEndWithoutDisplayOrderInput(): void
    {
        $repo = new SocialLinkRepository($this->pdo);
        $repo->create('Existing', 'https://existing.example', 0, true);

        $controller = $this->makeController($repo);
        $request    = new Request('POST', '/liens-sociaux.php', ['action' => 'save'], [
            'label' => 'Nouveau',
            'url'   => 'https://nouveau.example',
        ]);

        try {
            $controller->handle($request);
        } catch (TerminateException) {
        }

        $links = $repo->findAll();
        $this->assertCount(2, $links);
        $this->assertSame('Nouveau', $links[1]['label']);
        $this->assertSame(1, (int) $links[1]['display_order']);
    }

    private function makeController(SocialLinkRepository $repo): SocialLinkController
    {
        $authMock = $this->createMock(AuthService::class);
        $authMock->method('isLoggedIn')->willReturn(true);
        $authMock->method('getLoggedInAdminId')->willReturn(1);

        return new SocialLinkController(
            $this->makeConfig(),
            $authMock,
            $repo,
            $this->createMock(LogRepository::class),
            $this->createMock(ViewRenderer::class),
        );
    }

    private function makeConfig(): Config
    {
        $tmp = sys_get_temp_dir() . '/ico_sociallink_cfg_' . uniqid();
        mkdir($tmp, 0o775, true);
        file_put_contents($tmp . '/config.txt', "Test Site\n\njpg,png,gif\n");
        file_put_contents($tmp . '/version.txt', '1.0.0');
        $config = Config::fromFile($tmp . '/config.txt', $tmp . '/version.txt');
        unlink($tmp . '/config.txt');
        unlink($tmp . '/version.txt');
        rmdir($tmp);

        return $config;
    }
}
