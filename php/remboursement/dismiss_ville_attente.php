<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_role('admin')) {
    http_response_code(403);
    ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']);
    exit;
}
check_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']);
    exit;
}

$nom = trim($_POST['nom'] ?? '');
if ($nom === '') {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'Nom de ville requis.']);
    exit;
}

$pdo = get_pdo();
$pdo->prepare('DELETE FROM remboursement_villes_attente WHERE nom = ?')->execute([$nom]);
log_activite($pdo, 'suppression', 'remboursement_ville_attente', "Alerte ville « {$nom} » ignorée");

ob_end_clean();
echo json_encode(['success' => true]);
