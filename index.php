<?php
require_once 'fonctions.php';

function getCarouselImages($limit = 5) {
    $images = [];
    $carouselDir = './img_carrousel';
    
    // Vérifier si le dossier existe
    if (!is_dir($carouselDir)) {
        // Créer le dossier s'il n'existe pas
        mkdir($carouselDir, 0755, true);
        return $images;
    }
    
    foreach (new DirectoryIterator($carouselDir) as $file) {
        if ($file->isDot()) continue;
        if ($file->isFile()) {
            $extension = strtolower($file->getExtension());
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $images[] = str_replace('\\', '/', $file->getPathname());
            }
        }
    }

    // Trier par date de création décroissante
    usort($images, function($a, $b) {
        return filectime($b) - filectime($a);
    });

    return array_slice($images, 0, $limit);
}

$carouselImages = getCarouselImages();
$config = getSiteConfig();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['site_title']); ?> - <?php echo htmlspecialchars($config['site_description']); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="carousel">
        <?php foreach($carouselImages as $index => $image): ?>
            <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>">
                <img src="<?php echo htmlspecialchars($image); ?>" alt="Image du carrousel">
            </div>
        <?php endforeach; ?>
    </div>

    <div class="overlay">
        <h1><?php echo htmlspecialchars($config['site_title']); ?></h1>
        <p><?php echo htmlspecialchars($config['site_description']); ?></p>
        <a href="albums.php" class="cta-button">Accéder aux galeries</a>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentSlide = 0;
            const slides = document.querySelectorAll('.carousel-slide');
            
            if (slides.length === 0) return; // Sortir si aucune image
            
            function showSlide(index) {
                slides.forEach(slide => {
                    slide.classList.remove('active');
                });
                
                slides[index].classList.add('active');
            }

            function nextSlide() {
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
            }

            // Initialiser le premier slide
            showSlide(0);
            
            // Changer de slide toutes les 5 secondes seulement s'il y a plus d'une image
            if (slides.length > 1) {
                setInterval(nextSlide, 5000);
            }
        });
    </script>
</body>
</html>