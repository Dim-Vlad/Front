<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

if (!has_any_role(['admin'])) {
    http_response_code(403);
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}

check_csrf();

try {
    $pdo = get_pdo();
    $pdo->exec('DELETE FROM journal_connexions');
    $pdo->exec('DELETE FROM journal_activites');

    $now = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO parametres (cle, valeur) VALUES ('dernier_vidage_logs', ?) ON DUPLICATE KEY UPDATE valeur = ?")
        ->execute([$now, $now]);

    ob_end_clean(); echo json_encode([
        'success'              => true,
        'connexions_restantes' => 0,
        'activites_restantes'  => 0,
        'date_vidage'          => $now,
    ]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
