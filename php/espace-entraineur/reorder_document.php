<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
check_csrf();

$id        = (int)($_POST['id'] ?? 0);
$direction = $_POST['direction'] ?? '';
if (!$id || !in_array($direction, ['up', 'down'], true)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Paramètres invalides']); exit;
}

try {
    $pdo = get_pdo();

    $stmt = $pdo->prepare('SELECT section_id FROM entraineur_documents WHERE id = ?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) { ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Introuvable']); exit; }

    $stmtAll = $pdo->prepare('SELECT id, ordre FROM entraineur_documents WHERE section_id = ? ORDER BY ordre ASC, id ASC');
    $stmtAll->execute([$doc['section_id']]);
    $all = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_column($all, 'id');
    $pos = array_search($id, $ids);

    if ($pos === false) { ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Introuvable']); exit; }

    $targetPos = $direction === 'up' ? $pos - 1 : $pos + 1;
    if ($targetPos < 0 || $targetPos >= count($all)) {
        ob_end_clean(); echo json_encode(['success' => true]); exit;
    }

    $current   = $all[$pos];
    $neighbour = $all[$targetPos];

    $pdo->prepare('UPDATE entraineur_documents SET ordre = ? WHERE id = ?')->execute([$neighbour['ordre'], $current['id']]);
    $pdo->prepare('UPDATE entraineur_documents SET ordre = ? WHERE id = ?')->execute([$current['ordre'], $neighbour['id']]);

    ob_end_clean(); echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
