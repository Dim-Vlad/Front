<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}

$id        = (int)($_POST['id']        ?? 0);
$direction = trim($_POST['direction']  ?? '');

if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Paramètres invalides']); exit;
}

try {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT id, ordre FROM drive_dossiers ORDER BY ordre ASC, id ASC")->fetchAll();
    $idx  = array_search($id, array_column($rows, 'id'));

    if ($idx === false) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Dossier introuvable']); exit;
    }

    $swapIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
    if ($swapIdx < 0 || $swapIdx >= count($rows)) {
        ob_end_clean(); echo json_encode(['success' => true]); exit;
    }

    $stmt = $pdo->prepare("UPDATE drive_dossiers SET ordre = ? WHERE id = ?");
    $stmt->execute([$rows[$swapIdx]['ordre'], $rows[$idx]['id']]);
    $stmt->execute([$rows[$idx]['ordre'],     $rows[$swapIdx]['id']]);

    ob_end_clean(); echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
