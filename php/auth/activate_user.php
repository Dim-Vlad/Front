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
$role   = trim($_POST['role'] ?? '');

if (!$userId || $role === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Données manquantes']); exit;
}

try {
    $pdo = get_pdo();

    $user = $pdo->prepare('SELECT prenom, nom, username FROM users WHERE id = ? AND actif = 0');
    $user->execute([$userId]);
    $user = $user->fetch();
    if (!$user) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Compte introuvable']); exit;
    }

    $roleExists = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
    $roleExists->execute([$role]);
    if (!$roleExists->fetch()) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Rôle invalide']); exit;
    }

    $pdo->prepare('UPDATE users SET actif = 1 WHERE id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = ?')
        ->execute([$userId, $role]);

    log_activite($pdo, 'modification', 'utilisateur', 'Compte activé : ' . $user['prenom'] . ' ' . $user['nom'] . ' → rôle ' . $role);

    ob_end_clean();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
