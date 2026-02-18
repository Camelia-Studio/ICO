<?php
/**
 * Vue : page de connexion admin.
 *
 * Variables :
 *   string|null $error     Message d'erreur à afficher (null si aucun)
 *   string      $version   Version de l'application (pour le footer)
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string|null $error */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', ['pageTitle' => 'Connexion - ICO']); ?>
    <div class="admin-login">
        <h1>Connexion</h1>
        <?php if ($error !== null): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="admin.php?action=login">
            <div class="form-group">
                <label for="username">Identifiant :</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="action-button">Se connecter</button>
        </form>
    </div>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
