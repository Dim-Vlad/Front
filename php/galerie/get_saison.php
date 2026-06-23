<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

$slug = $_GET['slug'] ?? '';
if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Slug invalide']);
    exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT * FROM saisons_galerie WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $saison = $stmt->fetch();

    if (!$saison) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Saison introuvable']);
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
