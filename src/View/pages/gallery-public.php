<?php
/**
 * Vue : galerie publique d'images.
 *
 * Variables :
 *   array{title: string, description: string, mature_content: bool, more_info_url: string} $album_info
 *   list<array{url: string, is_top: bool, aspect_ratio: float}> $images
 *   string|null $header_image   URL de la première image (ou null)
 *   string      $parent_path    Chemin du dossier parent (pour retour)
 *   string      $site_title     Titre du site
 *   string      $version        Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var array<string, mixed> $album_info */
/** @var list<array{url: string, is_top: bool, aspect_ratio: float}> $images */
/** @var string|null $header_image */
/** @var string $parent_path */
/** @var string $site_title */
/** @var string $version */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($album_info['title']); ?> - <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="gallery-page<?php echo $album_info['mature_content'] ? ' gallery-page-mature content-blurred' : ''; ?>">
    <?php if ($album_info['mature_content']): ?>
    <div id="mature-warning" class="mature-overlay">
        <div class="mature-content">
            <div class="mature-icon">🔞</div>
            <h2>Cet album contient du contenu réservé à un public averti.</h2>
            <button onclick="acceptMatureContent()" class="mature-button">J'ai plus de 18 ans - Afficher le contenu</button>
        </div>
    </div>
    <?php endif; ?>

    <a href="albums.php?path=<?php echo urlencode($parent_path); ?>" class="back-button">Retour</a>

    <?php if ($header_image !== null): ?>
    <div class="gallery-header">
        <img src="<?php echo htmlspecialchars($header_image); ?>" alt="Image principale" class="header-image">
    </div>
    <?php endif; ?>

    <div class="gallery-info">
        <h1><?php echo htmlspecialchars($album_info['title']); ?></h1>
        <?php if (!empty($album_info['description'])): ?>
        <p><?php echo nl2br(htmlspecialchars($album_info['description'])); ?></p>
        <?php endif; ?>
        <?php if ($album_info['mature_content']): ?>
        <div class="mature-badge">
            <span class="mature-badge-icon">🔞</span>
            Contenu réservé aux plus de 18 ans
        </div>
        <?php endif; ?>
        <?php if (!empty($album_info['more_info_url'])): ?>
        <div class="more-info-button">
            <a href="<?php echo htmlspecialchars($album_info['more_info_url']); ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="action-button">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12" y2="8"/>
                </svg>
                En savoir plus sur <?php echo htmlspecialchars($album_info['title']); ?>
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
            <a href="partage.php?image=<?php echo urlencode($image['url']); ?>" target="_blank">
                <img src="<?php echo htmlspecialchars($image['url']); ?>"
                     alt="Image de la galerie"
                     loading="lazy">
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    function acceptMatureContent() {
        document.body.classList.remove('gallery-page-mature');
        document.body.classList.remove('content-blurred');
        const warning = document.getElementById('mature-warning');
        if (warning) {
            warning.style.opacity = '0';
            setTimeout(() => { warning.style.display = 'none'; }, 300);
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
    <?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
</body>
</html>
