<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur','admin'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}
check_csrf();

$id        = (int)($_POST['id']        ?? 0);
$direction = $_POST['direction'] ?? '';
if (!$id || !in_array($direction, ['up','down'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Paramètres invalides']); exit;
}

try {
    $pdo = get_pdo();

    $all = $pdo->query("SELECT id, ordre FROM evenements WHERE termine=0 ORDER BY ordre ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_column($all, 'id');
    $pos = array_search($id, $ids);

    if ($pos === false) { ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Introuvable']); exit; }

    $targetPos = $direction === 'up' ? $pos - 1 : $pos + 1;
    if ($targetPos < 0 || $targetPos >= count($all)) {
        ob_end_clean(); echo json_encode(['success'=>true]); exit;
    }

    $current   = $all[$pos];
    $neighbour = $all[$targetPos];

    $pdo->prepare("UPDATE evenements SET ordre=? WHERE id=?")->execute([$neighbour['ordre'], $current['id']]);
    $pdo->prepare("UPDATE evenements SET ordre=? WHERE id=?")->execute([$current['ordre'],   $neighbour['id']]);

    ob_end_clean(); echo json_encode(['success'=>true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
