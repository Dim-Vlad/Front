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

$nom    = trim($_POST['nom']    ?? '');
$groupe = trim($_POST['groupe'] ?? '');
$niveau = trim($_POST['poule']  ?? '');
$coach  = trim($_POST['coach']  ?? '');
$lien   = trim($_POST['lien']   ?? '');

if ($nom === '' || !in_array($groupe, ['seniors', 'jeunes'], true)) {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'Nom et groupe requis.']);
    exit;
}

$pdo = get_pdo();

$maxOrdre = $pdo->prepare('SELECT COALESCE(MAX(ordre), 0) FROM equipes WHERE groupe = ?');
$maxOrdre->execute([$groupe]);
$ordre = (int)$maxOrdre->fetchColumn() + 1;

$pdo->prepare('INSERT INTO equipes (nom, groupe, niveau, coach, lien, photo, ordre) VALUES (?, ?, ?, ?, ?, ?, ?)')
    ->execute([$nom, $groupe, $niveau, $coach, $lien, '', $ordre]);
$newId = (int)$pdo->lastInsertId();

$photoPath  = '';
$genericUrl = trim($_POST['photo_url_generic'] ?? '');

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $tmp     = $_FILES['photo']['tmp_name'];
    $type    = mime_content_type($tmp);
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

    $filename  = $newId . '.' . $ext;
    $uploadDir = __DIR__ . '/../../photos/equipes/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (!move_uploaded_file($tmp, $uploadDir . $filename)) {
        http_response_code(500);
        ob_end_clean(); echo json_encode(['error' => "Erreur lors de l'enregistrement du fichier."]);
        exit;
    }
    $photoPath = '/photos/equipes/' . $filename;
    $pdo->prepare('UPDATE equipes SET photo = ? WHERE id = ?')->execute([$photoPath, $newId]);
} elseif ($genericUrl !== '') {
    $photoPath = $genericUrl;
    $pdo->prepare('UPDATE equipes SET photo = ? WHERE id = ?')->execute([$photoPath, $newId]);
}

log_activite($pdo, 'ajout', 'equipe', "Ajout de « {$nom} » dans {$groupe}");

$row = $pdo->prepare('SELECT * FROM equipes WHERE id = ?');
$row->execute([$newId]);
ob_end_clean(); echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
