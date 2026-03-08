<?php
/**
 * Partial messages — affiche et efface les messages flash de session.
 *
 * Gère success_message et error_message stockés en $_SESSION.
 * Utilise nl2br pour les messages multi-lignes (ex: erreurs d'upload).
 *
 * Variables optionnelles :
 *   bool $nlBr   Si true, applique nl2br (défaut : false)
 */

/** @var bool $nlBr */
$nlBr = $nlBr ?? false;
?>
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="message success-message">
        <?php
        $msg = htmlspecialchars($_SESSION['success_message']);
        echo $nlBr ? nl2br($msg) : $msg;
        unset($_SESSION['success_message']);
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="message error-message">
        <?php
        $msg = htmlspecialchars($_SESSION['error_message']);
        echo $nlBr ? nl2br($msg) : $msg;
        unset($_SESSION['error_message']);
        ?>
    </div>
<?php endif; ?>
