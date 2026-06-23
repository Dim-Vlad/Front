<?php
ob_start();
require_once __DIR__ . '/../auth.php';
ob_end_clean();
header('Content-Type: application/json');

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'helloasso_boutique_url'");
    $stmt->execute();
    $row  = $stmt->fetch();

    echo json_encode([
        'success'       => true,
        'helloasso_url' => $row ? $row['valeur'] : '',
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
