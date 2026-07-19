<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Http\Request;
use ICO\Http\TerminateException;
use ICO\Service\AlbumService;
use ICO\Service\PathService;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

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

        $safeName = preg_replace('/[^a-z0-9_\-]/i', '_', $albumInfo['title']) ?: 'album';
        $filename = $safeName . '.zip';
        $zip      = new ZipStream(
            defaultCompressionMethod: CompressionMethod::STORE,
            outputName: $filename,
            contentType: 'application/zip',
            flushOutput: true,
        );

        foreach (new DirectoryIterator($currentPath) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            if (!in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                continue;
            }

            $zip->addFileFromPath(
                fileName: $file->getFilename(),
                path: $file->getPathname(),
            );
        }

        $zip->finish();
        throw new TerminateException();
    }
}
