<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

try {
    $pdo  = get_pdo();
    $saison = $pdo->query("SELECT * FROM saisons_galerie WHERE est_active = 1 LIMIT 1")->fetch();

    if (!$saison) {
        ob_end_clean(); echo json_encode(['success' => true, 'saison' => null, 'photos' => []]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM photos_galerie WHERE saison_id = ? AND statut = 'publiee' ORDER BY ordre ASC, id ASC"
    );
    $stmt->execute([$saison['id']]);
    $photos = $stmt->fetchAll();

    ob_end_clean(); echo json_encode(['success' => true, 'saison' => $saison, 'photos' => $photos]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
