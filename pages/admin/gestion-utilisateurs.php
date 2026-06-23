<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('admin')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$pdo = get_pdo();
$message = '';
$messageType = '';

$validRoles = ['entraineur', 'admin', 'arbitre', 'bureau', 'moderateur'];
$roleLabels = [
    'entraineur' => 'Entraineur',
    'admin'      => 'Admin',
    'arbitre'    => 'Arbitre',
    'bureau'     => 'Bureau',
    'moderateur' => 'Modérateur',
];

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['user_id'] ?? 0);
    $currentId = (int)(current_user()['id']);
    if ($id > 0 && $id !== $currentId) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        $message = 'Utilisateur supprimé.';
        $messageType = 'success';
    } else {
        $message = 'Impossible de supprimer ce compte.';
        $messageType = 'error';
    }
}

// Modification des rôles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_roles') {
    $id = (int)($_POST['user_id'] ?? 0);
    $newRoles = array_values(array_filter($_POST['new_roles'] ?? [], fn($r) => in_array($r, $validRoles, true)));
    $currentId = (int)(current_user()['id']);

    if ($id <= 0 || $id === $currentId) {
        $message = 'Impossible de modifier ce compte.';
        $messageType = 'error';
    } elseif (empty($newRoles)) {
        $message = 'Veuillez sélectionner au moins un rôle.';
        $messageType = 'error';
    } else {
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
        $stmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = ?');
        foreach ($newRoles as $r) {
            $stmt->execute([$id, $r]);
        }
        $message = 'Rôles modifiés avec succès.';
        $messageType = 'success';
    }
}

// Changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $id       = (int)($_POST['user_id'] ?? 0);
    $password = $_POST['new_password'] ?? '';

    if ($id <= 0) {
        $message = 'Utilisateur invalide.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Le mot de passe doit contenir au moins 8 caractères.';
        $messageType = 'error';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $id]);
        $message = 'Mot de passe modifié avec succès.';
        $messageType = 'success';
    }
}

// Création
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $roles    = array_values(array_filter($_POST['roles'] ?? [], fn($r) => in_array($r, $validRoles, true)));
    $prenom   = trim($_POST['prenom'] ?? '');
    $nom      = trim($_POST['nom'] ?? '');

    if (empty($roles)) $roles = ['entraineur'];

    if ($username === '' || $password === '' || $prenom === '' || $nom === '') {
        $message = 'Tous les champs sont obligatoires.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Le mot de passe doit contenir au moins 8 caractères.';
        $messageType = 'error';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (username, password, prenom, nom) VALUES (?, ?, ?, ?)')->execute([$username, $hash, $prenom, $nom]);
            $newId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = ?');
            foreach ($roles as $r) {
                $stmt->execute([$newId, $r]);
            }
            $rolesStr = implode(', ', array_map(fn($r) => $roleLabels[$r] ?? $r, $roles));
            $message = 'Compte de ' . htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom) . ' créé avec le(s) rôle(s) : ' . $rolesStr . '.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = str_contains($e->getMessage(), 'Duplicate entry')
                ? 'Cet identifiant est déjà utilisé.'
                : 'Erreur lors de la création.';
            $messageType = 'error';
        }
    }
}

