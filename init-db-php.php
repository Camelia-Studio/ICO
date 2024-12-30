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

// Créer la table des albums protégés
$db->exec('CREATE TABLE IF NOT EXISTS protected_albums (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    path TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
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
    
    echo "Admin par défaut créé (username: admin, password: admin). Pensez à changer ces identifiants !";
}

$db->close();
echo "Base de données initialisée avec succès !";
?>