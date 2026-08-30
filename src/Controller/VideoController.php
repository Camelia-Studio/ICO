<?php

declare(strict_types=1);

namespace ICO\Controller;

use ICO\Http\Request;
use ICO\Http\Response;
use ICO\Http\TerminateException;
use ICO\Repository\ShareKeyRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;

/**
 * Contrôleur du proxy vidéo privé.
 *
 * Proxy sécurisé avec support des requêtes HTTP Range pour permettre la navigation
 * dans la vidéo (seek). Même logique d'authentification qu'ImageController.
 */
class VideoController
{
    /** Extensions vidéo autorisées */
    private const array VIDEO_EXTENSIONS = ['mp4', 'webm'];

    public function __construct(
        private readonly AlbumService       $albumService,
        private readonly ShareKeyRepository $shareKeyRepo,
        private readonly string             $projectRoot,
        private readonly ?AuthService       $auth = null,
    ) {
    }

    // -------------------------------------------------------------------------
    // Action principale
    // -------------------------------------------------------------------------

    /**
     * Sert une vidéo privée après contrôle d'accès.
     * Supporte les requêtes HTTP Range pour la navigation dans la vidéo.
     */
    public function serve(Request $request): never
    {
        $path         = (string) $request->query('path', '');
        $key          = (string) $request->query('key', '');
        $adminSession = (string) $request->query('admin_session', '');

        // Reconstituer le chemin absolu si un chemin relatif est fourni
        if ($path !== '' && !str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:[\\/]/', $path)) {
            $path = $this->projectRoot . '/' . ltrim($path, '/');
        }

        // Vérification de l'extension
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, self::VIDEO_EXTENSIONS, true)) {
            Response::html('', 404)->send();
            throw new TerminateException();
        }

        // Vérification du chemin (doit être sous liste_albums_prives)
        if (!$this->albumService->isSecurePrivatePath($path) || !file_exists($path)) {
            Response::html('', 404)->send();
            throw new TerminateException();
        }

        // Authentification
        if ($this->auth?->canAccessPrivatePath($path) === true
            || (!$this->auth instanceof AuthService && isset($_SESSION['admin_id']))) {
            // Session courante autorisée pour cet album.
        } elseif ($adminSession !== '') {
            if (!$this->checkAdminSession($adminSession)) {
                Response::html('', 403)->send();
                throw new TerminateException();
            }
        } elseif ($key === '' || $this->shareKeyRepo->findValidForPath($key, $path) === null) {
            Response::html('', 403)->send();
            throw new TerminateException();
        }

        $this->streamVideo($path);
    }

    // -------------------------------------------------------------------------
    // Streaming avec Range
    // -------------------------------------------------------------------------

    /**
     * Envoie le fichier vidéo en supportant les requêtes HTTP Range.
     */
    private function streamVideo(string $path): never
    {
        $fileSize = (int) filesize($path);
        $mime     = mime_content_type($path) ?: 'video/mp4';

        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-store');

        $rangeHeader = $_SERVER['HTTP_RANGE'] ?? null;

        if ($rangeHeader !== null && preg_match('/bytes=(\d*)-(\d*)/i', (string) $rangeHeader, $matches)) {
            $start  = $matches[1] !== '' ? (int) $matches[1] : 0;
            $end    = $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;
            $end    = min($end, $fileSize - 1);
            $length = $end - $start + 1;

            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
            header('Content-Length: ' . $length);

            $fp = fopen($path, 'rb');
            if ($fp !== false) {
                fseek($fp, $start);
                $remaining = $length;
                while ($remaining > 0 && !feof($fp)) {
                    $chunk = min(8192, $remaining);
                    $data  = fread($fp, $chunk);
                    if ($data === false) {
                        break;
                    }

                    echo $data;
                    $remaining -= $chunk;
                }

                fclose($fp);
            }
        } else {
            header('Content-Length: ' . $fileSize);
            readfile($path);
        }

        throw new TerminateException();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Vérifie qu'un session_id donné correspond à une session admin active.
     */
    private function checkAdminSession(string $sessionId): bool
    {
        $currentId = session_id();

        if ($currentId !== '' && $currentId !== $sessionId) {
            session_write_close();
        }

        session_id($sessionId);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $valid = isset($_SESSION['admin_id'])
            && ($_SESSION['admin_role'] ?? 'administrator') !== 'visitor';

        if ($currentId !== '' && $currentId !== $sessionId) {
            session_write_close();
            session_id($currentId);
            session_start();
        }

        return $valid;
    }
}
