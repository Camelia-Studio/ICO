<?php
/**
 * Vue : page de changement de mot de passe.
 *
 * Variables :
 *   string $version   Version de l'application (pour le footer)
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', ['pageTitle' => 'Changer le mot de passe - ICO']); ?>
    <div class="admin-header">
        <h1>Changer le mot de passe</h1>
        <a href="admin.php" class="action-button action-button-secondary">Retour</a>
    </div>
    <div class="admin-content">
        <?php $renderer->renderLayout('partials/messages'); ?>

        <form method="post" action="admin.php?action=change_password" class="form-container">
            <div class="form-group">
                <label for="current_password">Mot de passe actuel :</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe :</label>
                <input type="password" id="new_password" name="new_password" required minlength="12">
                <small class="form-help">
                    Le mot de passe doit contenir au moins :
                    <ul>
                        <li>12 caractères</li>
                        <li>1 lettre minuscule</li>
                        <li>1 lettre majuscule</li>
                        <li>1 chiffre</li>
                        <li>1 caractère spécial</li>
                    </ul>
                </small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe :</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="12">
            </div>
            <div class="form-actions">
                <button type="submit" class="action-button">Changer le mot de passe</button>
            </div>
        </form>
    </div>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
