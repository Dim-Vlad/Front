<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$id     = (int)($_POST['id']     ?? 0);
$valeur = (($_POST['valeur'] ?? '') === '1') ? 1 : 0;

if (!$id) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'ID manquant']); exit;
}

try {
    $pdo = get_pdo();
    $pdo->prepare("UPDATE evenements SET home_visible = ? WHERE id = ?")
        ->execute([$valeur, $id]);
    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
