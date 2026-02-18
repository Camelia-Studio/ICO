<?php

declare(strict_types=1);

namespace ICO\Service;

/**
 * Vérifie la disponibilité de mises à jour depuis Gitea.
 *
 * Remplace getLatestVersion(), compareVersions() et checkUpdate()
 * de fonctions.php.
 */
class UpdateService
{
    private const TAGS_API_URL  = 'https://git.crystalyx.net/api/v1/repos/camelia-studio/ICO/tags';
    private const TAG_URL_BASE  = 'https://git.crystalyx.net/camelia-studio/ICO/releases/tag/';
    private const USER_AGENT    = 'ICO Gallery Update Checker';

    public function __construct(private readonly string $currentVersion) {}

    // -------------------------------------------------------------------------
    // API publique
    // -------------------------------------------------------------------------

    /**
     * Retourne les informations sur la dernière version publiée.
     *
     * @return array{version: string, url: string}|null
     */
    public function getLatestVersion(): ?array
    {
        $response = $this->fetchTags();

        if ($response === null) {
            return null;
        }

        $tags = json_decode($response, true);

        if (!is_array($tags) || empty($tags)) {
            return null;
        }

        usort($tags, static fn (array $a, array $b): int =>
            strtotime((string) ($b['created_at'] ?? '')) - strtotime((string) ($a['created_at'] ?? ''))
        );

        $latest = $tags[0];

        return [
            'version' => ltrim((string) $latest['name'], 'v'),
            'url'     => self::TAG_URL_BASE . $latest['name'],
        ];
    }

    /**
     * Compare deux chaînes de version semver (ex : "1.2.3").
     *
     * Retourne  1 si $v1 > $v2
     *           0 si $v1 == $v2
     *          -1 si $v1 < $v2
     */
    public function compareVersions(string $v1, string $v2): int
    {
        $parts1 = array_map('intval', explode('.', $v1));
        $parts2 = array_map('intval', explode('.', $v2));

        for ($i = 0; $i < 3; $i++) {
            $a = $parts1[$i] ?? 0;
            $b = $parts2[$i] ?? 0;

            if ($a > $b) {
                return 1;
            }
            if ($a < $b) {
                return -1;
            }
        }

        return 0;
    }

    /**
     * Vérifie si une mise à jour est disponible.
     *
     * @return array{available: bool, current: string, latest: string, url: string}|null
     *         null si la vérification a échoué (réseau, parsing…)
     */
    public function checkUpdate(): ?array
    {
        $latest = $this->getLatestVersion();

        if ($latest === null) {
            return null;
        }

        return [
            'available' => $this->compareVersions($latest['version'], $this->currentVersion) > 0,
            'current'   => $this->currentVersion,
            'latest'    => $latest['version'],
            'url'       => $latest['url'],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Effectue la requête HTTP vers l'API Gitea.
     * Retourne le corps de la réponse, ou null en cas d'erreur.
     */
    private function fetchTags(): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init(self::TAGS_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            error_log('ICO UpdateService: erreur cURL — ' . $error);
            return null;
        }

        return (string) $response;
    }
}
