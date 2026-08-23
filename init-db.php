<?php
$db_file = 'database.sqlite';
$db = new SQLite3($db_file);

// Créer la table des administrateurs
$db->exec('CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Créer la nouvelle table des clés de partage
$db->exec("CREATE TABLE IF NOT EXISTS share_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_value TEXT UNIQUE NOT NULL,
    album_identifier TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    comment TEXT,
    options TEXT NOT NULL DEFAULT '{\"download\":true,\"source\":true,\"share\":true}',
    FOREIGN KEY (album_identifier) REFERENCES album_identifiers(identifier)
)");

// Migration : ajout de la colonne options si elle n'existe pas encore
$columns = [];
$result = $db->query('PRAGMA table_info(share_keys)');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $columns[] = $row['name'];
}
if (!in_array('options', $columns)) {
    $db->exec("ALTER TABLE share_keys ADD COLUMN options TEXT NOT NULL DEFAULT '{\"download\":true,\"source\":true,\"share\":true}'");
}

// Créer la table de liaison des comptes Vestikan (SSO)
$db->exec('CREATE TABLE IF NOT EXISTS vestikan_links (
    vestikan_id   TEXT PRIMARY KEY,
    local_user_id TEXT NOT NULL,
    created_at    INTEGER NOT NULL
)');

// Créer la table des identifiants d'albums
$db->exec('CREATE TABLE IF NOT EXISTS album_identifiers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier TEXT UNIQUE NOT NULL,
    path TEXT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Insérer un admin par défaut si la table est vide
$result = $db->query('SELECT COUNT(*) as count FROM admins');
$count = $result->fetchArray()['count'];

if ($count === 0) {
    // Créer un admin par défaut (admin/admin) - À changer après la première connexion !
    $default_username = 'admin';
    $default_password = 'admin';
    $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
    $stmt->bindValue(':username', $default_username, SQLITE3_TEXT);
    $stmt->bindValue(':password_hash', $password_hash, SQLITE3_TEXT);
    $stmt->execute();
    
    echo "Admin par défaut créé (username: {$default_username}, password: {$default_password}). Pensez à changer ces identifiants !\n";
}

// Après la création des tables existantes, ajouter :
$db->exec('CREATE TABLE IF NOT EXISTS admin_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER NOT NULL,
    action_type TEXT NOT NULL,
    action_description TEXT NOT NULL,
    target_path TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id)
)');

// Créer la table des pages "en savoir plus"
$db->exec('CREATE TABLE IF NOT EXISTS info_pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    content TEXT NOT NULL DEFAULT \'\',
    is_published INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Créer la table des liens sociaux
$db->exec('CREATE TABLE IF NOT EXISTS social_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    url TEXT NOT NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Créer la table de l'ordre manuel des images du carrousel
$db->exec('CREATE TABLE IF NOT EXISTS carousel_positions (
    filename TEXT PRIMARY KEY,
    position INTEGER NOT NULL
)');

// Créer les index nécessaires
$db->exec('CREATE INDEX IF NOT EXISTS idx_share_keys_expires_at ON share_keys(expires_at)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_share_keys_album_identifier ON share_keys(album_identifier)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_album_identifiers_identifier ON album_identifiers(identifier)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_admin_logs_admin_id ON admin_logs(admin_id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_admin_logs_created_at ON admin_logs(created_at)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_info_pages_slug ON info_pages(slug)');

$db->close();
echo "Base de données initialisée avec succès !";
?>