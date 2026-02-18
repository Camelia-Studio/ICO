<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Http;

use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\Router;
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    // =========================================================================
    // Request
    // =========================================================================

    public function testRequestStoresMethodAndUri(): void
    {
        $req = new Request('GET', '/albums.php');

        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/albums.php', $req->getUri());
    }

    public function testRequestQueryAccessors(): void
    {
        $req = new Request('GET', '/albums.php', ['album' => 'test']);

        $this->assertSame('test', $req->query('album'));
        $this->assertNull($req->query('missing'));
        $this->assertSame('default', $req->query('missing', 'default'));
    }

    public function testRequestBodyAccessors(): void
    {
        $req = new Request('POST', '/admin.php', [], ['username' => 'alice']);

        $this->assertSame('alice', $req->post('username'));
        $this->assertNull($req->post('missing'));
    }

    public function testRequestIsPostAndIsGet(): void
    {
        $get  = new Request('GET', '/');
        $post = new Request('POST', '/admin.php');

        $this->assertTrue($get->isGet());
        $this->assertFalse($get->isPost());
        $this->assertTrue($post->isPost());
        $this->assertFalse($post->isGet());
    }

    public function testRequestServerAccessor(): void
    {
        $req = new Request('GET', '/', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $this->assertSame('127.0.0.1', $req->server('REMOTE_ADDR'));
        $this->assertNull($req->server('UNKNOWN'));
    }

    // =========================================================================
    // Response
    // =========================================================================

    public function testResponseDefaultStatusCode(): void
    {
        $resp = new Response();

        $this->assertSame(200, $resp->getStatusCode());
    }

    public function testResponseWithStatus(): void
    {
        $resp = (new Response())->withStatus(404);

        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testResponseWithHeader(): void
    {
        $resp = (new Response())->withHeader('X-Foo', 'bar');

        $this->assertSame('bar', $resp->getHeader('X-Foo'));
    }

    public function testResponseWithBody(): void
    {
        $resp = (new Response())->withBody('hello');

        $this->assertSame('hello', $resp->getBody());
    }

    public function testResponseRedirectFactory(): void
    {
        $resp = Response::redirect('/admin.php');

        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame('/admin.php', $resp->getHeader('Location'));
    }

    public function testResponseRedirectCustomStatus(): void
    {
        $resp = Response::redirect('/admin.php', 301);

        $this->assertSame(301, $resp->getStatusCode());
    }

    public function testResponseHtmlFactory(): void
    {
        $resp = Response::html('<p>ok</p>');

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('<p>ok</p>', $resp->getBody());
        $this->assertStringContainsString('text/html', $resp->getHeader('Content-Type') ?? '');
    }

    public function testResponseJsonFactory(): void
    {
        $resp = Response::json(['key' => 'value']);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('{"key":"value"}', $resp->getBody());
        $this->assertStringContainsString('application/json', $resp->getHeader('Content-Type') ?? '');
    }

    public function testResponseIsImmutable(): void
    {
        $original = new Response('body', 200);
        $modified = $original->withStatus(404);

        $this->assertSame(200, $original->getStatusCode());
        $this->assertSame(404, $modified->getStatusCode());
    }

    // =========================================================================
    // Router
    // =========================================================================

    public function testRouterResolvesKnownRoute(): void
    {
        $router = new Router('/var/www/ico', '');
        $req    = new Request('GET', '/albums.php');

        $this->assertSame('/var/www/ico/albums.php', $router->resolve($req));
    }

    public function testRouterResolvesRootToIndex(): void
    {
        $router = new Router('/var/www/ico', '');
        $req    = new Request('GET', '/');

        $this->assertSame('/var/www/ico/index.php', $router->resolve($req));
    }

    public function testRouterResolvesIndexPhpExplicitly(): void
    {
        $router = new Router('/var/www/ico', '');
        $req    = new Request('GET', '/index.php');

        $this->assertSame('/var/www/ico/index.php', $router->resolve($req));
    }

    public function testRouterReturnsNullForUnknownRoute(): void
    {
        $router = new Router('/var/www/ico', '');
        $req    = new Request('GET', '/nonexistent.php');

        $this->assertNull($router->resolve($req));
    }

    public function testRouterStripsBasePath(): void
    {
        $router = new Router('/var/www/ico', 'mon-ico');
        $req    = new Request('GET', '/mon-ico/albums.php');

        $this->assertSame('/var/www/ico/albums.php', $router->resolve($req));
    }

    public function testRouterStripsBasePathForRoot(): void
    {
        $router = new Router('/var/www/ico', 'mon-ico');
        $req    = new Request('GET', '/mon-ico');

        $this->assertSame('/var/www/ico/index.php', $router->resolve($req));
    }

    public function testRouterStripsBasePathWithSlash(): void
    {
        $router = new Router('/var/www/ico', 'mon-ico');
        $req    = new Request('GET', '/mon-ico/');

        // "/mon-ico/" → strip prefix → "/" → index.php
        $this->assertSame('/var/www/ico/index.php', $router->resolve($req));
    }

    public function testRouterAddCustomRoute(): void
    {
        $router = new Router('/var/www/ico', '');
        $router->add('/custom.php', 'custom.php');
        $req = new Request('GET', '/custom.php');

        $this->assertSame('/var/www/ico/custom.php', $router->resolve($req));
    }

    public function testRouterGetRoutesContainsDefaults(): void
    {
        $router = new Router('/var/www/ico', '');
        $routes = $router->getRoutes();

        $this->assertArrayHasKey('/albums.php', $routes);
        $this->assertArrayHasKey('/admin.php', $routes);
        $this->assertArrayHasKey('/', $routes);
    }

    public function testRouterResolvesAllAdminPages(): void
    {
        $router = new Router('/var/www/ico', '');

        $pages = [
            '/admin.php', '/utilisateurs.php', '/clefs.php', '/logs.php',
            '/personnalisation.php', '/arbre.php', '/arbre-prive.php',
            '/arbre-img.php', '/arbre-img-prive.php',
        ];

        foreach ($pages as $page) {
            $req = new Request('GET', $page);
            $this->assertNotNull(
                $router->resolve($req),
                "La route $page devrait être résolue"
            );
        }
    }
}
