<?php

declare(strict_types=1);

/**
 * Configuration du client SSO Vestikan.
 *
 * Copiez ce fichier en `vestikan-config.php` (à la racine du projet, à côté
 * de config.txt) et renseignez les valeurs obtenues lors de l'enregistrement
 * du site auprès de Vestikan (admin Vestikan > Sites satellites).
 *
 * `vestikan-config.php` contient un secret et ne doit JAMAIS être commité
 * (déjà ignoré via .gitignore). Si ce fichier est absent, le bouton
 * « Se connecter avec Vestikan » reste simplement masqué.
 */
return [
    'base_url'      => 'https://concepts.esenjin.xyz/vestikan',
    'client_id'     => 'vk_client_xxxxxxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxx...(64 caractères hex)...',
    'redirect_uri'  => 'https://example.com/admin.php?action=vestikan_callback',
];
