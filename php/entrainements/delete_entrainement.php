<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    http_response_code(403); ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']); exit;
}
check_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'ID invalide.']); exit; }

$pdo  = get_pdo();
$stmt = $pdo->prepare('SELECT jour, horaire FROM entrainements WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); ob_end_clean(); echo json_encode(['error' => 'Créneau introuvable.']); exit; }

$pdo->prepare('DELETE FROM entrainements WHERE id = ?')->execute([$id]);
log_activite($pdo, 'suppression', 'entrainement', "Suppression créneau {$row['jour']} {$row['horaire']}");

ob_end_clean(); echo json_encode(['success' => true]);
