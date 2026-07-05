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
check_csrf();

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

$pdo   = get_pdo();
$check = $pdo->prepare('SELECT nom, photo FROM equipes WHERE id = ?');
$check->execute([$id]);
$row   = $check->fetch();

if (!$row) {
    http_response_code(404);
    ob_end_clean(); echo json_encode(['error' => 'Équipe introuvable.']);
    exit;
}

$photo = $row['photo'];
if ($photo && str_starts_with($photo, '/photos/equipes/')) {
    $filePath = __DIR__ . '/../..' . $photo;
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

$pdo->prepare('DELETE FROM equipes WHERE id = ?')->execute([$id]);

log_activite($pdo, 'suppression', 'equipe', "Suppression de « {$row['nom']} »");

ob_end_clean(); echo json_encode(['success' => true]);
