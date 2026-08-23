<?php

declare(strict_types=1);

namespace ICO\Service;

use ICO\Config\VestikanConfig;

/**
 * Construit un VestikanClient à la demande.
 *
 * Le SDK Vestikan échoue dès sa construction si la config est incomplète ;
 * passer par une factory évite d'instancier le SDK tant qu'une action SSO
 * n'est pas réellement déclenchée (et permet de mocker le client en tests).
 */
class VestikanClientFactory
{
    public function __construct(private readonly VestikanConfig $config)
    {
    }

    public function create(): VestikanClientInterface
    {
        return new VestikanClient($this->config);
    }
}
