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
if ($id <= 0) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'ID requis.']); exit;
}

$pdo  = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM licence_documents WHERE id = ?');
$stmt->execute([$id]);
$doc  = $stmt->fetch();
if (!$doc) { http_response_code(404); ob_end_clean(); echo json_encode(['error' => 'Document introuvable.']); exit; }

if ($doc['path'] !== '') {
    $filePath = __DIR__ . '/../../' . ltrim($doc['path'], '/');
    if (is_file($filePath)) unlink($filePath);
}

if (($_POST['ligne'] ?? '') === '1') {
    $pdo->prepare('DELETE FROM licence_documents WHERE id = ?')->execute([$id]);
    log_activite($pdo, 'suppression', 'licence_document', "Suppression du document « {$doc['label']} » ({$doc['slug']})");
    ob_end_clean(); echo json_encode(['success' => true, 'deleted' => true, 'id' => $id]); exit;
}

$pdo->prepare('UPDATE licence_documents SET path = ? WHERE id = ?')->execute(['', $id]);

log_activite($pdo, 'suppression', 'licence_document', "Fichier retiré du document « {$doc['slug']} »");

$row = $pdo->prepare('SELECT * FROM licence_documents WHERE id = ?');
$row->execute([$id]);
ob_end_clean(); echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
