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

$id1 = (int)($_POST['id1'] ?? 0);
$id2 = (int)($_POST['id2'] ?? 0);

if ($id1 <= 0 || $id2 <= 0 || $id1 === $id2) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'IDs invalides.']); exit;
}

$pdo  = get_pdo();
$stmt = $pdo->prepare('SELECT id, ordre, jour FROM entrainements WHERE id IN (?, ?)');
$stmt->execute([$id1, $id2]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) !== 2) {
    http_response_code(404); ob_end_clean(); echo json_encode(['error' => 'Créneaux introuvables.']); exit;
}

$map = [];
foreach ($rows as $r) $map[(int)$r['id']] = ['ordre' => (int)$r['ordre'], 'jour' => $r['jour']];

if ($map[$id1]['jour'] !== $map[$id2]['jour']) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Les créneaux doivent être du même jour.']); exit;
}

$pdo->prepare('UPDATE entrainements SET ordre = ? WHERE id = ?')->execute([$map[$id2]['ordre'], $id1]);
$pdo->prepare('UPDATE entrainements SET ordre = ? WHERE id = ?')->execute([$map[$id1]['ordre'], $id2]);

log_activite($pdo, 'modification', 'entrainement', "Réordonnancement créneaux #{$id1} <-> #{$id2}");

ob_end_clean();
echo json_encode(['success' => true]);
