<?php
/**
 * Vue : grille des albums (sous-dossiers).
 *
 * Variables :
 *   array<int, array{
 *     path: string, title: string, description: string,
 *     images: array<mixed>, hasSubfolders: bool, hasImages: bool, mature_content: bool
 *   }>  $albums              Liste des sous-albums
 *   array{title: string, description: string} $current_album_info  Infos de l'album courant
 *   string|null $parent_path   Chemin du parent (null = racine)
 *   list<array{label: string, url: string|null}> $breadcrumbs  Fil d'Ariane
 *   string      $site_title    Titre du site
 *   string      $version       Version pour le footer
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var array<int, array<string, mixed>> $albums */
/** @var array<string, mixed> $current_album_info */
/** @var string|null $parent_path */
/** @var list<array{label: string, url: string|null}> $breadcrumbs */
/** @var string $site_title */
/** @var string $version */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($current_album_info['title']); ?> - <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="albums-page">
    <?php $renderer->renderLayout('partials/breadcrumb', ['breadcrumbs' => $breadcrumbs]); ?>

    <div class="album-info">
        <h1 class="current-album-title"><?php echo htmlspecialchars($current_album_info['title']); ?></h1>
        <?php if (!empty($current_album_info['description'])): ?>
            <p><?php echo nl2br(htmlspecialchars($current_album_info['description'])); ?></p>
        <?php endif; ?>
    </div>

    <div class="albums-grid">
        <?php foreach ($albums as $album): ?>
        <a href="<?php echo $album['hasSubfolders'] ? 'albums.php' : 'galeries.php'; ?>?path=<?php echo urlencode($album['path']); ?>"
           class="album-card<?php echo $album['mature_content'] ? ' album-card-mature' : ''; ?>"
           <?php if ($album['mature_content']): ?>data-mature-warning="Contenu réservé aux plus de 18 ans"<?php endif; ?>>
            <div class="album-images">
                <?php if (empty($album['images'])): ?>
                    <div class="empty-album"></div>
                <?php else: ?>
                    <?php foreach ($album['images'] as $index => $image): ?>
                        <div class="album-image">
                            <div class="image-background <?php echo is_array($image) && $image['is_mature'] ? 'mature-preview' : ''; ?>"
                                style="background-image: url('<?php echo htmlspecialchars(is_array($image) ? $image['url'] : $image); ?>')">
                            </div>
                            <?php if (is_array($image) && $image['is_mature']): ?>
                                <div class="mature-preview-indicator">🔞</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php for ($i = count($album['images']); $i < 4; $i++): ?>
                        <div class="empty-image"></div>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
            <div class="album-info">
                <h2><?php echo htmlspecialchars($album['title']); ?></h2>
                <?php if (!empty($album['description'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($album['description'])); ?></p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <button class="scroll-top" title="Retour en haut">↑</button>
    <script src="js/albums.js"></script>
    <script src="js/scroll-top.js"></script>
    <?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
</body>
</html>
