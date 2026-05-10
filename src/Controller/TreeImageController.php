<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Config\Config;
use ICO\Http\TerminateException;
use ICO\Repository\LogRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
use ICO\Service\PathService;
use ICO\View\ViewRenderer;

/**
 * Gère la gestion des images au sein d'un album.
 *
 * - handlePublic()  — albums publics (upload, suppression, déplacement, carrousel)
 * - handlePrivate() — albums privés (upload, suppression, génération de lien de partage)
 */
class TreeImageController
{
    private readonly string $albumsRoot;

    private readonly string $privateRoot;

    private readonly string $carouselRoot;

    public function __construct(
        private readonly Config        $config,
        private readonly PathService   $pathService,
        private readonly AuthService   $auth,
        private readonly AlbumService  $albumService,
        private readonly LogRepository $logRepo,
        private readonly ViewRenderer  $view,
    ) {
        $this->albumsRoot   = $pathService->toAbsolute('liste_albums');
        $this->privateRoot  = $pathService->toAbsolute('liste_albums_prives');
        $this->carouselRoot = $pathService->toAbsolute('img_carrousel');
    }

    // -------------------------------------------------------------------------
    // arbre-img.php — images publiques (et carrousel)
    // -------------------------------------------------------------------------

    public function handlePublic(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        $rawPath     = $_GET['path'] ?? '';
        $currentPath = realpath($rawPath !== '' ? $this->pathService->toAbsolute($rawPath) : $this->albumsRoot) ?: '';
        if (!$currentPath || !$this->albumService->isSecurePath($currentPath)) {
            header('Location: arbre.php');
            throw new TerminateException();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePublicPost($currentPath);
            header('Location: arbre-img.php?path=' . urlencode($this->pathService->toRelative($currentPath)));
            throw new TerminateException();
        }

        $images    = $this->listImages($currentPath);
        $siteTitle = $this->config->getSiteTitle();
        $this->renderPublic($siteTitle, $currentPath, $images);
    }

