<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$matchId  = (int)($_POST['match_id'] ?? 0);
$victoire = $_POST['resultat_victoire'] ?? null;
$sets     = trim($_POST['resultat_sets'] ?? '');

$setsVictoire = ['3-0', '3-1', '3-2'];
$setsDefaite  = ['0-3', '1-3', '2-3'];

if (!$matchId || !in_array($victoire, ['0', '1'], true) || $sets === '') {
    echo json_encode(['success' => false, 'error' => 'Données invalides']); exit;
}

$victoire = (int)$victoire;

if ($victoire === 1 && !in_array($sets, $setsVictoire, true)) {
    echo json_encode(['success' => false, 'error' => 'Score incohérent avec le résultat']); exit;
}
if ($victoire === 0 && !in_array($sets, $setsDefaite, true)) {
    echo json_encode(['success' => false, 'error' => 'Score incohérent avec le résultat']); exit;
}

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT adversaire FROM pronostics_matchs WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        echo json_encode(['success' => false, 'error' => 'Match introuvable']); exit;
    }

    $pdo->prepare('UPDATE pronostics_matchs SET resultat_victoire = ?, resultat_sets = ? WHERE id = ?')
        ->execute([$victoire, $sets, $matchId]);

    log_activite($pdo, 'modification', 'pronostics',
        'Résultat saisi : VBO vs ' . $match['adversaire'] . ' → ' . ($victoire ? 'Victoire' : 'Défaite') . ' ' . $sets);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
