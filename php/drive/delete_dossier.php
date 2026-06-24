<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'ID invalide']); exit;
}

try {
    $pdo  = get_pdo();
    $row  = $pdo->prepare("SELECT nom FROM drive_dossiers WHERE id = ?");
    $row->execute([$id]);
    $dossier = $row->fetch();
    if (!$dossier) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Dossier introuvable']); exit;
    }
    $pdo->prepare("DELETE FROM drive_dossiers WHERE id = ?")->execute([$id]);
    log_activite($pdo, 'suppression', 'drive_dossier', $dossier['nom']);
    ob_end_clean(); echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
