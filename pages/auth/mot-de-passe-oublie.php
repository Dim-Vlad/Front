<?php
require_once __DIR__ . '/../../php/auth.php';

if (is_logged_in()) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - VBO</title>
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
            <h1 class="auth-form-title">Mot de passe oublié</h1>
            <p class="login-subtitle">Indiquez votre identifiant<?= LOCAL_DEV ? '' : ' (adresse email)' ?> : si un compte existe, un lien de réinitialisation vous sera envoyé par email.</p>

            <div id="req-error" class="login-error" style="display:none"></div>
            <div id="req-success" class="reg-success" style="display:none">
                <p>✅ Si un compte existe avec cet identifiant, un email vient de vous être envoyé.</p>
                <p>Pensez à vérifier vos spams.</p>
            </div>

            <form id="req-form" novalidate>
                <div class="form-group">
                    <label for="req-username">Identifiant <?= LOCAL_DEV ? '' : '<span class="form-hint">adresse email</span>' ?></label>
                    <input type="<?= LOCAL_DEV ? 'text' : 'email' ?>" id="req-username" name="username"
                           placeholder="<?= LOCAL_DEV ? 'ex: admin' : 'exemple@email.com' ?>"
                           autocomplete="username" required>
                </div>
                <button type="submit" class="btn-login" id="req-submit">Envoyer le lien</button>
            </form>

            <p class="auth-switch-link" style="display:block">
                <a href="/pages/auth/connexion.php">← Retour à la connexion</a>
            </p>
        </div>

        <a href="/" class="back-home">← Retour à l'accueil</a>
    </div>

    <script>
        document.getElementById('req-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn     = document.getElementById('req-submit');
            const error   = document.getElementById('req-error');
            const success = document.getElementById('req-success');
            error.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Envoi…';

            const fd = new FormData(this);
            try {
                const res  = await fetch('/php/auth/request_password_reset.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    this.style.display = 'none';
                    success.style.display = 'block';
                } else {
                    error.textContent   = data.error || 'Une erreur est survenue.';
                    error.style.display = 'block';
                    btn.disabled    = false;
                    btn.textContent = 'Envoyer le lien';
                }
            } catch {
                error.textContent   = 'Erreur réseau. Veuillez réessayer.';
                error.style.display = 'block';
                btn.disabled    = false;
                btn.textContent = 'Envoyer le lien';
            }
        });
    </script>
</body>
</html>
