<?php

declare(strict_types=1);

namespace ICO\Http;

/**
 * Représente la requête HTTP courante.
 *
 * Encapsule $_SERVER, $_GET, $_POST, $_COOKIE et $_FILES.
 * Toutes les données sont lues à la construction ; la classe est immuable
 * après instanciation.
 *
 * Usage normal :
 *   $request = Request::fromGlobals();
 *
 * Usage dans les tests :
 *   $request = new Request('GET', '/albums.php', ['album' => 'foo']);
 */
class Request
{
    /**
     * @param string               $method       Méthode HTTP en majuscules (GET, POST…)
     * @param string               $uri          URI brute, sans query string (ex : "/albums.php")
     * @param array<string,mixed>  $query        Paramètres GET ($_GET)
     * @param array<string,mixed>  $body         Paramètres POST ($_POST)
     * @param array<string,mixed>  $cookies      Cookies ($_COOKIE)
     * @param array<string,mixed>  $server       Variables serveur ($_SERVER)
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array  $query   = [],
        private readonly array  $body    = [],
        private readonly array  $cookies = [],
        private readonly array  $server  = [],
    ) {}

    /**
     * Construit l'instance à partir des superglobales PHP.
     */
    public static function fromGlobals(): self
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

        return new self(
            method:  strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            uri:     $uri,
            query:   $_GET    ?? [],
            body:    $_POST   ?? [],
            cookies: $_COOKIE ?? [],
            server:  $_SERVER ?? [],
        );
    }

    // -------------------------------------------------------------------------
    // Accesseurs
    // -------------------------------------------------------------------------

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /** @return array<string,mixed> */
    public function getQuery(): array
    {
        return $this->query;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public function getBody(): array
    {
        return $this->body;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }
}
