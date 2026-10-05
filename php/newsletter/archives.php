<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Connexion requise']); exit;
}

try {
    $rows = get_pdo()->query(
        'SELECT id, titre, created_at FROM newsletters ORDER BY created_at DESC, id DESC LIMIT 100'
    )->fetchAll(PDO::FETCH_ASSOC);
    ob_end_clean(); echo json_encode(['success' => true, 'editions' => $rows]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
