<?php
require_once __DIR__ . '/../../php/auth.php';

if (is_logged_in()) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

function parseUserAgent(string $ua): string {
    if ($ua === '') return 'Inconnu';
    if (str_contains($ua, 'Edg/'))                                          $browser = 'Edge';
    elseif (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera/'))      $browser = 'Opera';
    elseif (str_contains($ua, 'YaBrowser'))                                 $browser = 'Yandex';
    elseif (str_contains($ua, 'Chrome/') && !str_contains($ua, 'Chromium'))$browser = 'Chrome';
    elseif (str_contains($ua, 'Chromium/'))                                 $browser = 'Chromium';
    elseif (str_contains($ua, 'Firefox/'))                                  $browser = 'Firefox';
    elseif (str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome/')) $browser = 'Safari';
    else                                                                     $browser = 'Navigateur';

    if (str_contains($ua, 'Android'))                                       $os = 'Android';
    elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad'))      $os = 'iOS';
    elseif (str_contains($ua, 'Windows'))                                   $os = 'Windows';
    elseif (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')) $os = 'macOS';
    elseif (str_contains($ua, 'Linux'))                                     $os = 'Linux';
    else                                                                     $os = '';

    return $os ? "$browser · $os" : $browser;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $appareil = parseUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '');
        try {
            $pdo  = get_pdo();
            $stmt = $pdo->prepare('SELECT id, username, password, prenom, nom, actif FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password']) && ($user['actif'] ?? 1) == 0) {
                $error = 'Votre compte est en attente de validation par un administrateur.';
                $nomComplet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?: $user['username'];
                try {
                    $pdo->prepare('INSERT INTO journal_connexions (user_id, username, roles, ip, succes, appareil) VALUES (?, ?, ?, ?, 0, ?)')
                        ->execute([$user['id'], $nomComplet, 'Compte en attente', $ip, $appareil]);
                } catch (Exception $e) {}

            } elseif ($user && password_verify($password, $user['password'])) {
                $rolesStmt = $pdo->prepare(
                    'SELECT r.name FROM roles r
                     JOIN user_roles ur ON ur.role_id = r.id
                     WHERE ur.user_id = ?'
                );
                $rolesStmt->execute([$user['id']]);
                $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['roles']    = $roles;
                $_SESSION['prenom']   = $user['prenom'];
                $_SESSION['nom']      = $user['nom'];

                try {
                    $nomComplet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?: ($user['username'] ?? 'Inconnu');
                    $rolesStr   = implode(', ', $roles);
                    $pdo->prepare('INSERT INTO journal_connexions (user_id, username, roles, ip, succes, appareil) VALUES (?, ?, ?, ?, 1, ?)')
                        ->execute([$user['id'], $nomComplet, $rolesStr, $ip, $appareil]);
                } catch (Exception $e) {}

                header('Location: /pages/auth/tableau-de-bord.php');
                exit;
            } else {
                $error = 'Identifiant ou mot de passe incorrect.';
                try {
                    $pdo->prepare('INSERT INTO journal_connexions (user_id, username, roles, ip, succes, appareil) VALUES (0, ?, ?, ?, 0, ?)')
                        ->execute([$username, '', $ip, $appareil]);
                } catch (Exception $e) {}
            }
        } catch (PDOException $e) {
            $error = 'Erreur de connexion à la base de données.';
        }
    }
}

