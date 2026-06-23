<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur','admin'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'error'=>'ID manquant']); exit; }

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT nom, sous_titre FROM staff_membres WHERE id=?");
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    $pdo->prepare("DELETE FROM staff_membres WHERE id=?")->execute([$id]);
    if ($m) log_activite($pdo, 'Suppression membre staff', 'staff', $m['nom'] . ' (' . $m['sous_titre'] . ')');
    ob_end_clean(); echo json_encode(['success'=>true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
