<?php
/**
 * Layout header — début de page HTML commun aux pages admin.
 *
 * Variables attendues (injectées via ViewRenderer::renderLayout) :
 *   string   $pageTitle   Titre de l'onglet (sans suffixe)
 *   string[] $extraCss    (optionnel) Feuilles CSS supplémentaires à charger
 *   string   $bodyClass   (optionnel) Classe(s) CSS du <body>, défaut : 'admin-page'
 *   string   $dataPage    (optionnel) Valeur de l'attribut data-page du <body>
 */

/** @var string   $pageTitle */
/** @var string[] $extraCss */
/** @var string   $bodyClass */
/** @var string   $dataPage */

$bodyClass = $bodyClass ?? 'admin-page';
$extraCss  = $extraCss  ?? [];
$dataPage  = $dataPage  ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-admin.css">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
    <?php endforeach; ?>
</head>
<body class="<?php echo htmlspecialchars($bodyClass); ?>"<?php echo $dataPage !== '' ? ' data-page="' . htmlspecialchars($dataPage) . '"' : ''; ?>>
