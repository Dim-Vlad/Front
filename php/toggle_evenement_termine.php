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
    $stmt = $pdo->prepare("SELECT titre, termine FROM evenements WHERE id=?");
    $stmt->execute([$id]);
    $ev = $stmt->fetch();
    if (!$ev) { echo json_encode(['success'=>false,'error'=>'Introuvable']); exit; }

    $newVal = $ev['termine'] ? 0 : 1;
    $pdo->prepare("UPDATE evenements SET termine=? WHERE id=?")->execute([$newVal, $id]);
    log_activite($pdo, 'Toggle événement', 'evenements', $ev['titre'] . ' → ' . ($newVal ? 'terminé' : 'à venir'));
    echo json_encode(['success'=>true, 'termine'=>$newVal]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
