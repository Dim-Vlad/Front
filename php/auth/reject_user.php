<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
if (!$userId) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Données manquantes']); exit;
}

try {
    $pdo = get_pdo();

    $user = $pdo->prepare('SELECT prenom, nom FROM users WHERE id = ? AND actif = 0');
    $user->execute([$userId]);
    $user = $user->fetch();
    if (!$user) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Compte introuvable']); exit;
    }

    $pdo->prepare('DELETE FROM users WHERE id = ? AND actif = 0')->execute([$userId]);

    log_activite($pdo, 'suppression', 'utilisateur', 'Compte refusé : ' . $user['prenom'] . ' ' . $user['nom']);

    ob_end_clean();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
