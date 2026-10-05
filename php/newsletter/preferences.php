<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Connexion requise']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']); exit;
}

$abonne = ($_POST['newsletter'] ?? '') === '1' ? 1 : 0;
$id     = (int)current_user()['id'];

get_pdo()->prepare('UPDATE users SET newsletter = ?, newsletter_token = COALESCE(newsletter_token, ?) WHERE id = ?')
    ->execute([$abonne, bin2hex(random_bytes(16)), $id]);

ob_end_clean(); echo json_encode(['success' => true, 'newsletter' => $abonne]);
