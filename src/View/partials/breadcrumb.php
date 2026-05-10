<?php
/**
 * Partial : fil d'Ariane.
 *
 * Variables :
 *   list<array{label: string, url: string|null}> $breadcrumbs
 *     Chaque entrée : label affiché, url (null = élément courant, sans lien).
 */

/** @var list<array{label: string, url: string|null}> $breadcrumbs */
?>
<nav class="breadcrumb" aria-label="Fil d'Ariane">
    <?php foreach ($breadcrumbs as $i => $crumb): ?>
        <?php if ($i > 0): ?><span class="breadcrumb-separator" aria-hidden="true">/</span><?php endif; ?>
        <?php if ($crumb['url'] !== null): ?>
            <a href="<?php echo htmlspecialchars($crumb['url']); ?>" class="breadcrumb-item"><?php echo htmlspecialchars($crumb['label']); ?></a>
        <?php else: ?>
            <span class="breadcrumb-item breadcrumb-current" aria-current="page"><?php echo htmlspecialchars($crumb['label']); ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