// Onglet actif : 'login' par défaut, 'inscription' si demandé ou si erreur login absente
$defaultTab = isset($_GET['tab']) && $_GET['tab'] === 'inscription' ? 'inscription' : 'login';
if ($error !== '') $defaultTab = 'login';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace membres - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/connexion.css?v=20260626" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card <?= $defaultTab === 'inscription' ? 'login-card--wide' : '' ?>">
            <img src="/images/logo-club/LogoVBO.png" alt="Logo VBO" class="login-logo">

            <!-- Onglets -->
            <div class="auth-tabs">
                <button class="auth-tab <?= $defaultTab === 'login' ? 'auth-tab--active' : '' ?>" id="tab-login" onclick="switchTab('login')">Se connecter</button>
                <button class="auth-tab <?= $defaultTab === 'inscription' ? 'auth-tab--active' : '' ?>" id="tab-inscription" onclick="switchTab('inscription')">Créer un compte</button>
            </div>

            <!-- ── Panneau Connexion ── -->
            <div id="panel-login" <?= $defaultTab === 'inscription' ? 'style="display:none"' : '' ?>>
                <?php if ($error): ?>
                    <div class="login-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/pages/auth/connexion.php">
                    <input type="hidden" name="_action" value="login">
                    <div class="form-group">
                        <label for="username">Identifiant <?= LOCAL_DEV ? '' : '<span class="form-hint">adresse email</span>' ?></label>
                        <input type="<?= LOCAL_DEV ? 'text' : 'email' ?>" id="username" name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="<?= LOCAL_DEV ? 'ex: admin' : 'exemple@email.com' ?>"
                               autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password"
                                   autocomplete="current-password" required>
                            <button type="button" class="btn-toggle-password" onclick="togglePassword(this)" aria-label="Voir le mot de passe">
                                <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn-login">Se connecter</button>
                </form>
            </div>

            <!-- ── Panneau Inscription ── -->
            <div id="panel-inscription" <?= $defaultTab === 'login' ? 'style="display:none"' : '' ?>>
                <p class="login-subtitle">Votre compte sera activé par un administrateur.</p>

                <div id="reg-error" class="login-error" style="display:none"></div>
                <div id="reg-success" class="reg-success" style="display:none">
                    <p>✅ Votre demande a été envoyée.</p>
                    <p>Un administrateur validera votre compte prochainement.</p>
                </div>

                <form id="reg-form" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg-prenom">Prénom</label>
                            <input type="text" id="reg-prenom" name="prenom" placeholder="Jean" autocomplete="given-name" required>
                        </div>
                        <div class="form-group">
                            <label for="reg-nom">Nom</label>
                            <input type="text" id="reg-nom" name="nom" placeholder="Dupont" autocomplete="family-name" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reg-username"><?= LOCAL_DEV ? 'Identifiant' : 'Adresse email <span class="form-hint">(sert d\'identifiant pour la connexion)</span>' ?></label>
                        <input type="<?= LOCAL_DEV ? 'text' : 'email' ?>" id="reg-username" name="username" placeholder="<?= LOCAL_DEV ? 'ex: admin' : 'exemple@email.com' ?>" autocomplete="<?= LOCAL_DEV ? 'username' : 'email' ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="reg-password">Mot de passe <span class="form-hint">(8 caractères minimum)</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="reg-password" name="password" placeholder="8 caractères minimum" autocomplete="new-password" required>
                            <button type="button" class="btn-toggle-password" onclick="togglePassword(this)" aria-label="Voir le mot de passe">
                                <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reg-confirm">Confirmer le mot de passe</label>
                        <div class="password-wrapper">
                            <input type="password" id="reg-confirm" name="password_confirm" placeholder="Répétez votre mot de passe" autocomplete="new-password" required>
                            <button type="button" class="btn-toggle-password" onclick="togglePassword(this)" aria-label="Voir le mot de passe">
                                <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn-login" id="reg-submit">Créer mon compte</button>
                </form>
            </div>

            <a href="/" class="back-home">← Retour à l'accueil</a>
        </div>
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

        function switchTab(tab) {
            const isLogin = tab === 'login';
            document.getElementById('panel-login').style.display        = isLogin ? '' : 'none';
            document.getElementById('panel-inscription').style.display  = isLogin ? 'none' : '';
            document.getElementById('tab-login').classList.toggle('auth-tab--active', isLogin);
            document.getElementById('tab-inscription').classList.toggle('auth-tab--active', !isLogin);
            document.querySelector('.login-card').classList.toggle('login-card--wide', !isLogin);
        }

        // Onglet initial
        switchTab('<?= $defaultTab ?>');

        // Formulaire inscription
        document.getElementById('reg-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn   = document.getElementById('reg-submit');
            const error = document.getElementById('reg-error');
            error.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Envoi…';

            const fd = new FormData(this);
            try {
                const res  = await fetch('/php/auth/register.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    this.style.display = 'none';
                    document.getElementById('reg-success').style.display = 'block';
                } else {
                    error.textContent   = data.error || 'Une erreur est survenue.';
                    error.style.display = 'block';
                    btn.disabled    = false;
                    btn.textContent = 'Créer mon compte';
                }
            } catch {
                error.textContent   = 'Erreur réseau. Veuillez réessayer.';
                error.style.display = 'block';
                btn.disabled    = false;
                btn.textContent = 'Créer mon compte';
            }
        });
    </script>
</body>
</html>
