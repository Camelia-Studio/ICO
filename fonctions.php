<?php
// Configuration
define('PROJECT_ROOT_DIR', 'test-ico');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

/**
 * Obtient l'URL de base du site
 * @return string L'URL de base du site
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . '/' . PROJECT_ROOT_DIR;
}

/**
 * Récupère les informations d'un album depuis son fichier infos.txt
 * @param string $albumPath Chemin vers l'album
 * @return array Tableau contenant le titre et la description de l'album
 */
function getAlbumInfo($albumPath) {
    $infoFile = $albumPath . '/infos.txt';
    $info = [
        'title' => basename($albumPath),
        'description' => '',
        'mature_content' => false
    ];
    
    if (file_exists($infoFile)) {
        $content = file_get_contents($infoFile);
        $lines = explode("\n", $content);
        if (isset($lines[0])) $info['title'] = trim($lines[0]);
        if (isset($lines[1])) $info['description'] = trim($lines[1]);
        if (isset($lines[2])) $info['mature_content'] = trim($lines[2]) === '18+';
    }
    
    return $info;
}

/**
 * Récupère les dernières images d'un dossier
 * @param string $albumPath Chemin vers l'album
 * @param int $limit Nombre d'images à récupérer
 * @return array Tableau des URLs des images
 */
function getLatestImages($albumPath, $limit = 4) {
    $images = [];
    $baseUrl = getBaseUrl();
    
    if (!is_dir($albumPath)) return $images;
    
    foreach (new DirectoryIterator($albumPath) as $file) {
        if ($file->isDot()) continue;
        if ($file->isFile()) {
            $extension = strtolower($file->getExtension());
            if (in_array($extension, ALLOWED_EXTENSIONS)) {
                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(realpath('./'))));
                $images[] = $baseUrl . '/' . ltrim($relativePath, '/');
            }
        }
    }

    usort($images, function($a, $b) {
        $pathA = realpath('.') . str_replace(getBaseUrl(), '', $a);
        $pathB = realpath('.') . str_replace(getBaseUrl(), '', $b);
        return filectime($pathB) - filectime($pathA);
    });

    return array_slice($images, 0, $limit);
}

/**
 * Récupère les images de manière récursive dans tous les sous-dossiers
 * @param string $albumPath Chemin vers l'album
 * @param int $limit Nombre d'images à récupérer
 * @return array Tableau des URLs des images
 */
function getImagesRecursively($albumPath, $limit = 4) {
    $images = [];
    $baseUrl = getBaseUrl();
    
    if (!is_dir($albumPath)) return $images;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($albumPath),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $extension = strtolower($file->getExtension());
            if (in_array($extension, ALLOWED_EXTENSIONS)) {
                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(realpath('./'))));
                $images[] = $baseUrl . '/' . ltrim($relativePath, '/');
            }
        }
    }

    usort($images, function($a, $b) {
        $pathA = realpath('.') . str_replace(getBaseUrl(), '', $a);
        $pathB = realpath('.') . str_replace(getBaseUrl(), '', $b);
        return filectime($pathB) - filectime($pathA);
    });

    return array_slice($images, 0, $limit);
}

/**
 * Vérifie si un dossier contient des sous-dossiers
 * @param string $path Chemin du dossier
 * @return bool True si le dossier contient des sous-dossiers
 */
function hasSubfolders($path) {
    if (!is_dir($path)) return false;
    
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot()) continue;
        if ($item->isDir()) return true;
    }
    return false;
}

/**
 * Vérifie si un dossier contient des images
 * @param string $path Chemin du dossier
 * @return bool True si le dossier contient des images
 */
function hasImages($path) {
    if (!is_dir($path)) return false;
    
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot()) continue;
        if ($item->isFile()) {
            $extension = strtolower($item->getExtension());
            if (in_array($extension, ALLOWED_EXTENSIONS)) return true;
        }
    }
    return false;
}

/**
 * Vérifie si le chemin est sécurisé (dans le dossier liste_albums)
 * @param string $path Chemin à vérifier
 * @return bool True si le chemin est sécurisé
 */
function isSecurePath($path) {
    $realPath = realpath($path);
    $rootPath = realpath('./liste_albums');
    return $realPath && strpos($realPath, $rootPath) === 0;
}

/**
 * Formate la taille d'un fichier en format lisible
 * @param int $bytes Taille en octets
 * @return string Taille formatée (ex: "1.2 MB")
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 1) . ' ' . $units[$pow];
}

/**
 * Récupère les dimensions d'une image de manière sécurisée
 * @param string $path Chemin vers l'image
 * @return array|false Tableau contenant width et height ou false en cas d'erreur
 */
function getSecureImageSize($path) {
    try {
        if (!file_exists($path)) return false;
        $imageInfo = getimagesize($path);
        if ($imageInfo === false) return false;
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Génère un identifiant unique sécurisé
 * @param int $length Longueur de l'identifiant
 * @return string Identifiant unique
 */
function generateSecureId($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Nettoie et sécurise un nom de fichier
 * @param string $filename Nom du fichier à nettoyer
 * @return string Nom de fichier sécurisé
 */
function sanitizeFilename($filename) {
    // Supprime les caractères spéciaux
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
    // Évite les noms de fichiers commençant par un point
    $filename = ltrim($filename, '.');
    // Limite la longueur du nom de fichier
    return substr($filename, 0, 255);
}
?>