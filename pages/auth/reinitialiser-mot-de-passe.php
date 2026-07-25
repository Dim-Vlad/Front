<?php
require_once __DIR__ . '/../../php/auth.php';

if (is_logged_in()) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$token   = trim($_GET['token'] ?? '');
$isValid = false;

if ($token !== '') {
    try {
        $pdo  = get_pdo();
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > NOW()");
        $stmt->execute([$hash]);
        $isValid = (bool)$stmt->fetch();
    } catch (Exception $e) {
        $isValid = false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/connexion.css?v=20260801" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper">
        <div class="simple-auth-card">
            <?php if (!$isValid): ?>
                <h1 class="auth-form-title">Lien invalide</h1>
                <div class="login-error">Ce lien de réinitialisation est invalide ou a expiré.</div>
                <p class="login-subtitle">
                    <a href="/pages/auth/mot-de-passe-oublie.php">Faire une nouvelle demande</a>
                </p>
            <?php else: ?>
                <h1 class="auth-form-title">Nouveau mot de passe</h1>

                <div id="reset-error" class="login-error" style="display:none"></div>
                <div id="reset-success" class="reg-success" style="display:none">
                    <p>✅ Votre mot de passe a été mis à jour.</p>
                    <p>Vous pouvez maintenant vous connecter.</p>
                </div>

                <form id="reset-form" novalidate>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                    <div class="form-group">
                        <label for="reset-password">Nouveau mot de passe <span class="form-hint">(8 caractères minimum)</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="reset-password" name="password" placeholder="8 caractères minimum" autocomplete="new-password" required minlength="8">
                            <button type="button" class="btn-toggle-password" onclick="togglePassword(this)" aria-label="Voir le mot de passe">
                                <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reset-confirm">Confirmer le mot de passe</label>
                        <div class="password-wrapper">
                            <input type="password" id="reset-confirm" name="password_confirm" placeholder="Répétez votre mot de passe" autocomplete="new-password" required minlength="8">
                            <button type="button" class="btn-toggle-password" onclick="togglePassword(this)" aria-label="Voir le mot de passe">
                                <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn-login" id="reset-submit">Réinitialiser</button>
                </form>
            <?php endif; ?>

            <p class="auth-switch-link" style="display:block">
                <a href="/pages/auth/connexion.php">← Retour à la connexion</a>
            </p>
        </div>

        <a href="/" class="back-home">← Retour à l'accueil</a>
    </div>

    <script>
        function togglePassword(btn) {
            const input = btn.closest('.password-wrapper').querySelector('input');
            const eyeOn  = btn.querySelector('.icon-eye');
            const eyeOff = btn.querySelector('.icon-eye-off');
            const show = input.type === 'password';
            input.type           = show ? 'text' : 'password';
            eyeOn.style.display  = show ? 'none' : '';
            eyeOff.style.display = show ? '' : 'none';
        }

        const form = document.getElementById('reset-form');
        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn     = document.getElementById('reset-submit');
                const error   = document.getElementById('reset-error');
                const success = document.getElementById('reset-success');
                const pwd     = document.getElementById('reset-password').value;
                const confirm = document.getElementById('reset-confirm').value;

                error.style.display = 'none';
                if (pwd !== confirm) {
                    error.textContent   = 'Les mots de passe ne correspondent pas.';
                    error.style.display = 'block';
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Enregistrement…';

                const fd = new FormData(this);
                try {
                    const res  = await fetch('/php/auth/reset_password.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        this.style.display = 'none';
                        success.style.display = 'block';
                        setTimeout(() => { window.location.href = '/pages/auth/connexion.php'; }, 1800);
                    } else {
                        error.textContent   = data.error || 'Une erreur est survenue.';
                        error.style.display = 'block';
                        btn.disabled    = false;
                        btn.textContent = 'Réinitialiser';
                    }
                } catch {
                    error.textContent   = 'Erreur réseau. Veuillez réessayer.';
                    error.style.display = 'block';
                    btn.disabled    = false;
                    btn.textContent = 'Réinitialiser';
                }
            });
        }
    </script>
</body>
</html>
