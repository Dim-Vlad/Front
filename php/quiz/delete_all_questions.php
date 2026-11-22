<?php
/**
 * Suppression de TOUTES les questions du quiz (remise à zéro, ex. nouvelle
 * saison). Cascade sur quiz_reponses (cf. schéma) : les points des
 * utilisateurs liés au quiz repartent donc aussi à zéro.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    echo json_encode(['success' => false, 'error' => 'Accès refusé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

try {
    $pdo = get_pdo();
    $nb  = (int)$pdo->query('SELECT COUNT(*) FROM quiz_questions')->fetchColumn();
    $pdo->exec('DELETE FROM quiz_questions');
    log_activite($pdo, 'suppression', 'quiz_question', 'Toutes les questions supprimées (' . $nb . ')');
    echo json_encode(['success' => true, 'supprimees' => $nb]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
