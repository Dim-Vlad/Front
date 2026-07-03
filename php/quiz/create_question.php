<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    echo json_encode(['success' => false, 'error' => 'Accès refusé']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$question  = trim($_POST['question'] ?? '');
$choix_a   = trim($_POST['choix_a'] ?? '');
$choix_b   = trim($_POST['choix_b'] ?? '');
$choix_c   = trim($_POST['choix_c'] ?? '') ?: null;
$choix_d   = trim($_POST['choix_d'] ?? '') ?: null;
$reponse   = strtolower(trim($_POST['reponse_correcte'] ?? ''));
$explication = trim($_POST['explication'] ?? '') ?: null;
$points    = max(1, min(5, (int)($_POST['points'] ?? 1)));

if (!$question || !$choix_a || !$choix_b || !in_array($reponse, ['a','b','c','d'], true)) {
    echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']); exit;
}

try {
    $pdo  = get_pdo();
    $user = current_user();
    $stmt = $pdo->prepare(
        'INSERT INTO quiz_questions (question, choix_a, choix_b, choix_c, choix_d, reponse_correcte, explication, points, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([$question, $choix_a, $choix_b, $choix_c, $choix_d, $reponse, $explication, $points, $user['id']]);
    $id = (int)$pdo->lastInsertId();
    log_activite($pdo, 'creation', 'quiz_question', 'Question #' . $id . ' créée');
    echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
