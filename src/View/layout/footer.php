<?php
/**
 * Layout footer — fin de page HTML commun aux pages admin.
 *
 * Variables attendues (injectées via ViewRenderer::renderLayout) :
 *   string $version   Version courante de l'application
 */

/** @var string $version */
?>
<footer class="site-footer">
    <p class="footer-text">
        Site propulsé grâce à <a href="https://git.crystalyx.net/camelia-studio/ICO" target="_blank" class="footer-link">ICO</a>
        <span class="footer-version">version <?php echo htmlspecialchars($version); ?></span>
    </p>
</footer>
</body>
</html>
