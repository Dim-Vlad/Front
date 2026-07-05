<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$valeur = (($_POST['valeur'] ?? '') === '1') ? '1' : '0';

try {
    $pdo = get_pdo();
    $pdo->prepare("INSERT INTO parametres (cle, valeur) VALUES ('home_show_events', ?) ON DUPLICATE KEY UPDATE valeur = ?")
        ->execute([$valeur, $valeur]);
    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
