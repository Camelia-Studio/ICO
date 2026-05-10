<?php
/**
 * Vue publique d'une page "en savoir plus".
 *
 * Variables :
 *   array<string, mixed>|null $page        Page à afficher (null = 404)
 *   string $site_title   Titre du site
 *   string $version      Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var array<string, mixed>|null $page */
/** @var string $site_title */
/** @var string $version */

$pageTitle = $page !== null ? (string) $page['title'] : 'Page introuvable';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="albums-page">
    <a href="index.php" class="back-button">Retour</a>

    <div class="album-info" style="max-width:800px; margin:6rem auto 2rem; padding:0 1.5rem;">
        <?php if ($page !== null): ?>
            <h1 class="current-album-title"><?php echo htmlspecialchars((string) $page['title']); ?></h1>
            <div class="page-content" style="line-height:1.7;">
                <?php echo $page['content']; ?>
            </div>
        <?php else: ?>
            <h1 class="current-album-title">Page introuvable</h1>
            <p>Cette page n'existe pas ou n'est plus disponible.</p>
            <p><a href="index.php">Retour à l'accueil</a></p>
        <?php endif; ?>
    </div>

    <?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
</body>
</html>
