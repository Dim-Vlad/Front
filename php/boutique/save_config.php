<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
ob_end_clean();
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

$url = trim($_POST['helloasso_url'] ?? '');
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'error' => 'URL invalide']);
    exit;
}

try {
    $pdo = get_pdo();
    $pdo->prepare(
        "INSERT INTO parametres (cle, valeur) VALUES ('helloasso_boutique_url', ?)
         ON DUPLICATE KEY UPDATE valeur = ?"
    )->execute([$url, $url]);

    log_activite($pdo, 'modification', 'parametres', 'URL HelloAsso boutique mise à jour');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
