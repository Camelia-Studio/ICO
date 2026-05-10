<?php
/**
 * Vue : page de partage/visualisation d'une image.
 *
 * Variables :
 *   string $image_url         URL de l'image à afficher
 *   string $filename          Nom du fichier pour le téléchargement
 *   bool   $is_private_image  True si image privée (masque le bouton "Intégrer")
 *   string $site_title        Titre du site
 *   string $version           Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string $image_url */
/** @var string $filename */
/** @var bool $is_private_image */
/** @var string $site_title */
/** @var string $version */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image - <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="share-page">
    <button class="back-button">Retour</button>

    <div class="share-container">
        <div class="share-image">
            <img src="<?php echo htmlspecialchars($image_url); ?>"
                 data-image-url="<?php echo htmlspecialchars($image_url); ?>"
                 alt="Image partagée">
        </div>

        <div class="share-actions">
            <button class="action-button" onclick="shareImage()">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                    <polyline points="16 6 12 2 8 6"></polyline>
                    <line x1="12" y1="2" x2="12" y2="15"></line>
                </svg>
                Partager
            </button>

            <?php if (!$is_private_image): ?>
            <button class="action-button" onclick="embedImage()">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="16 18 22 12 16 6"></polyline>
                    <polyline points="8 6 2 12 8 18"></polyline>
                </svg>
                Intégrer
            </button>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($image_url); ?>"
               download="<?php echo htmlspecialchars($filename); ?>"
               class="action-button">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Télécharger
            </a>

            <a href="https://saucenao.com/search.php?url=<?php echo urlencode($image_url); ?>"
               target="_blank"
               class="action-button">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    <line x1="11" y1="8" x2="11" y2="14"></line>
                    <line x1="8" y1="11" x2="14" y2="11"></line>
                </svg>
                Source ?
            </a>
        </div>
    </div>

    <?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>

    <script src="js/share.js"></script>
</body>
</html>
