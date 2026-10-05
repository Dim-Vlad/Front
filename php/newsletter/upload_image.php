<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']); exit;
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu ou fichier trop volumineux.']); exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'L\'image ne doit pas dépasser 5 Mo.']); exit;
}

$exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$mime = mime_content_type($file['tmp_name']);
if (!isset($exts[$mime])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Format non autorisé (jpg, png, webp, gif).']); exit;
}

$dir = __DIR__ . '/../../images/newsletter/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$nom = 'nl-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $exts[$mime];
if (!move_uploaded_file($file['tmp_name'], $dir . $nom)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Impossible d\'enregistrer l\'image sur le serveur.']); exit;
}

ob_end_clean(); echo json_encode(['success' => true, 'url' => '/images/newsletter/' . $nom]);
