<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    http_response_code(403);
    ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'ID invalide.']);
    exit;
}

$pdo = get_pdo();
$check = $pdo->prepare('SELECT id, nom FROM equipes WHERE id = ?');
$check->execute([$id]);
$equipe = $check->fetch();
if (!$equipe) {
    http_response_code(404);
    ob_end_clean(); echo json_encode(['error' => 'Équipe introuvable.']);
    exit;
}

$poule = trim($_POST['poule'] ?? '');
$coach  = trim($_POST['coach'] ?? '');
$lien   = trim($_POST['lien']  ?? '');

$photoPath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['photo']['tmp_name'];
    $type = mime_content_type($tmp);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!in_array($type, $allowed, true)) {
        http_response_code(400);
        ob_end_clean(); echo json_encode(['error' => 'Type de fichier non autorisé (jpg, png, webp, gif).']);
        exit;
    }

    $ext = match($type) {
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };

    $filename  = $id . '.' . $ext;
    $uploadDir = __DIR__ . '/../../photos/equipes/';
    if (!move_uploaded_file($tmp, $uploadDir . $filename)) {
        http_response_code(500);
        ob_end_clean(); echo json_encode(['error' => "Erreur lors de l'enregistrement du fichier."]);
        exit;
    }
    $photoPath = '/photos/equipes/' . $filename;
}

if ($photoPath !== null) {
    $pdo->prepare('UPDATE equipes SET niveau = ?, coach = ?, lien = ?, photo = ? WHERE id = ?')
        ->execute([$poule, $coach, $lien, $photoPath, $id]);
} else {
    $pdo->prepare('UPDATE equipes SET niveau = ?, coach = ?, lien = ? WHERE id = ?')
        ->execute([$poule, $coach, $lien, $id]);
}

$parts = ["poule : {$poule}", "coach : {$coach}"];
if ($photoPath !== null) $parts[] = 'photo mise à jour';
log_activite($pdo, 'modification', 'equipe', "Modification de « {$equipe['nom']} » — " . implode(', ', $parts));

$row = $pdo->prepare('SELECT niveau AS poule, coach, lien, photo FROM equipes WHERE id = ?');
$row->execute([$id]);
ob_end_clean(); echo json_encode(['success' => true, 'data' => $row->fetch()]);