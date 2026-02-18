<?php

declare(strict_types=1);

namespace ICO\Service;

/**
 * Utilitaires de manipulation de fichiers.
 *
 * Remplace sanitizeFilename(), formatFileSize(), getSecureImageSize(),
 * generateSecureId(), generateShareKey(), generateAlbumIdentifier()
 * de fonctions.php, ainsi que la logique de suppression récursive
 * de dossier présente dans arbre-prive.php.
 */
class FileService
{
    // -------------------------------------------------------------------------
    // Nommage et sécurité
    // -------------------------------------------------------------------------

    /**
     * Nettoie et sécurise un nom de fichier :
     * - remplace les caractères non alphanum/._- par un tiret
     * - supprime un éventuel point en début de nom
     * - tronque à 255 caractères
     */
    public function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename) ?? '';
        $filename = ltrim($filename, '.');

        return substr($filename, 0, 255);
    }

    // -------------------------------------------------------------------------
    // Formatage
    // -------------------------------------------------------------------------

    /**
     * Formate une taille en octets en chaîne lisible (ex : "1.2 MB").
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow   = (int) floor(($bytes > 0 ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);

        return round($bytes, 1) . ' ' . $units[$pow];
    }

    // -------------------------------------------------------------------------
    // Informations image
    // -------------------------------------------------------------------------

    /**
     * Retourne les dimensions d'une image, ou null en cas d'erreur.
     *
     * @return array{width: int, height: int}|null
     */
    public function getSecureImageSize(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        try {
            $info = getimagesize($path);
        } catch (\Exception) {
            return null;
        }

        if ($info === false) {
            return null;
        }

        return ['width' => $info[0], 'height' => $info[1]];
    }

    // -------------------------------------------------------------------------
    // Génération d'identifiants
    // -------------------------------------------------------------------------

    /**
     * Génère un identifiant hexadécimal aléatoire de $length caractères.
     */
    public function generateSecureId(int $length = 32): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /**
     * Génère une clé de partage hexadécimale de $length caractères.
     */
    public function generateShareKey(int $length = 64): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /**
     * Génère un identifiant d'album hexadécimal de $length caractères.
     */
    public function generateAlbumIdentifier(int $length = 32): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    // -------------------------------------------------------------------------
    // Suppression récursive
    // -------------------------------------------------------------------------

    /**
     * Supprime récursivement un dossier et tout son contenu.
     * Retourne true si le dossier a bien été supprimé, false sinon.
     */
    public function deleteDirectoryRecursively(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        return rmdir($path);
    }
}
