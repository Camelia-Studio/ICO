<?php
// images.php
require_once 'fonctions.php';
session_start();

$path = $_GET['path'] ?? '';
$key = $_GET['key'] ?? '';
$adminSession = $_GET['admin_session'] ?? '';

// Vérifier que le chemin est valide et dans un album privé
if (!isSecurePrivatePath($path) || !file_exists($path)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Vérifier l'authentification (admin ou clé de partage valide)
if ($adminSession) {
    session_id($adminSession);
    session_start();
    if (!isset($_SESSION['admin_id'])) {
        header("HTTP/1.0 403 Forbidden");
        exit;
    }
} else {
    if (!$key || !validateShareKey($key)) {
        header("HTTP/1.0 403 Forbidden");
        exit;
    }
}

// Servir l'image avec le bon Content-Type
$mime = mime_content_type($path);
header("Content-Type: $mime");
readfile($path);