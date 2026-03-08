<?php
/**
 * Vue : galerie privée (accès par clé de partage).
 *
 * Variables :
 *   string|null $error_title    Titre d'erreur (non-null si accès refusé)
 *   string|null $error_message  Message d'erreur
 *   array<string, mixed>|null $album_data   Infos de l'album (null si erreur)
 *   list<array{url: string, share_url: string, is_top: bool, aspect_ratio: float}> $images
 *   string|null $header_image   URL de la première image (ou null)
 *   string      $share_key      Clé de partage active
 *   string      $site_title     Titre du site
 *   string      $version        Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string|null $error_title */
/** @var string|null $error_message */
/** @var array<string, mixed>|null $album_data */
/** @var list<array{url: string, is_top: bool, aspect_ratio: float}> $images */
/** @var string|null $header_image */
/** @var string $share_key */
/** @var string $site_title */
/** @var string $version */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $album_data !== null ? htmlspecialchars($album_data['title']) : htmlspecialchars((string) $error_title); ?> - <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="gallery-page<?php echo ($album_data !== null && $album_data['mature_content']) ? ' gallery-page-mature content-blurred' : ''; ?>">
    <?php if ($error_title !== null): ?>
    <div class="error-container">
        <div class="error-content">
            <h1><?php echo htmlspecialchars($error_title); ?></h1>
            <p><?php echo htmlspecialchars((string) $error_message); ?></p>
            <a href="index.php" class="action-button">Retour à l'accueil</a>
        </div>
    </div>

    <?php else: ?>
    <?php if ($album_data['mature_content']): ?>
    <div id="mature-warning" class="mature-overlay">
        <div class="mature-content">
            <div class="mature-icon">🔞</div>
            <h2>Cet album contient du contenu réservé à un public averti.</h2>
            <button onclick="acceptMatureContent()" class="mature-button">J'ai plus de 18 ans - Afficher le contenu</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="private-gallery-badge">
        <span class="private-icon">🔒</span> Album privé
    </div>

    <?php if ($header_image !== null): ?>
    <div class="gallery-header">
        <img src="<?php echo htmlspecialchars($header_image); ?>" alt="Image principale" class="header-image">
    </div>
    <?php endif; ?>

    <div class="gallery-info">
        <h1><?php echo htmlspecialchars($album_data['title']); ?></h1>
        <?php if (!empty($album_data['description'])): ?>
        <p><?php echo nl2br(htmlspecialchars($album_data['description'])); ?></p>
        <?php endif; ?>
        <?php if ($album_data['mature_content']): ?>
        <div class="mature-badge">
            <span class="mature-badge-icon">🔞</span>
            Contenu réservé aux plus de 18 ans
        </div>
        <?php endif; ?>
        <?php if (!empty($album_data['more_info_url'])): ?>
        <div class="more-info-button">
            <a href="<?php echo htmlspecialchars($album_data['more_info_url']); ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="action-button">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12" y2="8"/>
                </svg>
                En savoir plus sur <?php echo htmlspecialchars($album_data['title']); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="gallery-grid">
        <?php foreach ($images as $image):
            $spanClass = '';
            if ($image['aspect_ratio'] > 1.7) {
                $spanClass = 'gallery-item-wide';
            } elseif ($image['aspect_ratio'] < 0.7) {
                $spanClass = 'gallery-item-tall';
            }
        ?>
        <div class="gallery-item <?php echo $image['is_top'] ? 'gallery-item-top' : ''; ?> <?php echo $spanClass; ?>">
            <a href="<?php echo htmlspecialchars($image['share_url']); ?>" target="_blank">
                <img src="<?php echo htmlspecialchars($image['url']); ?>"
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
            setTimeout(() => { warning.style.display = 'none'; }, 300);
        }
    }
    </script>
    <?php endif; ?>
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
    <?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
</body>
</html>
