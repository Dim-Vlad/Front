<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}
check_csrf();

try {
    $rows = get_pdo()
        ->query("SELECT id, titre, saison FROM tournois ORDER BY created_at DESC")
        ->fetchAll();
    ob_end_clean(); echo json_encode(['success'=>true, 'tournois'=>$rows]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
