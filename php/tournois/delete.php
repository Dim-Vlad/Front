<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}
check_csrf();

$id = (int)($_POST['id'] ?? 0);
if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'error'=>'ID manquant']); exit; }

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT titre FROM tournois WHERE id=?");
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    if (!$row) { ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Introuvable']); exit; }

    $pdo->prepare("DELETE FROM tournois WHERE id=?")->execute([$id]);
    log_activite($pdo, 'Suppression page tournoi', 'tournois', $row['titre']);
    ob_end_clean(); echo json_encode(['success'=>true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
