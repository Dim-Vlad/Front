<?php
ob_start();
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'ID invalide.']);
    exit;
}

$pdo  = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM entrainements WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    ob_end_clean();
    http_response_code(404);
    echo json_encode(['error' => 'Introuvable.']);
    exit;
}

ob_end_clean();
echo json_encode(['success' => true, 'data' => $row]);
