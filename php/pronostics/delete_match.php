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

$matchId = (int)($_POST['match_id'] ?? 0);
if (!$matchId) {
    echo json_encode(['success' => false, 'error' => 'ID manquant']); exit;
}

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT adversaire FROM pronostics_matchs WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        echo json_encode(['success' => false, 'error' => 'Match introuvable']); exit;
    }

    $pdo->prepare('DELETE FROM pronostics_matchs WHERE id = ?')->execute([$matchId]);
    log_activite($pdo, 'suppression', 'pronostics', 'Match supprimé : VBO vs ' . $match['adversaire']);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
