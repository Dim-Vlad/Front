<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']); exit;
}

try {
    $rows = get_pdo()->query(
        "SELECT id, filepath, alt_text FROM photos_galerie
         WHERE statut = 'publiee' ORDER BY uploaded_at DESC, id DESC LIMIT 300"
    )->fetchAll(PDO::FETCH_ASSOC);
    $photos = array_map(fn($r) => [
        'url'   => '/' . ltrim($r['filepath'], '/'),
        'alt'   => $r['alt_text'] ?? '',
    ], $rows);
    ob_end_clean(); echo json_encode(['success' => true, 'photos' => $photos]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