    private function handlePublicPost(string $currentPath): void
    {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'upload':
                $this->handleUpload($currentPath, false);
                break;

            case 'toggle_top':
                $this->handleToggleTop($currentPath, false);
                break;

            case 'delete':
                $this->handleDelete($currentPath, false);
                break;

            case 'move':
                $this->handleMove($currentPath);
                break;
        }
    }

    // -------------------------------------------------------------------------
    // arbre-img-prive.php — images privées
    // -------------------------------------------------------------------------

    public function handlePrivate(): void
    {
        if (!$this->auth->isLoggedIn()) {
            header('Location: admin.php?action=login');
            throw new TerminateException();
        }

        $rawPath     = $_GET['path'] ?? '';
        $currentPath = realpath($rawPath !== '' ? $this->pathService->toAbsolute($rawPath) : $this->privateRoot) ?: '';
        if (!$currentPath || !$this->albumService->isSecurePrivatePath($currentPath)) {
            header('Location: arbre-prive.php');
            throw new TerminateException();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePrivatePost($currentPath);
            header('Location: arbre-img-prive.php?path=' . urlencode($this->pathService->toRelative($currentPath)));
            throw new TerminateException();
        }

        $images    = $this->listImages($currentPath);
        $albumInfo = $this->albumService->getAlbumInfo($currentPath);
        $siteTitle = $this->config->getSiteTitle();
        $this->renderPrivate($siteTitle, $currentPath, $images, $albumInfo);
    }

    private function handlePrivatePost(string $currentPath): void
    {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'upload':
                $this->handleUpload($currentPath, true);
                break;

            case 'toggle_top':
                $this->handleToggleTop($currentPath, true);
                break;

            case 'delete':
                $this->handleDelete($currentPath, true);
                break;
        }
    }

    // -------------------------------------------------------------------------
    // Logique partagée : upload, toggle_top, delete, move
    // -------------------------------------------------------------------------

    private function handleUpload(string $currentPath, bool $isPrivate): void
    {
        $uploadedFiles = $_FILES['images'] ?? [];
        $successCount  = 0;
        $errors        = [];
        $allowedExts   = $this->config->getAllowedExtensions();

        $count = is_array($uploadedFiles['name'] ?? null) ? count($uploadedFiles['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName   = $uploadedFiles['tmp_name'][$i];
            $fileName  = $this->sanitizeFilename($uploadedFiles['name'][$i]);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExts, true)) {
                $errors[] = 'Extension non autorisée pour ' . $fileName;
                continue;
            }

            $destination = $currentPath . '/' . $fileName;

            // Anti-collision : ajouter un suffixe numérique si le fichier existe déjà
            if (file_exists($destination)) {
                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                $counter  = 1;
                while (file_exists($destination)) {
                    $fileName    = $baseName . '_' . $counter . '.' . $extension;
                    $destination = $currentPath . '/' . $fileName;
                    $counter++;
                }
            }

            if (move_uploaded_file($tmpName, $destination)) {
                $successCount++;
            } else {
                $errors[] = 'Erreur lors du déplacement de ' . $fileName;
            }
        }

        if ($successCount > 0) {
            $logAction  = $isPrivate ? 'UPLOAD_PRIVATE_IMAGES' : 'UPLOAD_IMAGES';
            $logMessage = $isPrivate
                ? sprintf('Téléversement de %d image(s) privée(s)', $successCount)
                : sprintf('Téléversement de %d image(s)', $successCount);
            $this->logRepo->log((int) $_SESSION['admin_id'], $logAction, $logMessage, $currentPath);
            $_SESSION['success_message'] = $successCount . ' image(s) téléversée(s) avec succès.';
        }

        if ($errors !== []) {
            $_SESSION['error_message'] = implode("\n", $errors);
        }
    }

    private function handleToggleTop(string $currentPath, bool $isPrivate): void
    {
        $image = $_POST['image'] ?? '';
        if (!$image) {
            return;
        }

        $imagePath = $currentPath . '/' . basename((string) $image);
        $secure    = $isPrivate
            ? $this->albumService->isSecurePrivatePath($imagePath)
            : $this->albumService->isSecurePath($imagePath);

        if (!$secure || !file_exists($imagePath)) {
            return;
        }

        $info  = pathinfo($imagePath);
        $isTop = str_contains($info['filename'], '--top--');

        // Dans le carrousel, une seule image peut être "première" — retirer le flag des autres
        $carouselReal = realpath($this->carouselRoot) ?: $this->carouselRoot;
        $isCarousel   = str_starts_with($currentPath, $carouselReal);
        if ($isCarousel && !$isTop) {
            foreach (new DirectoryIterator($currentPath) as $file) {
                if ($file->isDot() || !$file->isFile()) {
                    continue;
                }
                if ($file->getPathname() !== $imagePath && str_contains($file->getFilename(), '--top--')) {
                    $fileInfo = pathinfo($file->getPathname());
                    $cleaned  = str_replace('--top--', '', $fileInfo['filename']) . '.' . $fileInfo['extension'];
                    rename($file->getPathname(), $currentPath . '/' . $cleaned);
                }
            }
        }

        $newName = $isTop
            ? str_replace('--top--', '', $info['filename']) . '.' . $info['extension']
            : $info['filename'] . '--top--.' . $info['extension'];

        $newPath = $currentPath . '/' . $newName;

        if (rename($imagePath, $newPath)) {
            $_SESSION['success_message'] = $isTop ? 'Image retirée des tops.' : 'Image mise en top.';
        } else {
            $_SESSION['error_message'] = 'Erreur lors de la modification du statut top.';
        }
    }

    private function handleDelete(string $currentPath, bool $isPrivate): void
    {
        $images      = $_POST['images'] ?? [];
        $deleteCount = 0;
        $errors      = [];

        foreach ($images as $image) {
            $imagePath = $currentPath . '/' . basename((string) $image);
            $secure    = $isPrivate
                ? $this->albumService->isSecurePrivatePath($imagePath)
                : $this->albumService->isSecurePath($imagePath);

            if ($secure && file_exists($imagePath)) {
                if (unlink($imagePath)) {
                    $deleteCount++;
                } else {
                    $errors[] = 'Erreur lors de la suppression de ' . basename((string) $image);
                }
            }
        }

        if ($deleteCount > 0) {
            $logAction  = $isPrivate ? 'DELETE_PRIVATE_IMAGES' : 'DELETE_IMAGES';
            $logMessage = $isPrivate
                ? sprintf('Suppression de %d image(s) privée(s)', $deleteCount)
                : sprintf('Suppression de %d image(s)', $deleteCount);
            $this->logRepo->log((int) $_SESSION['admin_id'], $logAction, $logMessage, $currentPath);
            $_SESSION['success_message'] = $deleteCount . ' image(s) supprimée(s).';
        }

        if ($errors !== []) {
            $_SESSION['error_message'] = implode("\n", $errors);
        }
    }

    private function handleMove(string $currentPath): void
    {
        $images          = $_POST['images'] ?? [];
        $destinationPath = realpath($this->pathService->toAbsolute($_POST['destination_path'] ?? '')) ?: '';
        $moveCount       = 0;
        $errors          = [];

        if (empty($destinationPath) || !is_dir($destinationPath) || !$this->albumService->isSecurePath($destinationPath)) {
            $_SESSION['error_message'] = 'Dossier de destination invalide.';
            return;
        }

        foreach ($images as $image) {
            $sourcePath = $currentPath . '/' . basename((string) $image);
            $destPath   = $destinationPath . '/' . basename((string) $image);

            if (!file_exists($sourcePath) || !$this->albumService->isSecurePath($sourcePath)) {
                continue;
            }

            // Anti-collision dans la destination
            if (file_exists($destPath)) {
                $info = pathinfo($destPath);
                $i    = 1;
                while (file_exists($destPath)) {
                    $destPath = $destinationPath . '/' . $info['filename'] . '_' . $i . '.' . $info['extension'];
                    $i++;
                }
            }

            if (rename($sourcePath, $destPath)) {
                $moveCount++;
            } else {
                $errors[] = 'Erreur lors du déplacement de ' . basename((string) $image);
            }
        }

        if ($moveCount > 0) {
            $this->logRepo->log(
                (int) $_SESSION['admin_id'],
                'MOVE_IMAGES',
                sprintf('Déplacement de %d image(s) vers ', $moveCount) . basename((string) $destinationPath),
                $currentPath . ' -> ' . $destinationPath,
            );
            $_SESSION['success_message'] = $moveCount . ' image(s) déplacée(s) avec succès.';
        }

        if ($errors !== []) {
            $_SESSION['error_message'] = implode("\n", $errors);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Retourne la liste des images d'un dossier, triées par date de création décroissante.
     *
     * @return string[]
     */
    private function listImages(string $path): array
    {
        $allowedExts = $this->config->getAllowedExtensions();
        $temp        = [];

        foreach (new DirectoryIterator($path) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            if (!in_array(strtolower($file->getExtension()), $allowedExts, true)) {
                continue;
            }

            $temp[] = ['name' => $file->getFilename(), 'time' => $file->getCTime()];
        }

        usort($temp, static fn (array $a, array $b): int => $b['time'] - $a['time']);

        return array_column($temp, 'name');
    }

    /**
     * Construit la liste <option> des dossiers cibles pour la modale de déplacement.
     */
    private function generateFolderOptions(string $path, string $currentPath, int $level = 0): string
    {
        if (!is_dir($path)) {
            return '';
        }

        $output = '';
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);

        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $fullPath = $item->getPathname();

            // Exclure le dossier courant et ses sous-dossiers, et le carrousel
            if (str_starts_with($fullPath, $currentPath)) {
                continue;
            }

            if (str_starts_with($fullPath, $this->carouselRoot)) {
                continue;
            }

            if (!$this->albumService->hasSubfolders($fullPath)) {
                $info    = $this->albumService->getAlbumInfo($fullPath);
                $output .= '<option value="' . htmlspecialchars($this->pathService->toRelative($fullPath)) . '">'
                    . $indent . htmlspecialchars($info['title'])
                    . '</option>';
            }

            $output .= $this->generateFolderOptions($fullPath, $currentPath, $level + 1);
        }

        return $output;
    }

    /**
     * Construit l'URL publique d'une image dans un album public ou le carrousel.
     */
    private function buildPublicImageUrl(string $currentPath, string $image): string
    {
        $absolutePath = $currentPath . '/' . $image;
        return $this->pathService->toUrl($absolutePath);
    }

    /**
     * Construit l'URL d'une image privée (via images.php).
     */
    private function buildPrivateImageUrl(string $currentPath, string $image): string
    {
        $absolutePath = $currentPath . '/' . $image;
        $relativePath = $this->pathService->toRelative($absolutePath);
        return $this->pathService->getBaseUrl() . '/images.php?path=' . urlencode($relativePath);
    }

    /**
     * Sanitize un nom de fichier (conserve uniquement les caractères sûrs).
     */
    private function sanitizeFilename(string $filename): string
    {
        $filename = mb_strtolower($filename);
        $filename = preg_replace('/\s+/', '-', $filename) ?? $filename;
        return preg_replace('/[^a-z0-9\-_\.]/', '', $filename) ?? $filename;
    }

    // -------------------------------------------------------------------------
    // Rendus HTML
    // -------------------------------------------------------------------------

    /**
     * @param string[] $images
     */
    private function renderPublic(string $siteTitle, string $currentPath, array $images): void
    {
        $isCarousel = str_contains($currentPath, 'img_carrousel');
        $pageTitle  = $isCarousel
            ? 'Images du carrousel'
            : 'Images de : ' . htmlspecialchars($this->albumService->getAlbumInfo($currentPath)['title']);

        $imageData = array_map(fn (string $image): array => [
            'name'  => $image,
            'url'   => $this->buildPublicImageUrl($currentPath, $image),
            'isTop' => str_contains($image, '--top--'),
        ], $images);

        $relativePath  = $this->pathService->toRelative($currentPath);
        $parentRelPath = ltrim(dirname($relativePath), '.');
        $backUrl       = 'arbre.php' . ($parentRelPath !== '' ? '?path=' . urlencode($parentRelPath) : '');
        $galleryUrl    = $isCarousel ? null : 'galeries.php?path=' . urlencode($relativePath);

        $this->view->render('pages/tree-image-public', [
            'siteTitle'     => $siteTitle,
            'backUrl'       => $backUrl,
            'imageData'     => $imageData,
            'pageTitle'     => $pageTitle,
            'folderOptions' => $this->generateFolderOptions($this->albumsRoot, $currentPath),
            'isCarousel'    => $isCarousel,
            'galleryUrl'    => $galleryUrl,
            'version'       => $this->config->getVersion(),
        ]);
    }

    /**
     * @param string[] $images
     * @param array<string, mixed> $albumInfo
     */
    private function renderPrivate(string $siteTitle, string $currentPath, array $images, array $albumInfo): void
    {
        $imageData = array_map(fn (string $image): array => [
            'name'  => $image,
            'url'   => $this->buildPrivateImageUrl($currentPath, $image),
            'isTop' => str_contains($image, '--top--'),
        ], $images);

        $parentRelPath = ltrim(dirname($this->pathService->toRelative($currentPath)), '.');
        $backUrl       = 'arbre-prive.php' . ($parentRelPath !== '' ? '?path=' . urlencode($parentRelPath) : '');

        $this->view->render('pages/tree-image-private', [
            'siteTitle'    => $siteTitle,
            'backUrl'      => $backUrl,
            'albumInfo'    => $albumInfo,
            'imageData'    => $imageData,
            'version'      => $this->config->getVersion(),
        ]);
    }
}
