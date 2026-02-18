<?php

declare(strict_types=1);

namespace ICO\Http;

/**
 * Représente une réponse HTTP à envoyer au client.
 *
 * La classe est immuable : chaque modificateur retourne une nouvelle instance.
 * L'envoi effectif (headers + body) est déclenché par send().
 *
 * Usage :
 *   Response::redirect('/admin.php')->send();
 *   (new Response('Texte', 200))->withHeader('X-Foo', 'bar')->send();
 */
class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly string $body       = '',
        private readonly int    $statusCode = 200,
        private readonly array  $headers    = [],
    ) {
    }

    // -------------------------------------------------------------------------
    // Factory helpers
    // -------------------------------------------------------------------------

    /**
     * Crée une réponse de redirection (302 par défaut).
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    /**
     * Crée une réponse texte/HTML.
     */
    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Crée une réponse JSON.
     */
    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    // -------------------------------------------------------------------------
    // Modificateurs (immutables)
    // -------------------------------------------------------------------------

    public function withStatus(int $code): self
    {
        return new self($this->body, $code, $this->headers);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->body, $this->statusCode, array_merge($this->headers, [$name => $value]));
    }

    public function withBody(string $body): self
    {
        return new self($body, $this->statusCode, $this->headers);
    }

    // -------------------------------------------------------------------------
    // Accesseurs (utiles pour les tests)
    // -------------------------------------------------------------------------

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    // -------------------------------------------------------------------------
    // Envoi
    // -------------------------------------------------------------------------

    /**
     * Envoie les headers puis le corps.
     * Ne doit être appelé qu'une seule fois par requête.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
