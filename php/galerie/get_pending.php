<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->query(
        "SELECT p.*, s.label AS saison_label, s.slug AS saison_slug
         FROM photos_galerie p
         JOIN saisons_galerie s ON s.id = p.saison_id
         WHERE p.statut = 'en_attente'
         ORDER BY p.uploaded_at ASC"
    );
    $photos = $stmt->fetchAll();

    ob_end_clean(); echo json_encode(['success' => true, 'photos' => $photos, 'count' => count($photos)]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