$users = $pdo->query(
    "SELECT u.id, u.username, u.prenom, u.nom, u.created_at,
            GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',') AS roles
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    GROUP BY u.id, u.username, u.prenom, u.nom, u.created_at
    ORDER BY u.created_at DESC"
)->fetchAll();
$currentId = (int)(current_user()['id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - VBO</title>
    <link href="/css/styles.css?v=20260623" rel="stylesheet">
    <link href="/css/admin.css?v=20260623" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Gestion des utilisateurs</h1>
                <p>Créez et gérez les comptes des membres.</p>
            </div>
        </div>
    </div>

    <div class="admin-container">

        <?php if ($message): ?>
            <div class="admin-alert admin-alert--<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de création -->
        <div class="admin-card">
            <h2>Créer un compte</h2>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" required>
                    </div>
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Identifiant <span class="hint">(pour la connexion)</span></label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe <span class="hint">(8 caractères min.)</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" required minlength="8">
                            <button type="button" class="btn-toggle-password" onclick="togglePassword(this)" aria-label="Voir le mot de passe">
                                <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Rôle(s)</label>
                        <div class="roles-box">
                            <div class="roles-checkboxes">
                                <?php foreach ($validRoles as $r): ?>
                                <label class="role-check">
                                    <input type="checkbox" name="roles[]" value="<?= $r ?>"<?= $r === 'entraineur' ? ' checked' : '' ?>>
                                    <?= $roleLabels[$r] ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-admin">Créer le compte</button>
            </form>
        </div>

        <!-- Liste des utilisateurs -->
        <div class="admin-card">
            <h2>Comptes existants (<?= count($users) ?>)</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nom complet</th>
                        <th>Identifiant</th>
                        <th>Rôle(s)</th>
                        <th>Créé le</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u):
                        $userRoles = $u['roles'] ? explode(',', $u['roles']) : [];
                    ?>
                    <tr>
                        <td data-label="Nom"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
                        <td data-label="Identifiant"><?= htmlspecialchars($u['username']) ?></td>
                        <td data-label="Rôle(s)">
                            <?php foreach ($userRoles as $r): ?>
                            <span class="badge badge--<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($roleLabels[$r] ?? $r) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td data-label="Créé le"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td class="actions-cell">
                            <button class="btn-edit"
                                onclick="openPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                Modifier MDP
                            </button>
                            <?php if ((int)$u['id'] !== $currentId): ?>
                            <button class="btn-edit"
                                onclick="openRoleModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', <?= htmlspecialchars(json_encode($userRoles), ENT_QUOTES) ?>)">
                                Modifier rôles
                            </button>
                            <form method="POST" onsubmit="return confirm('Supprimer «<?= htmlspecialchars($u['username']) ?>» ?')" style="display:inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-delete">Supprimer</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="/pages/admin/index.php" class="btn-back">← Retour à l'administration</a>
    </div>

    <!-- Modale changement de mot de passe -->
    <div id="modal-password" class="modal-overlay" style="display:none" onclick="closeModalOnOverlay(event)">
        <div class="modal-card">
            <h3>Modifier le mot de passe</h3>
            <p>Utilisateur : <strong id="modal-username"></strong></p>
            <form method="POST" class="admin-form" id="modal-form">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="user_id" id="modal-user-id">
                <div class="form-group">
                    <label>Nouveau mot de passe <span class="hint">(8 caractères min.)</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="modal-new-password" required minlength="8" autocomplete="new-password">
                        <button type="button" class="btn-toggle-password" onclick="togglePassword(this)" aria-label="Voir le mot de passe">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirmer le mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="modal-confirm-password" required minlength="8" autocomplete="new-password">
                        <button type="button" class="btn-toggle-password" onclick="togglePassword(this)" aria-label="Voir le mot de passe">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-admin">Enregistrer</button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modale modification des rôles -->
    <div id="modal-role" class="modal-overlay" style="display:none" onclick="closeRoleModalOnOverlay(event)">
        <div class="modal-card">
            <h3>Modifier les rôles</h3>
            <p>Utilisateur : <strong id="modal-role-username"></strong></p>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="change_roles">
                <input type="hidden" name="user_id" id="modal-role-user-id">
                <div class="form-group">
                    <label>Rôle(s) <span class="hint">(au moins un requis)</span></label>
                    <div class="roles-box">
                        <div class="roles-checkboxes">
                            <?php foreach ($validRoles as $r): ?>
                            <label class="role-check">
                                <input type="checkbox" name="new_roles[]" value="<?= $r ?>" id="modal-role-<?= $r ?>">
                                <?= $roleLabels[$r] ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-admin">Enregistrer</button>
                    <button type="button" class="btn-cancel" onclick="closeRoleModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.html', 'footer');

        function togglePassword(btn) {
            const input = btn.closest('.password-wrapper').querySelector('input');
            const eyeOn  = btn.querySelector('.icon-eye');
            const eyeOff = btn.querySelector('.icon-eye-off');
            const show = input.type === 'password';
            input.type           = show ? 'text' : 'password';
            eyeOn.style.display  = show ? 'none' : '';
            eyeOff.style.display = show ? '' : 'none';
        }

        function openPasswordModal(userId, username) {
            document.getElementById('modal-user-id').value = userId;
            document.getElementById('modal-username').textContent = username;
            document.getElementById('modal-new-password').value = '';
            document.getElementById('modal-confirm-password').value = '';
            document.getElementById('modal-password').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('modal-password').style.display = 'none';
        }

        function closeModalOnOverlay(e) {
            if (e.target === document.getElementById('modal-password')) closeModal();
        }

        function openRoleModal(userId, username, currentRoles) {
            document.getElementById('modal-role-user-id').value = userId;
            document.getElementById('modal-role-username').textContent = username;
            document.querySelectorAll('#modal-role input[type="checkbox"]').forEach(cb => cb.checked = false);
            currentRoles.forEach(r => {
                const cb = document.getElementById('modal-role-' + r);
                if (cb) cb.checked = true;
            });
            document.getElementById('modal-role').style.display = 'flex';
        }

        function closeRoleModal() {
            document.getElementById('modal-role').style.display = 'none';
        }

        function closeRoleModalOnOverlay(e) {
            if (e.target === document.getElementById('modal-role')) closeRoleModal();
        }

        document.getElementById('modal-form').addEventListener('submit', function(e) {
            const pwd     = document.getElementById('modal-new-password').value;
            const confirm = document.getElementById('modal-confirm-password').value;
            if (pwd !== confirm) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
            }
        });
    </script>
</body>
</html>