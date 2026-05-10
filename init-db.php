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
$db->exec('CREATE TABLE IF NOT EXISTS share_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_value TEXT UNIQUE NOT NULL,
    album_identifier TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    comment TEXT,
    FOREIGN KEY (album_identifier) REFERENCES album_identifiers(identifier)
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

// Créer les index nécessaires
$db->exec('CREATE INDEX IF NOT EXISTS idx_share_keys_expires_at ON share_keys(expires_at)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_share_keys_album_identifier ON share_keys(album_identifier)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_album_identifiers_identifier ON album_identifiers(identifier)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_admin_logs_admin_id ON admin_logs(admin_id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_admin_logs_created_at ON admin_logs(created_at)');

$db->close();
echo "Base de données initialisée avec succès !";
?>