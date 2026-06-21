<?php
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID invalide.']);
    exit;
}

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id FROM equipes WHERE id = ?');
$check->execute([$id]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Équipe introuvable.']);
    exit;
}

$coach = trim($_POST['coach'] ?? '');
$lien  = trim($_POST['lien']  ?? '');

$photoPath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['photo']['tmp_name'];
    $type = mime_content_type($tmp);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!in_array($type, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Type de fichier non autorisé (jpg, png, webp, gif).']);
        exit;
    }

    $ext = match($type) {
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };

    $filename  = $id . '.' . $ext;
    $uploadDir = __DIR__ . '/../photos/equipes/';
    if (!move_uploaded_file($tmp, $uploadDir . $filename)) {
        http_response_code(500);
        echo json_encode(['error' => "Erreur lors de l'enregistrement du fichier."]);
        exit;
    }
    $photoPath = '/photos/equipes/' . $filename;
}

if ($photoPath !== null) {
    $pdo->prepare('UPDATE equipes SET coach = ?, lien = ?, photo = ? WHERE id = ?')
        ->execute([$coach, $lien, $photoPath, $id]);
} else {
    $pdo->prepare('UPDATE equipes SET coach = ?, lien = ? WHERE id = ?')
        ->execute([$coach, $lien, $id]);
}

$row = $pdo->prepare('SELECT coach, lien, photo FROM equipes WHERE id = ?');
$row->execute([$id]);
echo json_encode(['success' => true, 'data' => $row->fetch()]);