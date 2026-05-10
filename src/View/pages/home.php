<?php
/**
 * Vue : page d'accueil avec carousel et overlay.
 *
 * Variables :
 *   string[] $carousel_images   URLs publiques des images du carousel
 *   string   $site_title        Titre du site
 *   string   $site_description  Description du site
 *   string   $version           Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string[] $carousel_images */
/** @var string $site_title */
/** @var string $site_description */
/** @var string $version */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_title); ?> - <?php echo htmlspecialchars($site_description); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <a href="admin.php" class="admin-button" title="Administration">⚙️</a>
    <div class="carousel">
        <?php foreach ($carousel_images as $index => $image): ?>
            <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>">
                <img src="<?php echo htmlspecialchars($image); ?>" alt="Image du carrousel">
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($carousel_images) > 1): ?>
    <div class="carousel-indicators">
        <?php foreach ($carousel_images as $index => $image): ?>
            <div class="indicator <?php echo $index === 0 ? 'active' : ''; ?>"></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="overlay">
        <h1><?php echo htmlspecialchars($site_title); ?></h1>
        <p><?php echo htmlspecialchars($site_description); ?></p>
        <a href="albums.php" class="cta-button">Accéder aux galeries</a>
    </div>

    <?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>

    <script src="js/carousel.js"></script>
</body>
</html>
