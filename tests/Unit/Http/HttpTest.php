<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Http;

use ICO\Controller\AlbumController;
use ICO\Controller\AdminController;
use ICO\Controller\HomeController;
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

    public function testRequestGetQueryReturnsFullArray(): void
    {
        $req = new Request('GET', '/albums.php', ['album' => 'foo', 'page' => '2']);

        $this->assertSame(['album' => 'foo', 'page' => '2'], $req->getQuery());
    }

    public function testRequestGetBodyReturnsFullArray(): void
    {
        $req = new Request('POST', '/admin.php', [], ['user' => 'alice', 'pass' => 'x']);

        $this->assertSame(['user' => 'alice', 'pass' => 'x'], $req->getBody());
    }

    public function testRequestCookieAccessor(): void
    {
        $req = new Request('GET', '/', [], [], ['session_id' => 'abc123']);

        $this->assertSame('abc123', $req->cookie('session_id'));
        $this->assertNull($req->cookie('missing'));
        $this->assertSame('default', $req->cookie('missing', 'default'));
    }

    public function testRequestFromGlobalsReadsSuperglobals(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/albums.php?album=test';
        $_GET                      = ['album' => 'test'];
        $_POST                     = [];
        $_COOKIE                   = ['foo' => 'bar'];

        $req = Request::fromGlobals();

        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/albums.php', $req->getUri());
        $this->assertSame('test', $req->query('album'));
        $this->assertSame('bar', $req->cookie('foo'));
    }

    public function testRequestFromGlobalsStripsQueryStringFromUri(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/admin.php?action=login';
        $_GET                      = [];
        $_POST                     = ['username' => 'bob'];
        $_COOKIE                   = [];

        $req = Request::fromGlobals();

        $this->assertSame('/admin.php', $req->getUri());
        $this->assertSame('bob', $req->post('username'));
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

    public function testResponseGetHeadersReturnsAllHeaders(): void
    {
        $resp = (new Response())
            ->withHeader('X-Foo', 'foo')
            ->withHeader('X-Bar', 'bar');

        $headers = $resp->getHeaders();

        $this->assertArrayHasKey('X-Foo', $headers);
        $this->assertArrayHasKey('X-Bar', $headers);
        $this->assertSame('foo', $headers['X-Foo']);
        $this->assertSame('bar', $headers['X-Bar']);
    }

    public function testResponseGetHeadersEmptyByDefault(): void
    {
        $resp = new Response();

        $this->assertSame([], $resp->getHeaders());
    }

    public function testResponseSendOutputsBody(): void
    {
        $resp = new Response('body content', 200);

        ob_start();
        $resp->send();
        $output = ob_get_clean();

        $this->assertSame('body content', $output);
    }

    public function testResponseWithBodyCreatesNewInstance(): void
    {
        $original = new Response('old');
        $modified = $original->withBody('new');

        $this->assertSame('old', $original->getBody());
        $this->assertSame('new', $modified->getBody());
    }

    // =========================================================================
    // Router
    // =========================================================================

    /**
     * Instancie un Router avec toutes les routes de routes/web.php chargées.
     */
    private function makeRouter(string $basePath = ''): Router
    {
        $router = new Router('/var/www/ico', $basePath);
        $routes = require dirname(__DIR__, 3) . '/routes/web.php';
        $routes($router);

        return $router;
    }

    public function testRouterResolvesKnownRoute(): void
    {
        $router = $this->makeRouter();
        $req    = new Request('GET', '/albums.php');

        $this->assertSame([AlbumController::class, 'index'], $router->resolve($req));
    }

    public function testRouterResolvesRootToIndex(): void
    {
        $router = $this->makeRouter();
        $req    = new Request('GET', '/');

        $this->assertSame([HomeController::class, 'index'], $router->resolve($req));
    }

    public function testRouterResolvesIndexPhpExplicitly(): void
    {
        $router = $this->makeRouter();
        $req    = new Request('GET', '/index.php');

        $this->assertSame([HomeController::class, 'index'], $router->resolve($req));
    }

    public function testRouterReturnsNullForUnknownRoute(): void
    {
        $router = new Router('/var/www/ico', '');
        $req    = new Request('GET', '/nonexistent.php');

        $this->assertNull($router->resolve($req));
    }

    public function testRouterStripsBasePath(): void
    {
        $router = $this->makeRouter('mon-ico');
        $req    = new Request('GET', '/mon-ico/albums.php');

        $this->assertSame([AlbumController::class, 'index'], $router->resolve($req));
    }

    public function testRouterStripsBasePathForRoot(): void
    {
        $router = $this->makeRouter('mon-ico');
        $req    = new Request('GET', '/mon-ico');

        $this->assertSame([HomeController::class, 'index'], $router->resolve($req));
    }

    public function testRouterStripsBasePathWithSlash(): void
    {
        $router = $this->makeRouter('mon-ico');
        $req    = new Request('GET', '/mon-ico/');

        // "/mon-ico/" → strip prefix → "/" → HomeController::index
        $this->assertSame([HomeController::class, 'index'], $router->resolve($req));
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
        $router = $this->makeRouter();
        $routes = $router->getRoutes();

        $this->assertArrayHasKey('/albums.php', $routes);
        $this->assertArrayHasKey('/admin.php', $routes);
        $this->assertArrayHasKey('/', $routes);
    }

    public function testRouterResolvesAllAdminPages(): void
    {
        $router = $this->makeRouter();

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

    public function testRouterHandlerIsCallableArray(): void
    {
        $router  = $this->makeRouter();
        $req     = new Request('GET', '/admin.php');
        $handler = $router->resolve($req);

        $this->assertIsArray($handler);
        $this->assertSame([AdminController::class, 'handle'], $handler);
    }
}
