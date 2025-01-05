<?php
require_once 'fonctions.php';
session_start();

// Initialiser les variables
$shareKey = $_GET['key'] ?? '';
$errorTitle = null;
$errorMessage = null;
$albumData = null;
$images = [];
$headerImage = null;

// Vérifier la présence et la validité de la clé de partage
if (empty($shareKey)) {
    $errorTitle = "Accès refusé";
    $errorMessage = "Aucune clé de partage fournie.";
} else {
    // Valider la clé et récupérer les informations de l'album
    $albumInfo = validateShareKey($shareKey);
    if (!$albumInfo) {
        $errorTitle = "Lien de partage invalide";
        $errorMessage = "Ce lien de partage a expiré ou n'existe pas.";
    } else {
        $currentPath = $albumInfo['path'];
        $albumData = getAlbumInfo($currentPath);
        $baseUrl = getBaseUrl();

        // Récupérer les images de l'album
        if (is_dir($currentPath)) {
            foreach (new DirectoryIterator($currentPath) as $file) {
                if ($file->isDot()) continue; 
                if ($file->isFile()) {
                    $extension = strtolower($file->getExtension());
                    if (in_array($extension, ALLOWED_EXTENSIONS)) {
                        // Obtenir le chemin relatif depuis la racine du projet
                        $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(realpath('./'))));
                        $url = $baseUrl . '/' . ltrim($relativePath, '/');
                        // Vérifier que le fichier existe et est accessible
                        if (file_exists($file->getPathname())) {
                            $images[] = $url;
                        }
                    }
                }
            }
        }

        // Tri des images pour mettre les "top" en premier
        usort($images, function($a, $b) {
            $isTopA = strpos(basename($a), '--top--') !== false;
            $isTopB = strpos(basename($b), '--top--') !== false;
            
            if ($isTopA && !$isTopB) return -1;
            if (!$isTopA && $isTopB) return 1;
            
            $pathA = realpath('.') . str_replace(getBaseUrl(), '', $a);
            $pathB = realpath('.') . str_replace(getBaseUrl(), '', $b);
            return filectime($pathB) - filectime($pathA);
        });

        $headerImage = !empty($images) ? $images[0] : null;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($albumData) ? htmlspecialchars($albumData['title']) : htmlspecialchars($errorTitle); ?> - ICO</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="gallery-page<?php echo isset($albumData) && $albumData['mature_content'] ? ' gallery-page-mature content-blurred' : ''; ?>">
    <?php if ($errorTitle): ?>
    <!-- Affichage de l'erreur -->
    <div class="error-container">
        <div class="error-content">
            <h1><?php echo htmlspecialchars($errorTitle); ?></h1>
            <p><?php echo htmlspecialchars($errorMessage); ?></p>
            <a href="index.php" class="action-button">Retour à l'accueil</a>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Affichage de l'avertissement contenu mature si nécessaire -->
    <?php if ($albumData['mature_content']): ?>
    <div id="mature-warning" class="mature-overlay">
        <div class="mature-content">
            <div class="mature-icon">🔞</div>
            <h2>Cet album contient du contenu réservé à un public averti.</h2>
            <button onclick="acceptMatureContent()" class="mature-button">J'ai plus de 18 ans - Afficher le contenu</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Badge indiquant qu'il s'agit d'un album privé -->
    <div class="private-gallery-badge">
        <span class="private-icon">🔒</span> Album privé
    </div>

    <?php if ($headerImage): ?>
    <div class="gallery-header">
        <img src="<?php echo htmlspecialchars($headerImage); ?>" alt="Image principale" class="header-image">
    </div>
    <?php endif; ?>

    <div class="gallery-info">
        <h1><?php echo htmlspecialchars($albumData['title']); ?></h1>
        <?php if (!empty($albumData['description'])): ?>
        <p><?php echo nl2br(htmlspecialchars($albumData['description'])); ?></p>
        <?php endif; ?>
        <?php if ($albumData['mature_content']): ?>
        <div class="mature-badge">
            <span class="mature-badge-icon">🔞</span>
            Contenu réservé aux plus de 18 ans
        </div>
        <?php endif; ?>
        <?php if (!empty($albumData['more_info_url'])): ?>
        <div class="more-info-button">
            <a href="<?php echo htmlspecialchars($albumData['more_info_url']); ?>" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="action-button">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12" y2="8"/>
                </svg>
                En savoir plus sur <?php echo htmlspecialchars($albumData['title']); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="gallery-grid">
        <?php foreach($images as $image): 
            $isTop = strpos(basename($image), '--top--') !== false;
            $size = getSecureImageSize(realpath('.') . str_replace(getBaseUrl(), '', $image));
            $aspectRatio = $size ? $size['width'] / $size['height'] : 1;
            $spanClass = '';
            
            if ($aspectRatio > 1.7) {
                $spanClass = 'gallery-item-wide';
            } elseif ($aspectRatio < 0.7) {
                $spanClass = 'gallery-item-tall';
            }
        ?>
        <div class="gallery-item <?php echo $isTop ? 'gallery-item-top' : ''; ?> <?php echo $spanClass; ?>">
            <a href="partage.php?image=<?php echo urlencode($image); ?>" target="_blank">
                <img src="<?php echo htmlspecialchars($image); ?>" 
                     alt="Image de la galerie"
                     loading="lazy">
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    function acceptMatureContent() {
        document.body.classList.remove('content-blurred');
        const warning = document.getElementById('mature-warning');
        if (warning) {
            warning.style.opacity = '0';
            setTimeout(() => {
                warning.style.display = 'none';
            }, 300);
        }
    }
    </script>
    <?php endif; ?>
    <?php include 'footer.php'; ?>
</body>
</html>