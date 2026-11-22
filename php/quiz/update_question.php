<?php
/**
 * Modification d'une question existante, y compris une question active
 * ("en ligne") qui a déjà des réponses. Les réponses déjà données ne sont
 * pas recalculées : seules les prochaines réponses utiliseront les
 * nouvelles valeurs.
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

$id = (int)($_POST['id'] ?? 0);
$question  = trim($_POST['question'] ?? '');
$choix_a   = trim($_POST['choix_a'] ?? '');
$choix_b   = trim($_POST['choix_b'] ?? '');
$choix_c   = trim($_POST['choix_c'] ?? '') ?: null;
$choix_d   = trim($_POST['choix_d'] ?? '') ?: null;
$reponse   = strtolower(trim($_POST['reponse_correcte'] ?? ''));
$explication = trim($_POST['explication'] ?? '') ?: null;
$points    = max(1, min(5, (int)($_POST['points'] ?? 1)));

$choixDisponibles = ['a' => $choix_a, 'b' => $choix_b, 'c' => $choix_c, 'd' => $choix_d];

if (!$id || !$question || !$choix_a || !$choix_b) {
    echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']); exit;
}
if (!in_array($reponse, ['a', 'b', 'c', 'd'], true) || empty($choixDisponibles[$reponse])) {
    echo json_encode(['success' => false, 'error' => 'Bonne réponse invalide']); exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        'UPDATE quiz_questions
         SET question = ?, choix_a = ?, choix_b = ?, choix_c = ?, choix_d = ?,
             reponse_correcte = ?, explication = ?, points = ?
         WHERE id = ?'
    );
    $stmt->execute([$question, $choix_a, $choix_b, $choix_c, $choix_d, $reponse, $explication, $points, $id]);
    log_activite($pdo, 'modification', 'quiz_question', 'Question #' . $id . ' modifiée');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
