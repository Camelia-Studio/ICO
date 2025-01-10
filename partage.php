<?php
require_once 'fonctions.php';

// Vérifier que nous avons une URL d'image
$imageUrl = isset($_GET['image']) ? $_GET['image'] : null;

if (!$imageUrl) {
    header('Location: index.php');
    exit;
}

// Si c'est une image privée
if (strpos($imageUrl, 'images.php') !== false) {
    // On récupère les paramètres de l'URL de l'image
    parse_str(parse_url($imageUrl, PHP_URL_QUERY), $params);
    $path = $params['path'] ?? '';
    $key = $params['key'] ?? '';
    
    if (strpos($path, 'liste_albums_prives') !== false) {
        $isPrivateImage = true;
        if (!isset($_SESSION['admin_id'])) {
            if (!$key || !validateShareKey($key)) {
                header('Location: index.php');
                exit;
            }
        } elseif (isset($_SESSION['admin_id'])) {
            // Pour les admins, on remplace la clé par la session admin
            $imageUrl = preg_replace('/&key=[^&]*/', '', $imageUrl) . '&admin_session=' . session_id();
        }
    }
}

// Récupérer le nom du fichier pour le téléchargement
$filename = basename(parse_url($imageUrl, PHP_URL_PATH));

// Si pas d'image, redirection
if (!$imageUrl) {
    header('Location: index.php');
    exit;
}

// Récupérer le nom du fichier pour le téléchargement
$filename = basename($imageUrl);
$config = getSiteConfig();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image - <?php echo htmlspecialchars($config['site_title']); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="share-page">
    <button onclick="var referrer = document.referrer; if (referrer.includes('galeries.php') || referrer.includes('galeries-privees.php')) { window.close(); } else { window.location.href='index.php'; }" class="back-button">Retour</button>

    <div class="share-container">
        <div class="share-image">
            <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="Image partagée">
        </div>

        <div class="share-actions">
            <button class="action-button" onclick="shareImage()">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                    <polyline points="16 6 12 2 8 6"></polyline>
                    <line x1="12" y1="2" x2="12" y2="15"></line>
                </svg>
                Partager
            </button>

            <?php if (!$isPrivateImage): ?>
            <button class="action-button" onclick="embedImage()">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="16 18 22 12 16 6"></polyline>
                    <polyline points="8 6 2 12 8 18"></polyline>
                </svg>
                Intégrer
            </button>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($imageUrl); ?>" 
               download="<?php echo htmlspecialchars($filename); ?>" 
               class="action-button">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Télécharger
            </a>

            <a href="https://saucenao.com/search.php?url=<?php echo urlencode($imageUrl); ?>" 
                target="_blank" 
                class="action-button">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        <line x1="11" y1="8" x2="11" y2="14"></line>
                        <line x1="8" y1="11" x2="14" y2="11"></line>
                    </svg>
                    Source ?
            </a>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        function copyToClipboard(text, button) {
            const input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);

            // Sauvegarder l'HTML original
            const originalHTML = button.innerHTML;
            
            // Afficher la confirmation
            button.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 6L9 17l-5-5"></path>
                </svg>
                Copié !
            `;
            
            // Restaurer l'état original après 2 secondes
            setTimeout(() => {
                button.innerHTML = originalHTML;
            }, 2000);
        }

        function shareImage() {
            copyToClipboard(window.location.href, document.querySelector('.action-button'));
        }

        function embedImage() {
            const imageUrl = '<?php echo htmlspecialchars($imageUrl); ?>';
            copyToClipboard(imageUrl, document.querySelector('.action-button:nth-child(2)'));
        }
    </script>
</body>
</html>