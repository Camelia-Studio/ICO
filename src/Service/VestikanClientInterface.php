<?php

declare(strict_types=1);

namespace ICO\Service;

use VestikanException;

/**
 * Abstraction du SDK Vestikan (classe globale finale, vendored telle quelle
 * dans src/Vestikan/vestikan-sdk.php) — permet de mocker le flow OAuth dans
 * les tests du contrôleur d'authentification.
 */
interface VestikanClientInterface
{
    /**
     * Génère l'URL d'autorisation Vestikan et mémorise le state anti-CSRF en session.
     */
    public function authorizeUrl(?string $returnTo = null): string;

    /**
     * Traite le retour de Vestikan (callback) : vérifie le state, échange le
     * code, et renvoie le vestikan_id.
     *
     * @throws VestikanException si le flow échoue (state invalide, code refusé…)
     */
    public function complete(): string;

    /**
     * Récupère (une fois) l'URL de retour applicative mémorisée par authorizeUrl().
     */
    public function popReturnTo(): ?string;
}
