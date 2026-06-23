<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

try {
    $pdo    = get_pdo();
    $stmt   = $pdo->query(
        "SELECT id, label, slug, flickr_url FROM saisons_galerie WHERE est_active = 0 ORDER BY ordre ASC"
    );
    $saisons = $stmt->fetchAll();

    ob_end_clean(); echo json_encode(['success' => true, 'saisons' => $saisons]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
