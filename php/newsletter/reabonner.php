<?php
require_once __DIR__ . '/../auth.php';

$token = trim($_GET['t'] ?? '');
$ok    = false;

if (preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
    $stmt = get_pdo()->prepare('UPDATE users SET newsletter = 1 WHERE newsletter_token = ? AND newsletter = 0');
    $stmt->execute([$token]);
    $ok = $stmt->rowCount() > 0;
    if (!$ok) {
        $verif = get_pdo()->prepare('SELECT 1 FROM users WHERE newsletter_token = ? AND newsletter = 1');
        $verif->execute([$token]);
        $ok = (bool)$verif->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
</head>
<body style="background:#f4f8f4;">
    <div style="max-width:520px;margin:60px auto;padding:32px;background:#fff;border-radius:14px;box-shadow:0 2px 14px rgba(0,0,0,.08);text-align:center;font-family:Arial,sans-serif;">
        <?php if ($ok): ?>
        <h1 style="font-size:20px;color:#063E0B;margin:0 0 12px;">Vous êtes réabonné</h1>
        <p style="color:#444;line-height:1.6;margin:0 0 18px;">Vous recevrez à nouveau la newsletter du Volley Ball Ollioulais.</p>
        <?php else: ?>
        <h1 style="font-size:20px;color:#063E0B;margin:0 0 12px;">Lien invalide</h1>
        <p style="color:#444;line-height:1.6;margin:0 0 18px;">Ce lien de réabonnement n'est plus valide. Contactez le club si besoin.</p>
        <?php endif; ?>
        <a href="/" style="color:#063E0B;font-weight:600;">← Retour à l'accueil</a>
    </div>
</body>
</html>
