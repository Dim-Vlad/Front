<?php
ob_start();
require_once __DIR__ . '/../auth.php';
ob_end_clean();
header('Content-Type: application/json');

try {
    $pdo       = get_pdo();
    $adminView = is_logged_in() && has_any_role(['admin', 'moderateur']);

    $sql = "SELECT id, nom, description, image_path, section, actif, ordre
            FROM boutique_articles";
    if (!$adminView) $sql .= " WHERE actif = 1";
    $sql .= " ORDER BY section, ordre, id";

    $stmt = $pdo->query($sql);
    echo json_encode(['success' => true, 'articles' => $stmt->fetchAll()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
