<?php

declare(strict_types=1);

namespace ICO\Service;

use ICO\Config\VestikanConfig;
use Vestikan;

/**
 * Adapte le SDK Vestikan (classe globale, vendored telle quelle) à
 * l'interface ICO\Service\VestikanClientInterface.
 */
final readonly class VestikanClient implements VestikanClientInterface
{
    private Vestikan $sdk;

    public function __construct(VestikanConfig $config)
    {
        $this->sdk = new Vestikan($config->toArray());
    }

    public function authorizeUrl(?string $returnTo = null): string
    {
        return $this->sdk->authorizeUrl($returnTo);
    }

    public function complete(): string
    {
        return $this->sdk->complete();
    }

    public function popReturnTo(): ?string
    {
        return $this->sdk->popReturnTo();
    }
}
