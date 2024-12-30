<?php
// Fonction pour récupérer les 5 dernières images
function getLatestImages($rootDir = './liste_albums', $limit = 5) {
    $images = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootDir),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $extension = strtolower($file->getExtension());
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $images[] = str_replace('\\', '/', $file->getPathname());
            }
        }
    }

    usort($images, function($a, $b) {
        return filectime($b) - filectime($a);
    });

    return array_slice($images, 0, $limit);
}

$latestImages = getLatestImages();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICO - Galerie d'images</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="carousel">
        <?php foreach($latestImages as $index => $image): ?>
            <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>">
                <img src="<?php echo htmlspecialchars($image); ?>" alt="Image de la galerie">
            </div>
        <?php endforeach; ?>
    </div>

    <div class="overlay">
        <h1>ICO</h1>
        <p>ICO est la galerie d'images de l'association Camélia Studio.</p>
        <a href="albums.php" class="cta-button">Accéder aux galeries</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentSlide = 0;
            const slides = document.querySelectorAll('.carousel-slide');
            
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
            
            // Changer de slide toutes les 5 secondes
            setInterval(nextSlide, 5000);
        });
    </script>
</body>
</html>