<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    http_response_code(403); ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']); exit;
}
check_csrf();

$allowed = ['saison', 'video_url'];
$cle     = trim($_POST['cle']    ?? '');
$valeur  = trim($_POST['valeur'] ?? '');

if (!in_array($cle, $allowed, true)) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Clé non autorisée.']); exit;
}

$pdo = get_pdo();
$pdo->prepare('INSERT INTO licence_config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?')
    ->execute([$cle, $valeur, $valeur]);

log_activite($pdo, 'modification', 'licence_config', "Config « {$cle} » mise à jour");

ob_end_clean(); echo json_encode(['success' => true, 'cle' => $cle, 'valeur' => $valeur]);
