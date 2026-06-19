<?php
require_once __DIR__ . '/../php/auth.php';

if (is_logged_in()) {
    header('Location: /pages/tableau-de-bord.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $pdo  = get_pdo();
            $stmt = $pdo->prepare('SELECT id, username, password, role, prenom, nom FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['prenom']   = $user['prenom'];
                $_SESSION['nom']      = $user['nom'];
                header('Location: /pages/tableau-de-bord.php');
                exit;
            } else {
                $error = 'Identifiant ou mot de passe incorrect.';
            }
        } catch (PDOException $e) {
            $error = 'Erreur de connexion à la base de données.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - VBO</title>
    <link href="/css/styles.css" rel="stylesheet">
    <link href="/css/connexion.css" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <img src="/images/logo-club/LogoVBO.png" alt="Logo VBO" class="login-logo">
            <h1>Connexion</h1>
            <p class="login-subtitle">Espace réservé aux membres du club</p>

            <?php if ($error): ?>
                <div class="login-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/pages/connexion.php">
                <div class="form-group">
                    <label for="username">Identifiant</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="btn-toggle-password" onclick="togglePassword(this)" aria-label="Voir le mot de passe">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login">Se connecter</button>
            </form>

            <a href="/" class="back-home">← Retour à l'accueil</a>
        </div>
    </div>
    <script>
        function togglePassword(btn) {
            const input = btn.closest('.password-wrapper').querySelector('input');
            const eyeOn  = btn.querySelector('.icon-eye');
            const eyeOff = btn.querySelector('.icon-eye-off');
            const show = input.type === 'password';
            input.type        = show ? 'text' : 'password';
            eyeOn.style.display  = show ? 'none' : '';
            eyeOff.style.display = show ? '' : 'none';
        }
    </script>
</body>
</html>
