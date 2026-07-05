<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    echo json_encode(['success' => false, 'error' => 'Accès refusé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'error' => 'ID manquant']); exit; }

try {
    $pdo = get_pdo();
    $pdo->prepare('UPDATE quiz_questions SET actif = 1 - actif WHERE id = ?')->execute([$id]);
    $actif = (int)$pdo->query('SELECT actif FROM quiz_questions WHERE id = ' . $id)->fetchColumn();
    echo json_encode(['success' => true, 'actif' => $actif]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
