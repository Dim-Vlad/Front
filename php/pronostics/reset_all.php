<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    echo json_encode(['success' => false, 'error' => 'Réservé aux administrateurs']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

try {
    $pdo = get_pdo();
    $nbMatchs = (int)$pdo->query('SELECT COUNT(*) FROM pronostics_matchs')->fetchColumn();
    $nbVotes  = (int)$pdo->query('SELECT COUNT(*) FROM pronostics_votes')->fetchColumn();

    $pdo->exec('DELETE FROM pronostics_votes');
    $pdo->exec('DELETE FROM pronostics_matchs');

    log_activite($pdo, 'suppression', 'pronostics',
        'Réinitialisation complète : ' . $nbMatchs . ' match(s) et ' . $nbVotes . ' vote(s) supprimés');

    echo json_encode(['success' => true, 'nb_matchs' => $nbMatchs, 'nb_votes' => $nbVotes]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
