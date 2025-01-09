<?php
require_once 'fonctions.php';
session_start();

// Récupérer le chemin actuel depuis l'URL
$currentPath = isset($_GET['path']) ? $_GET['path'] : './liste_albums';
$currentPath = realpath($currentPath);

// Vérification de sécurité  
if (!isSecurePath($currentPath)) {
   header('Location: index.php');
   exit;
}

$albumInfo = getAlbumInfo($currentPath);
$images = [];
$baseUrl = getBaseUrl();

foreach (new DirectoryIterator($currentPath) as $file) {
   if ($file->isDot()) continue; 
   if ($file->isFile()) {
       $extension = strtolower($file->getExtension());
       if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
           $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(realpath('./'))));
           $url = $baseUrl . '/' . ltrim($relativePath, '/');
           $images[] = $url;
       }
   }
}

// Tri modifié pour mettre les images "top" en premier
usort($images, function($a, $b) {
    $isTopA = strpos(basename($a), '--top--') !== false;
    $isTopB = strpos(basename($b), '--top--') !== false;
    
    if ($isTopA && !$isTopB) return -1; // a est top, pas b
    if (!$isTopA && $isTopB) return 1;  // b est top, pas a
    
    // Si les deux sont top ou aucun n'est top, on garde le tri par date
    $pathA = realpath('.') . str_replace(getBaseUrl(), '', $a);
    $pathB = realpath('.') . str_replace(getBaseUrl(), '', $b);
    return filectime($pathB) - filectime($pathA);
});

$headerImage = !empty($images) ? $images[0] : null;

$parentPath = dirname($currentPath);
if (!isSecurePath($parentPath)) {
   $parentPath = './liste_albums';
}

$config = getSiteConfig();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title><?php echo htmlspecialchars($albumInfo['title']); ?> - <?php echo htmlspecialchars($config['site_title']); ?></title>
   <link rel="icon" type="image/png" href="favicon.png">
   <link rel="stylesheet" href="styles.css">
</head>
<body class="gallery-page<?php echo $albumInfo['mature_content'] ? ' gallery-page-mature content-blurred' : ''; ?>">
   <?php if ($albumInfo['mature_content']): ?>
   <div id="mature-warning" class="mature-overlay">
       <div class="mature-content">
           <div class="mature-icon">🔞</div>
           <h2>Cet album contient du contenu réservé à un public averti.</h2>
           <button onclick="acceptMatureContent()" class="mature-button">J'ai plus de 18 ans - Afficher le contenu</button>
       </div>
   </div>
   <?php endif; ?>

   <a href="albums.php?path=<?php echo urlencode($parentPath); ?>" class="back-button">Retour</a>

   <?php if ($headerImage): ?>
   <div class="gallery-header">
       <img src="<?php echo htmlspecialchars($headerImage); ?>" alt="Image principale" class="header-image">
   </div>
   <?php endif; ?>

   <div class="gallery-info">
       <h1><?php echo htmlspecialchars($albumInfo['title']); ?></h1>
       <?php if (!empty($albumInfo['description'])): ?>
       <p><?php echo nl2br(htmlspecialchars($albumInfo['description'])); ?></p>
       <?php endif; ?>
       <?php if ($albumInfo['mature_content']): ?>
       <div class="mature-badge">
           <span class="mature-badge-icon">🔞</span>
           Contenu réservé aux plus de 18 ans
       </div>
       <?php endif; ?>
       <?php if (!empty($albumInfo['more_info_url'])): ?>
       <div class="more-info-button">
           <a href="<?php echo htmlspecialchars($albumInfo['more_info_url']); ?>" 
              target="_blank" 
              rel="noopener noreferrer" 
              class="action-button">
               <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                   <circle cx="12" cy="12" r="10"/>
                   <line x1="12" y1="16" x2="12" y2="12"/>
                   <line x1="12" y1="8" x2="12" y2="8"/>
               </svg>
               En savoir plus sur <?php echo htmlspecialchars($albumInfo['title']); ?>
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
   // Gestion du contenu mature
   function acceptMatureContent() {
    document.body.classList.remove('gallery-page-mature');
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
    <button class="scroll-top" title="Retour en haut">↑</button>
    <script>
    const scrollBtn = document.querySelector('.scroll-top');
    window.addEventListener('scroll', () => {
        scrollBtn.style.display = window.scrollY > 500 ? 'flex' : 'none';
    });
    scrollBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    </script>
   <?php include 'footer.php'; ?>
</body>
</html>