<?php
ob_start();
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

$defaults = [
    'logo_club'    => 'images/logo-club/LogoVBO.png',
    'logo_menu'    => 'images/logo-club/Logo-VBO-blanc.png',
    'logo_favicon' => 'images/favicon-36x36.png',
];

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT cle, valeur FROM parametres WHERE cle IN ('logo_club', 'logo_menu', 'logo_favicon')");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    ob_end_clean();
    echo json_encode([
        'success'      => true,
        'logo'         => $rows['logo_club']    ?? $defaults['logo_club'],
        'logo_menu'    => $rows['logo_menu']    ?? $defaults['logo_menu'],
        'logo_favicon' => $rows['logo_favicon'] ?? $defaults['logo_favicon'],
    ]);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success'      => false,
        'logo'         => $defaults['logo_club'],
        'logo_menu'    => $defaults['logo_menu'],
        'logo_favicon' => $defaults['logo_favicon'],
    ]);
}
