<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$photoId = (int)($_POST['photo_id'] ?? 0);
if ($photoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT p.*, s.label AS saison_label FROM photos_galerie p JOIN saisons_galerie s ON s.id = p.saison_id WHERE p.id = ? LIMIT 1");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();

    if (!$photo) {
        echo json_encode(['success' => false, 'error' => 'Photo introuvable']);
        exit;
    }

    // Only delete files that were uploaded through the new system (in photos/galerie/)
    $fullPath = __DIR__ . '/../../' . $photo['filepath'];
    if (strpos(realpath(dirname($fullPath)), realpath(__DIR__ . '/../../photos/galerie')) === 0) {
        if (file_exists($fullPath)) @unlink($fullPath);
    }

    $pdo->prepare("DELETE FROM photos_galerie WHERE id = ?")->execute([$photoId]);

    log_activite($pdo, 'DELETE', 'photos_galerie', "Photo #{$photoId} supprimée (saison {$photo['saison_label']})");

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
