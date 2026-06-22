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
    $stmt = $pdo->prepare("SELECT label FROM staff_documents WHERE id=?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) { echo json_encode(['success'=>false,'error'=>'Document introuvable']); exit; }

    $pdo->prepare("DELETE FROM staff_documents WHERE id=?")->execute([$id]);
    log_activite($pdo, 'Suppression document staff', 'documents', $doc['label']);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
