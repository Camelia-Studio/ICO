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

usort($images, function($a, $b) {
   $pathA = realpath('.') . str_replace(getBaseUrl(), '', $a);
   $pathB = realpath('.') . str_replace(getBaseUrl(), '', $b);
   return filectime($pathB) - filectime($pathA);
});

$headerImage = !empty($images) ? $images[0] : null;

$parentPath = dirname($currentPath);
if (!isSecurePath($parentPath)) {
   $parentPath = './liste_albums';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title><?php echo htmlspecialchars($albumInfo['title']); ?> - ICO</title>
   <link rel="icon" type="image/png" href="favicon.png">
   <link rel="stylesheet" href="styles.css">
</head>
<body class="gallery-page">
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
   </div>

   <div class="gallery-grid" id="gallery-grid">
    <?php foreach($images as $image): ?>
    <div class="gallery-item">
        <a href="partage.php?image=<?php echo urlencode($image); ?>">
            <img src="<?php echo htmlspecialchars($image); ?>" 
                 alt="Image de la galerie" 
                 loading="lazy"
                 onload="this.parentElement.parentElement.style.opacity = '1'">
        </a>
    </div>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const grid = document.querySelector('#gallery-grid');
    
    // Initialiser Masonry immédiatement sans attendre le chargement des images
    const masonry = new Masonry(grid, {
        itemSelector: '.gallery-item',
        columnWidth: '.gallery-item',
        gutter: 20,
        fitWidth: true
    });
    
    // Réorganiser la grille quand une image est chargée
    grid.addEventListener('load', function(e) {
        if (e.target.tagName === 'IMG') {
            masonry.layout();
        }
    }, true);
});
</script>
   <script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>
   <script src="https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js"></script>
   <script>
   document.addEventListener('DOMContentLoaded', function() {
    const grid = document.querySelector('#gallery-grid');
    const loadingClass = 'is-loading';
    
    // Ajouter une classe de chargement
    grid.classList.add(loadingClass);
    
    // Initialiser Masonry avec imagesLoaded
    imagesLoaded(grid, function() {
        const masonry = new Masonry(grid, {
            itemSelector: '.gallery-item',
            columnWidth: '.gallery-item',
            gutter: 20,
            fitWidth: true,
            transitionDuration: 0 // Désactiver l'animation pour le premier rendu
        });
        
        // Retirer la classe de chargement
        grid.classList.remove(loadingClass);
        
        // Réactiver les animations après le premier rendu
        setTimeout(() => {
            masonry.options.transitionDuration = '0.3s';
            masonry.layout();
        }, 100);
    });
});
   </script>
</body>
</html>