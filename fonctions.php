<?php
// Configuration
define('PROJECT_ROOT_DIR', 'test-ico');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Configuration de la durée de session
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

// Nouvelle fonction de vérification de session
function checkAdminSession() {
    // Ne pas vérifier si on est déjà sur la page de login
    if (basename($_SERVER['PHP_SELF']) === 'admin.php' && isset($_GET['action']) && $_GET['action'] === 'login') {
        return;
    }
    
    $timeout = 86400;
    if (!isset($_SESSION['admin_id']) || 
        (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $timeout)) {
        session_destroy();
        header('Location: admin.php?action=login');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Obtient l'URL de base du site
 * @return string L'URL de base du site
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . '/' . PROJECT_ROOT_DIR;
}

/**
 * Vérifie si le chemin est sécurisé (dans le dossier liste_albums_prives)
 * @param string $path Chemin à vérifier
 * @return bool True si le chemin est sécurisé
 */
function isSecurePrivatePath($path) {
    $realPath = realpath($path);
    $privateRootPath = realpath('./liste_albums_prives');
    
    return $realPath && (strpos($realPath, $privateRootPath) === 0);
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
        'mature_content' => false,
        'more_info_url' => ''
    ];
    
    if (file_exists($infoFile)) {
        $content = file_get_contents($infoFile);
        $lines = explode("\n", $content);
        if (isset($lines[0])) $info['title'] = trim($lines[0]);
        if (isset($lines[1])) $info['description'] = trim($lines[1]);
        if (isset($lines[2])) $info['mature_content'] = trim($lines[2]) === '18+';
        if (isset($lines[3])) $info['more_info_url'] = trim($lines[3]);
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
    $carouselPath = realpath('./img_carrousel');
    
    return $realPath && (
        (strpos($realPath, $rootPath) === 0) || 
        ($carouselPath && strpos($realPath, $carouselPath) === 0)
    );
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

/**
 * Génère une clé cryptographiquement sûre pour le partage
 * @param int $length Longueur de la clé
 * @return string Clé générée
 */
function generateShareKey($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Génère un identifiant unique pour un album
 * @param int $length Longueur de l'identifiant
 * @return string Identifiant généré
 */
function generateAlbumIdentifier($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Crée ou récupère l'identifiant unique d'un album
 * @param string $albumPath Chemin de l'album
 * @return string|false Identifiant de l'album ou false en cas d'erreur
 */
function ensureAlbumIdentifier($albumPath) {
    $db = new SQLite3('database.sqlite');
    
    // Vérifie si l'album a déjà un identifiant
    $stmt = $db->prepare('SELECT identifier FROM album_identifiers WHERE path = :path');
    $stmt->bindValue(':path', $albumPath, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray()) {
        return $row['identifier'];
    }
    
    // Génère un nouvel identifiant
    $identifier = generateAlbumIdentifier();
    
    // Insère le nouvel identifiant
    $stmt = $db->prepare('INSERT INTO album_identifiers (identifier, path) VALUES (:identifier, :path)');
    $stmt->bindValue(':identifier', $identifier, SQLITE3_TEXT);
    $stmt->bindValue(':path', $albumPath, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        return $identifier;
    }
    
    return false;
}

/**
 * Crée une nouvelle clé de partage
 * @param string $albumIdentifier Identifiant de l'album
 * @param int $duration Durée de validité en heures
 * @param string $comment Commentaire optionnel
 * @return string|false Clé de partage ou false en cas d'erreur
 */
function createShareKey($albumIdentifier, $duration, $comment = '') {
    $db = new SQLite3('database.sqlite');
    
    // Génère une nouvelle clé
    $key = generateShareKey();
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$duration} hours"));
    
    // Insère la clé
    $stmt = $db->prepare('INSERT INTO share_keys (key_value, album_identifier, expires_at, comment) 
                         VALUES (:key, :identifier, :expires, :comment)');
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->bindValue(':identifier', $albumIdentifier, SQLITE3_TEXT);
    $stmt->bindValue(':expires', $expiresAt, SQLITE3_TEXT);
    $stmt->bindValue(':comment', $comment, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        return $key;
    }
    
    return false;
}

/**
 * Vérifie la validité d'une clé de partage
 * @param string $key Clé de partage
 * @return array|false Informations sur l'album ou false si clé invalide
 */
function validateShareKey($key) {
    $db = new SQLite3('database.sqlite');
    
    $stmt = $db->prepare('SELECT a.path, a.identifier 
                         FROM share_keys s
                         JOIN album_identifiers a ON s.album_identifier = a.identifier
                         WHERE s.key_value = :key 
                         AND s.expires_at > datetime("now")');
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // S'assurer que le chemin est valide et sécurisé
        $path = realpath($row['path']);
        if ($path && isSecurePrivatePath($path)) {
            $row['path'] = $path;
            return $row;
        }
    }
    
    return false;
}

/**
 * Nettoie les clés de partage expirées
 * @return int Nombre de clés supprimées
 */
function cleanExpiredShareKeys() {
    $db = new SQLite3('database.sqlite');
    
    $stmt = $db->prepare('DELETE FROM share_keys WHERE expires_at <= datetime("now")');
    $stmt->execute();
    
    return $db->changes();
}

/**
 * Récupère la version actuelle du projet
 * @return string La version du projet
 */
function getVersion() {
    $versionFile = __DIR__ . '/version.txt';
    if (file_exists($versionFile)) {
        return trim(file_get_contents($versionFile));
    }
    return 'inconnue'; // Version par défaut si le fichier n'existe pas
}

function getSiteConfig() {
    $configFile = './config.txt';
    $config = [
        'site_title' => 'ICO',
        'site_description' => 'ICO est la galerie d\'images de l\'association Camélia Studio.'
    ];
    
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        $lines = explode("\n", $content);
        if (isset($lines[0])) $config['site_title'] = trim($lines[0]);
        if (isset($lines[1])) $config['site_description'] = trim($lines[1]);
    }
    
    return $config;
}

/**
 * Récupère la dernière version disponible depuis Gitea
 * @return array|false Tableau contenant ['version' => 'x.x.x', 'url' => 'url'] ou false en cas d'erreur
 */
function getLatestVersion() {
    $ch = curl_init('https://git.crystalyx.net/api/v1/repos/camelia-studio/ICO/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ICO Gallery Update Checker');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if (!$response) {
        error_log("Erreur lors de la vérification des mises à jour : " . $error);
        return false;
    }
    
    $tags = json_decode($response, true);
    if (!$tags || !is_array($tags)) {
        return false;
    }
    
    // Trier les tags par date de création (le plus récent en premier)
    usort($tags, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    if (empty($tags)) {
        return false;
    }
    
    // Récupérer le dernier tag
    $latestTag = $tags[0];
    
    // Construction de l'URL directe vers le tag sur Gitea
    $tagUrl = 'https://git.crystalyx.net/camelia-studio/ICO/releases/tag/' . $latestTag['name'];
    
    return [
        'version' => ltrim($latestTag['name'], 'v'), // Enlever le 'v' potentiel du numéro de version
        'url' => $tagUrl // Utiliser l'URL construite manuellement
    ];
}

/**
 * Compare deux numéros de version
 * @param string $version1 Premier numéro de version
 * @param string $version2 Second numéro de version
 * @return int Retourne 1 si version1 > version2, -1 si version1 < version2, 0 si égales
 */
function compareVersions($version1, $version2) {
    $v1 = array_map('intval', explode('.', $version1));
    $v2 = array_map('intval', explode('.', $version2));
    
    for ($i = 0; $i < 3; $i++) {
        $v1[$i] = $v1[$i] ?? 0;
        $v2[$i] = $v2[$i] ?? 0;
        
        if ($v1[$i] > $v2[$i]) return 1;
        if ($v1[$i] < $v2[$i]) return -1;
    }
    
    return 0;
}

/**
 * Vérifie si une mise à jour est disponible
 * @return array|false ['available' => bool, 'current' => string, 'latest' => string, 'url' => string] ou false
 */
function checkUpdate() {
    $currentVersion = trim(file_get_contents(__DIR__ . '/version.txt'));
    $latest = getLatestVersion();
    
    if (!$latest) {
        return false;
    }
    
    return [
        'available' => compareVersions($latest['version'], $currentVersion) > 0,
        'current' => $currentVersion,
        'latest' => $latest['version'],
        'url' => $latest['url']
    ];
}
?>