<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée.']);
    exit;
}

$cle    = trim($_POST['cle']    ?? '');
$valeur = trim($_POST['valeur'] ?? '');

if (!in_array($cle, ['saison', 'visible'], true)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Clé non autorisée.']);
    exit;
}

$pdo = get_pdo();
$pdo->prepare('INSERT INTO entrainements_config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?')
    ->execute([$cle, $valeur, $valeur]);

$msg = $cle === 'visible'
    ? ('Tableau ' . ($valeur === '1' ? 'rendu visible' : 'masqué') . ' aux visiteurs')
    : "Saison mise à jour : {$valeur}";
log_activite($pdo, 'modification', 'entrainements_config', $msg);

ob_end_clean();
echo json_encode(['success' => true, 'cle' => $cle, 'valeur' => $valeur]);
