<?php
require_once 'fonctions.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php?action=login');
    exit;
}

// Gérer les soumissions du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteTitle = $_POST['site_title'] ?? '';
    $siteDescription = $_POST['site_description'] ?? '';
    
    // Vérifications basiques
    if (empty($siteTitle)) {
        $_SESSION['error_message'] = "Le titre du site est requis.";
    } else {
        // Sauvegarder la configuration
        $configContent = $siteTitle . "\n" . $siteDescription;
        
        if (file_put_contents('./config.txt', $configContent) !== false) {
            $_SESSION['success_message'] = "Configuration mise à jour avec succès.";
        } else {
            $_SESSION['error_message'] = "Erreur lors de la sauvegarde de la configuration.";
        }
    }
    
    header('Location: personnalisation.php');
    exit;
}

// Récupérer la configuration actuelle
$config = getSiteConfig();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnalisation - <?php echo htmlspecialchars($config['site_title']); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
</head>
<body class="admin-page">
    <div class="admin-header">
        <h1>Personnalisation du site</h1>
        <div class="admin-actions">
            <a href="admin.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success-message"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <form method="post" action="personnalisation.php" class="form-container">
            <div class="form-group">
                <label for="site_title">Titre du site :</label>
                <input type="text" id="site_title" name="site_title" required 
                       value="<?php echo htmlspecialchars($config['site_title']); ?>">
                <small class="form-help">Ce titre apparaîtra dans l'en-tête des pages et la barre de titre du navigateur.</small>
            </div>

            <div class="form-group">
                <label for="site_description">Description du site :</label>
                <textarea id="site_description" name="site_description" rows="4" 
                          class="form-textarea"><?php echo htmlspecialchars($config['site_description']); ?></textarea>
                <small class="form-help">Cette description apparaît sur la page d'accueil du site.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="action-button">Enregistrer les modifications</button>
            </div>
        </form>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>