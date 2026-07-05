<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$matchId  = (int)($_POST['match_id'] ?? 0);
$victoire = $_POST['choix_victoire'] ?? null;
$sets     = trim($_POST['choix_sets'] ?? '');

$validSets = ['3-0', '3-1', '3-2', '0-3', '1-3', '2-3'];

if (!$matchId || !in_array($victoire, ['0', '1'], true)) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']); exit;
}

$victoire = (int)$victoire;
$sets = ($sets !== '' && in_array($sets, $validSets, true)) ? $sets : null;

if ($sets !== null) {
    $isWinSets = in_array($sets, ['3-0', '3-1', '3-2'], true);
    if ($victoire === 1 && !$isWinSets) {
        echo json_encode(['success' => false, 'error' => 'Score incohérent']); exit;
    }
    if ($victoire === 0 && $isWinSets) {
        echo json_encode(['success' => false, 'error' => 'Score incohérent']); exit;
    }
}

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT date_match, resultat_victoire FROM pronostics_matchs WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();

    if (!$match) {
        echo json_encode(['success' => false, 'error' => 'Match introuvable']); exit;
    }
    if ($match['resultat_victoire'] !== null) {
        echo json_encode(['success' => false, 'error' => 'Le résultat est déjà connu']); exit;
    }
    if (strtotime($match['date_match']) <= time()) {
        echo json_encode(['success' => false, 'error' => 'Les votes sont fermés pour ce match']); exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $pdo->prepare(
        'INSERT INTO pronostics_votes (match_id, user_id, choix_victoire, choix_sets) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE choix_victoire = VALUES(choix_victoire), choix_sets = VALUES(choix_sets), updated_at = NOW()'
    )->execute([$matchId, $userId, $victoire, $sets]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
