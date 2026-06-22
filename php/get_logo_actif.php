<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'logo_club'");
    $stmt->execute();
    $row  = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'logo'    => $row ? $row['valeur'] : 'images/logo-club/LogoVBO.png',
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'logo'    => 'images/logo-club/LogoVBO.png',
    ]);
}
