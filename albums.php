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

// Récupérer tous les sous-dossiers
$albums = [];
foreach (new DirectoryIterator($currentPath) as $item) {
    if ($item->isDot()) continue;
    if ($item->isDir()) {
        $albumPath = $item->getPathname();
        $info = getAlbumInfo($albumPath);
        
        $images = hasSubfolders($albumPath) ? 
                 getImagesRecursively($albumPath) : 
                 getLatestImages($albumPath);
        
        $albums[] = [
            'path' => str_replace('\\', '/', $albumPath),
            'title' => $info['title'],
            'description' => $info['description'],
            'images' => $images,
            'hasSubfolders' => hasSubfolders($albumPath),
            'hasImages' => hasImages($albumPath)
        ];
    }
}

// Déterminer le chemin parent pour le bouton retour
$parentPath = dirname($currentPath);
if (!isSecurePath($parentPath)) {
    $parentPath = null;
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Albums - ICO</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="albums-page">
    <?php if ($parentPath): ?>
    <a href="?path=<?php echo urlencode($parentPath); ?>" class="back-button">Retour</a>
    <?php else: ?>
    <a href="index.php" class="back-button">Retour</a>
    <?php endif; ?>

    <div class="albums-grid">
        <?php foreach ($albums as $album): ?>
        <a href="<?php echo $album['hasSubfolders'] ? 'albums.php' : 'galeries.php'; ?>?path=<?php echo urlencode($album['path']); ?>" 
           class="album-card">
            <div class="album-images">
                <?php if (empty($album['images'])): ?>
                    <div class="empty-album"></div>
                <?php else: ?>
                    <?php foreach ($album['images'] as $index => $image): ?>
                        <div class="album-image" style="background-image: url('<?php echo htmlspecialchars($image); ?>')"></div>
                    <?php endforeach; ?>
                    <?php for ($i = count($album['images']); $i < 4; $i++): ?>
                        <div class="empty-image"></div>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
            <div class="album-info">
                <h2><?php echo htmlspecialchars($album['title']); ?></h2>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, {
            threshold: 0.1
        });

        document.querySelectorAll('.album-card').forEach(card => {
            observer.observe(card);
        });
    });
    </script>
</body>
</html>