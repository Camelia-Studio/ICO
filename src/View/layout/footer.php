<?php
/**
 * Layout footer — fin de page HTML commun aux pages admin.
 *
 * Variables attendues (injectées via ViewRenderer::renderLayout) :
 *   string                         $version      Version courante de l'application
 *   array<int,array<string,mixed>> $social_links Liens sociaux actifs (global injecté depuis index.php)
 */

/** @var string $version */
/** @var array<int, array<string, mixed>> $social_links */

$social_links = $social_links ?? [];
?>
<footer class="site-footer">
    <?php if (!empty($social_links)): ?>
    <div class="social-links">
        <?php foreach ($social_links as $link): ?>
        <a href="<?php echo htmlspecialchars((string) $link['url']); ?>"
           class="social-link"
           target="_blank"
           rel="noopener noreferrer">
            <?php echo htmlspecialchars((string) $link['label']); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="footer-text">
        Site propulsé grâce à <a href="https://git.crystalyx.net/camelia-studio/ICO" target="_blank" class="footer-link">ICO</a>
        <span class="footer-version">version <?php echo htmlspecialchars($version); ?></span>
    </p>
</footer>
</body>
</html>
