<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$matchId     = (int)($_POST['match_id'] ?? 0);
$adversaire  = trim($_POST['adversaire'] ?? '');
$date_match  = trim($_POST['date_match'] ?? '');
$competition = trim($_POST['competition'] ?? '') ?: null;
$domicile    = isset($_POST['domicile']) ? 1 : 0;

if (!$matchId || $adversaire === '' || $date_match === '') {
    echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']); exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $date_match)) {
    echo json_encode(['success' => false, 'error' => 'Date invalide']); exit;
}

$date_mysql = str_replace('T', ' ', $date_match) . ':00';

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT adversaire FROM pronostics_matchs WHERE id = ? AND resultat_victoire IS NULL');
    $stmt->execute([$matchId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Match introuvable ou déjà terminé']); exit;
    }

    $pdo->prepare('UPDATE pronostics_matchs SET adversaire = ?, date_match = ?, competition = ?, domicile = ? WHERE id = ?')
        ->execute([$adversaire, $date_mysql, $competition, $domicile, $matchId]);

    log_activite($pdo, 'modification', 'pronostics', 'Match modifié : VBO vs ' . $adversaire . ' (' . $date_mysql . ')');
    echo json_encode(['success' => true, 'adversaire' => $adversaire, 'date_mysql' => $date_mysql, 'competition' => $competition, 'domicile' => $domicile]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
