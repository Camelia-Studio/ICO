<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Service\AlbumService;
use ICO\Service\PathService;
use ZipArchive;

/**
 * Génère et sert un fichier ZIP contenant toutes les images d'un album public.
 *
 * Accessible uniquement si l'option zip_download est activée dans infos.txt de l'album.
 */
class ZipController
{
    private const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    public function __construct(
        private readonly AlbumService $albumService,
        private readonly PathService  $pathService,
    ) {
    }

    public function download(Request $request): void
    {
        $rawPath     = (string) $request->query('path', '');
        $currentPath = realpath($this->pathService->toAbsolute(ltrim($rawPath, '/')));

        if ($currentPath === false) {
            $currentPath = realpath($rawPath) ?: '';
        }

        if ($currentPath === '' || !$this->albumService->isSecurePath($currentPath)) {
            http_response_code(403);
            throw new TerminateException();
        }

        $albumInfo = $this->albumService->getAlbumInfo($currentPath);

        if (!$albumInfo['zip_download']) {
            http_response_code(403);
            throw new TerminateException();
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ico_zip_');
        if ($tmpFile === false) {
            http_response_code(500);
            throw new TerminateException();
        }

        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (new DirectoryIterator($currentPath) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            if (!in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                continue;
            }

            $zip->addFile($file->getPathname(), $file->getFilename());
        }

        $zip->close();

        $safeName = preg_replace('/[^a-z0-9_\-]/i', '_', $albumInfo['title']) ?: 'album';
        $filename = $safeName . '.zip';
        $size     = filesize($tmpFile);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }

        readfile($tmpFile);
        unlink($tmpFile);
        throw new TerminateException();
    }
}
