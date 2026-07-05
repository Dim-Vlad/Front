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

$num   = (int)($_POST['num']   ?? 0);
$titre = trim($_POST['titre']  ?? '');
$url   = trim($_POST['url']    ?? '');

if ($num < 1 || $num > 6 || $titre === '') {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Données invalides.']); exit;
}

$pdo = get_pdo();
$pdo->prepare('INSERT INTO licence_config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?')
    ->execute(["tuto_{$num}_titre", $titre, $titre]);
$pdo->prepare('INSERT INTO licence_config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?')
    ->execute(["tuto_{$num}_url", $url, $url]);

log_activite($pdo, 'modification', 'licence_config', "Tutoriel #{$num} mis à jour");

ob_end_clean();
echo json_encode(['success' => true, 'num' => $num, 'titre' => $titre, 'url' => $url]);
