<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    http_response_code(403); echo json_encode(['error' => 'Accès refusé.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Méthode non autorisée.']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID invalide.']); exit; }

$pdo   = get_pdo();
$check = $pdo->prepare('SELECT nom, logo FROM partenaires WHERE id = ?');
$check->execute([$id]);
$row = $check->fetch();
if (!$row) { http_response_code(404); echo json_encode(['error' => 'Partenaire introuvable.']); exit; }

if ($row['logo'] && str_starts_with($row['logo'], '/photos/partenaires/')) {
    $filePath = __DIR__ . '/..' . $row['logo'];
    if (file_exists($filePath)) unlink($filePath);
}

$pdo->prepare('DELETE FROM partenaires WHERE id = ?')->execute([$id]);
log_activite($pdo, 'suppression', 'partenaire', "Suppression du partenaire « {$row['nom']} »");

echo json_encode(['success' => true]);
