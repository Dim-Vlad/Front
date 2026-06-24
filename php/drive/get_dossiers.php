<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['bureau', 'admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}

try {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT id, nom, url FROM drive_dossiers ORDER BY ordre ASC, id ASC")->fetchAll();
    ob_end_clean(); echo json_encode(['success' => true, 'dossiers' => $rows]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
