<?php
require_once __DIR__ . '/../auth.php';

$token = trim($_GET['t'] ?? '');
$ok    = false;

if ($token !== '' && preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
    $stmt = get_pdo()->prepare('UPDATE users SET newsletter = 0 WHERE newsletter_token = ? AND newsletter = 1');
    $stmt->execute([$token]);
    $ok = $stmt->rowCount() > 0;
    if (!$ok) {
        $verif = get_pdo()->prepare('SELECT 1 FROM users WHERE newsletter_token = ? AND newsletter = 0');
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
    <link href="/css/styles.css?v=20261005" rel="stylesheet">
</head>
<body style="background:#f4f8f4;">
    <div style="max-width:520px;margin:60px auto;padding:32px;background:#fff;border-radius:14px;box-shadow:0 2px 14px rgba(0,0,0,.08);text-align:center;font-family:Arial,sans-serif;">
        <?php if ($ok): ?>
        <h1 style="font-size:20px;color:#063E0B;margin:0 0 12px;">Désabonnement confirmé</h1>
        <p style="color:#444;line-height:1.6;margin:0 0 18px;">Vous ne recevrez plus la newsletter du Volley Ball Ollioulais. Vous continuerez à recevoir les informations liées à votre adhésion.</p>
        <p style="margin:0 0 18px;"><a href="/php/newsletter/reabonner.php?t=<?= htmlspecialchars(urlencode($token), ENT_QUOTES) ?>" style="display:inline-block;background:#063E0B;color:#fff;padding:9px 18px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">Me réabonner à la newsletter</a></p>
        <?php else: ?>
        <h1 style="font-size:20px;color:#063E0B;margin:0 0 12px;">Lien invalide</h1>
        <p style="color:#444;line-height:1.6;margin:0 0 18px;">Ce lien de désabonnement n'est plus valide. Si vous ne souhaitez plus recevoir la newsletter, contactez le club.</p>
        <?php endif; ?>
        <a href="/" style="color:#063E0B;font-weight:600;">← Retour à l'accueil</a>
    </div>
</body>
</html>
