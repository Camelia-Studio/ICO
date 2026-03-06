<?php

declare(strict_types=1);

namespace ICO\Controller;

use DirectoryIterator;
use ICO\Config\Config;
use ICO\Http\TerminateException;
use ICO\Repository\LogRepository;
use ICO\Service\AlbumService;
use ICO\Service\AuthService;
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
        private readonly Config       $config,
        private readonly string       $projectRoot,
        private readonly AuthService  $auth,
        private readonly AlbumService $albumService,
        private readonly LogRepository $logRepo,
        private readonly ViewRenderer  $view,
    ) {
        $this->albumsRoot   = $projectRoot . '/liste_albums';
        $this->privateRoot  = $projectRoot . '/liste_albums_prives';
        $this->carouselRoot = $projectRoot . '/img_carrousel';
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

        $currentPath = realpath($_GET['path'] ?? $this->albumsRoot) ?: '';
        if (!$currentPath || !$this->albumService->isSecurePath($currentPath)) {
            header('Location: arbre.php');
            throw new TerminateException();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePublicPost($currentPath);
            header('Location: arbre-img.php?path=' . urlencode($currentPath));
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

        $currentPath = realpath($_GET['path'] ?? $this->privateRoot) ?: '';
        if (!$currentPath || !$this->albumService->isSecurePrivatePath($currentPath)) {
            header('Location: arbre-prive.php');
            throw new TerminateException();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePrivatePost($currentPath);
            header('Location: arbre-img-prive.php?path=' . urlencode($currentPath));
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
        $destinationPath = $_POST['destination_path'] ?? '';
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
                $output .= '<option value="' . htmlspecialchars($fullPath) . '">'
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
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $basePath  = $this->config->getBasePath();
        $baseUrl   = $protocol . $_SERVER['HTTP_HOST'] . ($basePath !== '' ? '/' . $basePath : '');

        if (str_contains($currentPath, 'img_carrousel')) {
            return $baseUrl . '/img_carrousel/' . $image;
        }

        $pos = strpos($currentPath, '/liste_albums/');
        if ($pos !== false) {
            $relative = substr($currentPath, $pos + strlen('/liste_albums/'));
            return $baseUrl . '/liste_albums/' . $relative . '/' . $image;
        }

        return $baseUrl . '/' . $image;
    }

    /**
     * Construit l'URL d'une image privée (via images.php).
     */
    private function buildPrivateImageUrl(string $currentPath, string $image): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $basePath  = $this->config->getBasePath();
        $baseUrl   = $protocol . $_SERVER['HTTP_HOST'] . ($basePath !== '' ? '/' . $basePath : '');

        $url = $baseUrl . '/images.php?path=' . urlencode($currentPath . '/' . $image);
        if (isset($_SESSION['admin_id'])) {
            $url .= '&admin_session=' . session_id();
        }

        return $url;
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

        $this->view->render('pages/tree-image-public', [
            'siteTitle'     => $siteTitle,
            'currentPath'   => $currentPath,
            'imageData'     => $imageData,
            'pageTitle'     => $pageTitle,
            'folderOptions' => $this->generateFolderOptions($this->albumsRoot, $currentPath),
            'imageScripts'  => $this->renderImageScripts(true),
            'isCarousel'    => $isCarousel,
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

        $this->view->render('pages/tree-image-private', [
            'siteTitle'    => $siteTitle,
            'currentPath'  => $currentPath,
            'albumInfo'    => $albumInfo,
            'imageData'    => $imageData,
            'imageScripts' => $this->renderImageScripts(false),
            'version'      => $this->config->getVersion(),
        ]);
    }

    /**
     * Génère le bloc JavaScript commun à la page de gestion d'images.
     *
     * @param bool $withMove Inclut la logique de déplacement (uniquement pour les albums publics).
     */
    private function renderImageScripts(bool $withMove): string
    {
        $moveJs = $withMove ? <<<'JS'

                    function moveSelected() {
                        const checkboxes = document.querySelectorAll('.image-checkbox:checked');
                        if (checkboxes.length === 0) return;

                        const container = document.getElementById('selected-images-container');
                        container.innerHTML = '';

                        checkboxes.forEach(checkbox => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'images[]';
                            input.value = checkbox.value;
                            container.appendChild(input);
                        });

                        document.getElementById('moveFolderModal').style.display = 'block';
                    }

                    function closeModal(modalId) {
                        document.getElementById(modalId).style.display = 'none';
                    }

                    window.onclick = function(event) {
                        if (event.target.classList.contains('modal')) {
                            event.target.style.display = 'none';
                        }
                    };
            JS : '';

        return <<<JS
                <script>
                    function updateActionButtons() {
                        const checkboxes         = document.querySelectorAll('.image-checkbox');
                        const selectedCheckboxes = document.querySelectorAll('.image-checkbox:checked');
                        const count = selectedCheckboxes.length;

                        const deleteBtn   = document.getElementById('deleteSelectedBtn');
                        const moveBtn     = document.getElementById('moveSelectedBtn');
                        const selectAllBtn = document.getElementById('selectAllBtn');

                        if (deleteBtn) deleteBtn.style.display = count > 0 ? 'inline-flex' : 'none';
                        if (moveBtn)   moveBtn.style.display   = count > 0 ? 'inline-flex' : 'none';

                        if (selectAllBtn) {
                            selectAllBtn.textContent = checkboxes.length === selectedCheckboxes.length
                                ? 'Tout désélectionner'
                                : 'Tout sélectionner';
                        }
                    }

                    function toggleSelectAll() {
                        const checkboxes = document.querySelectorAll('.image-checkbox');
                        const allChecked = document.querySelectorAll('.image-checkbox:checked').length === checkboxes.length;
                        checkboxes.forEach(cb => { cb.checked = !allChecked; });
                        updateActionButtons();
                    }

                    function deleteImage(imageName) {
                        if (confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
                            const form = document.getElementById('imagesForm');
                            form.innerHTML = `
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="images[]" value="\x24{imageName}">
                            `;
                            form.submit();
                        }
                    }

                    function deleteSelected() {
                        const checkboxes = document.querySelectorAll('.image-checkbox:checked');
                        if (checkboxes.length > 0 && confirm('Êtes-vous sûr de vouloir supprimer les images sélectionnées ?')) {
                            document.getElementById('formAction').value = 'delete';
                            document.getElementById('imagesForm').submit();
                        }
                    }

                    function toggleTop(imageName) {
                        const form = document.createElement('form');
                        form.method = 'post';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="toggle_top">
                            <input type="hidden" name="image" value="\x24{imageName}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
            {$moveJs}
                    document.addEventListener('DOMContentLoaded', function() {
                        updateActionButtons();

                        const modal           = document.getElementById('uploadModal');
                        const dropZone        = document.getElementById('dropZone');
                        const uploadForm      = document.getElementById('uploadForm');
                        const imageUploadForm = document.getElementById('imageUploadForm');

                        if (uploadForm) {
                            uploadForm.addEventListener('submit', function() {
                                const fileInput = this.querySelector('input[type="file"]');
                                if (fileInput && fileInput.files && fileInput.files.length > 0) {
                                    modal.style.display = 'block';
                                }
                            });
                        }

                        if (imageUploadForm) {
                            imageUploadForm.addEventListener('change', function() {
                                if (this.files && this.files.length > 0) {
                                    modal.style.display = 'block';
                                    uploadForm.submit();
                                }
                            });
                        }

                        if (dropZone) {
                            dropZone.addEventListener('dragover', (e) => {
                                e.preventDefault();
                                dropZone.classList.add('drag-over');
                            });
                            dropZone.addEventListener('dragleave', () => {
                                dropZone.classList.remove('drag-over');
                            });
                            dropZone.addEventListener('drop', (e) => {
                                e.preventDefault();
                                dropZone.classList.remove('drag-over');
                                const files = e.dataTransfer.files;
                                if (files.length > 0) {
                                    const dataTransfer = new DataTransfer();
                                    for (let file of files) { dataTransfer.items.add(file); }
                                    imageUploadForm.files = dataTransfer.files;
                                    modal.style.display = 'block';
                                    uploadForm.submit();
                                }
                            });
                        }
                    });

                    const scrollBtn = document.querySelector('.scroll-top');
                    if (scrollBtn) {
                        window.addEventListener('scroll', () => {
                            scrollBtn.style.display = window.scrollY > 500 ? 'flex' : 'none';
                        });
                        scrollBtn.addEventListener('click', () => {
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        });
                    }
                </script>
                <button class="scroll-top" title="Retour en haut">↑</button>
            JS;
    }
}
