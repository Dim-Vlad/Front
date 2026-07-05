<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Connexion requise']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$qid   = (int)($_POST['question_id'] ?? 0);
$choix = strtolower(trim($_POST['choix'] ?? ''));
$user  = current_user();

if (!$qid || !in_array($choix, ['a','b','c','d'], true)) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']); exit;
}

try {
    $pdo = get_pdo();

    $stmt = $pdo->prepare('SELECT reponse_correcte, points, explication, actif FROM quiz_questions WHERE id = ?');
    $stmt->execute([$qid]);
    $question = $stmt->fetch();

    if (!$question || !$question['actif']) {
        echo json_encode(['success' => false, 'error' => 'Question introuvable']); exit;
    }

    $check = $pdo->prepare('SELECT id FROM quiz_reponses WHERE question_id = ? AND user_id = ?');
    $check->execute([$qid, $user['id']]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Déjà répondu']); exit;
    }

    $correct = ($choix === $question['reponse_correcte']) ? 1 : 0;
    $pts     = $correct ? (int)$question['points'] : 0;

    $pdo->prepare('INSERT INTO quiz_reponses (question_id, user_id, choix, correct, points_obtenus) VALUES (?,?,?,?,?)')
        ->execute([$qid, $user['id'], $choix, $correct, $pts]);

    echo json_encode([
        'success'     => true,
        'correct'     => (bool)$correct,
        'bonne_reponse' => $question['reponse_correcte'],
        'points'      => $pts,
        'explication' => $question['explication'],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
