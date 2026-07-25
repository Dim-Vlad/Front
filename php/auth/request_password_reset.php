<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$username = trim($_POST['username'] ?? '');

if ($username === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Identifiant requis.']); exit;
}

// Réponse générique dans tous les cas (ne pas révéler si le compte existe).
$generic = ['success' => true];

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, prenom, actif FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $appareil = parse_user_agent($_SERVER['HTTP_USER_AGENT'] ?? '');
    try {
        $pdo->prepare('INSERT INTO journal_connexions (user_id, username, roles, ip, succes, appareil) VALUES (?, ?, ?, ?, 0, ?)')
            ->execute([$user['id'] ?? 0, $username, 'Demande de réinitialisation mot de passe', $ip, $appareil]);
    } catch (Exception $e) {}

    if ($user && (int)$user['actif'] === 1 && filter_var($user['username'], FILTER_VALIDATE_EMAIL)) {
        // Invalide les demandes précédentes encore actives.
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')
            ->execute([$user['id']]);

        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
            ->execute([$user['id'], $tokenHash, $expiresAt]);

        $link = 'https://volleyballollioulais.fr/pages/auth/reinitialiser-mot-de-passe.php?token=' . $token;
        $subj = '[VBO] Réinitialisation de votre mot de passe';
        $body = "Bonjour " . ($user['prenom'] ?: '') . ",\r\n\r\n"
              . "Vous avez demandé la réinitialisation de votre mot de passe sur le site du Volley Ball Ollioulais.\r\n\r\n"
              . "Cliquez sur le lien ci-dessous pour choisir un nouveau mot de passe (valable 1 heure) :\r\n"
              . $link . "\r\n\r\n"
              . "Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.\r\n\r\n"
              . "-- L'équipe VBO";
        $headers = "From: no-reply@volleyballollioulais.fr\r\n"
                 . "Content-Type: text/plain; charset=utf-8\r\n";
        @mail($user['username'], $subj, $body, $headers);
    }
} catch (Exception $e) {
    // Toujours répondre de façon générique.
}

ob_end_clean();
echo json_encode($generic);
