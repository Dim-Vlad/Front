<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_role('admin')) {
    http_response_code(403);
    ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']);
    exit;
}
check_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'ID invalide.']); exit; }

$pdo   = get_pdo();
$check = $pdo->prepare('SELECT id, nom FROM reseaux_sociaux WHERE id = ?');
$check->execute([$id]);
$existing = $check->fetch();
if (!$existing) { http_response_code(404); ob_end_clean(); echo json_encode(['error' => 'Réseau introuvable.']); exit; }

$nom = trim($_POST['nom'] ?? '');
$url = trim($_POST['url'] ?? '');
if ($nom === '' || $url === '') {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Nom et lien requis.']); exit;
}

$logoPath = null;
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $tmp     = $_FILES['logo']['tmp_name'];
    $type    = mime_content_type($tmp);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($type, $allowed, true)) {
        http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Type de fichier non autorisé (JPEG, PNG, WebP ou GIF uniquement).']); exit;
    }
    $ext = match($type) {
        'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', default => 'jpg',
    };
    $uploadDir = __DIR__ . '/../../images/social/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = 'reseau-' . $id . '.' . $ext;
    if (move_uploaded_file($tmp, $uploadDir . $filename)) {
        $logoPath = '/images/social/' . $filename;
    }
}

if ($logoPath !== null) {
    $pdo->prepare('UPDATE reseaux_sociaux SET nom = ?, url = ?, logo = ? WHERE id = ?')
        ->execute([$nom, $url, $logoPath, $id]);
} else {
    $pdo->prepare('UPDATE reseaux_sociaux SET nom = ?, url = ? WHERE id = ?')
        ->execute([$nom, $url, $id]);
}

log_activite($pdo, 'modification', 'reseau_social', "Modification du réseau « {$existing['nom']} »");

$row = $pdo->prepare('SELECT * FROM reseaux_sociaux WHERE id = ?');
$row->execute([$id]);
ob_end_clean(); echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
