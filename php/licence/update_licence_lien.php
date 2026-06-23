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

$id    = (int)($_POST['id'] ?? 0);
$label = trim($_POST['label'] ?? '');
$url   = trim($_POST['url']   ?? '');

if ($id <= 0 || $label === '') {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'ID et libellé requis.']); exit;
}

$pdo  = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM licence_liens WHERE id = ?');
$stmt->execute([$id]);
$lien = $stmt->fetch();
if (!$lien) { http_response_code(404); ob_end_clean(); echo json_encode(['error' => 'Lien introuvable.']); exit; }

$pdo->prepare('UPDATE licence_liens SET label = ?, url = ? WHERE id = ?')
    ->execute([$label, $url, $id]);

log_activite($pdo, 'modification', 'licence_lien', "Modification du lien « {$lien['slug']} » → URL : {$url}");

$row = $pdo->prepare('SELECT * FROM licence_liens WHERE id = ?');
$row->execute([$id]);
ob_end_clean(); echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
