<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

// Même public que la page : tout utilisateur autorisé peut proposer une ressource,
// pas seulement l'admin/modérateur (contrairement aux formations).
if (!is_logged_in() || !has_any_role(['admin', 'moderateur', 'bureau', 'entraineur', 'arbitre'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}
check_csrf();

$titre       = trim($_POST['titre'] ?? '');
$description = trim($_POST['description'] ?? '');
$type        = in_array($_POST['type'] ?? '', ['video', 'image', 'pdf'], true) ? $_POST['type'] : 'video';

if ($titre === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Titre requis']); exit;
}
if (mb_strlen($titre) > 150) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Titre trop long (150 caractères max)']); exit;
}

$url = '';

if ($type === 'video') {
    $url = trim($_POST['url'] ?? '');
    if ($url === '' || !preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Lien invalide : il doit commencer par http:// ou https://']); exit;
    }
} elseif ($type === 'image') {
    $file = $_FILES['fichier'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Aucune image reçue ou fichier trop volumineux']); exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => "L'image ne doit pas dépasser 5 Mo"]); exit;
    }
    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($exts[$mime])) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Format non autorisé (jpg, png, webp, gif)']); exit;
    }
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/images/espace-entraineur/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $nom = 'vid-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $exts[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $nom)) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => "Erreur d'enregistrement de l'image"]); exit;
    }
    $url = '/images/espace-entraineur/' . $nom;
} else { // pdf
    $file = $_FILES['fichier'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu ou fichier trop volumineux']); exit;
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Le PDF ne doit pas dépasser 10 Mo']); exit;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf' || mime_content_type($file['tmp_name']) !== 'application/pdf') {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Format PDF uniquement']); exit;
    }
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/documents/espace-entraineur/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $nom = 'vid-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $dir . $nom)) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => "Erreur d'enregistrement du fichier"]); exit;
    }
    $url = '/documents/espace-entraineur/' . $nom;
}

try {
    $pdo = get_pdo();
    $me  = current_user();
    $pdo->prepare('INSERT INTO videos_entrainement (type, titre, url, description, user_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([$type, $titre, $url, $description, (int)$me['id']]);
    $id = (int)$pdo->lastInsertId();
    log_activite($pdo, 'ajout', 'video_entrainement', "Partage de « {$titre} »");

    ob_end_clean();
    echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
