<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur','admin'])) {
    echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'error'=>'ID manquant']); exit; }

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT nom, visible FROM staff_membres WHERE id=?");
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if (!$m) { echo json_encode(['success'=>false,'error'=>'Introuvable']); exit; }

    $newVisible = $m['visible'] ? 0 : 1;
    $pdo->prepare("UPDATE staff_membres SET visible=? WHERE id=?")->execute([$newVisible,$id]);
    log_activite($pdo, $newVisible ? 'Entraîneur rendu visible' : 'Entraîneur masqué', 'staff', $m['nom']);
    echo json_encode(['success'=>true,'visible'=>$newVisible]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
