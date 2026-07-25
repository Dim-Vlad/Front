<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$token    = trim($_POST['token'] ?? '');
$password = $_POST['password']         ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

if ($token === '' || $password === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Données manquantes.']); exit;
}
if (strlen($password) < 8) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 8 caractères.']); exit;
}
if ($password !== $confirm) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Les mots de passe ne correspondent pas.']); exit;
}

try {
    $pdo  = get_pdo();
    $hash = hash('sha256', $token);

    $stmt = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > NOW()');
    $stmt->execute([$hash]);
    $reset = $stmt->fetch();

    if (!$reset) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Ce lien est invalide ou a expiré.']); exit;
    }

    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);

    // Invalide ce token et tout autre token en attente pour cet utilisateur.
    $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')
        ->execute([$reset['user_id']]);

    try {
        $u        = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $u->execute([$reset['user_id']]);
        $username = $u->fetchColumn() ?: '';
        $ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $appareil = parse_user_agent($_SERVER['HTTP_USER_AGENT'] ?? '');
        $pdo->prepare('INSERT INTO journal_connexions (user_id, username, roles, ip, succes, appareil) VALUES (?, ?, ?, ?, 1, ?)')
            ->execute([$reset['user_id'], $username, 'Mot de passe réinitialisé', $ip, $appareil]);
    } catch (Exception $e) {}

    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Erreur serveur.']);
}
