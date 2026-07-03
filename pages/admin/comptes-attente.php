<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('admin')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$pdo = get_pdo();

$comptes = $pdo->query(
    'SELECT u.id, u.prenom, u.nom, u.username, u.actif FROM users u WHERE u.actif = 0 ORDER BY u.id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$validRoles = ['admin', 'moderateur', 'bureau', 'entraineur', 'arbitre', 'adherent'];
$roleLabels = [
    'admin'      => 'Admin',
    'moderateur' => 'Modérateur',
    'bureau'     => 'Bureau',
    'entraineur' => 'Entraîneur',
    'arbitre'    => 'Arbitre',
    'adherent'   => 'Adhérent',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptes en attente - VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link href="/css/admin.css?v=20260702" rel="stylesheet">
    <link href="/css/comptes-attente.css?v=20260702" rel="stylesheet">
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
                <h1>Comptes en attente</h1>
                <p>Validez ou refusez les demandes d'inscription.</p>
            </div>
        </div>
    </div>

    <a href="/pages/admin/index.php" class="back-btn">← Retour</a>

    <div class="ca-container">

        <?php if (empty($comptes)): ?>
        <p class="ca-empty">✅ Aucun compte en attente de validation.</p>
        <?php else: ?>

        <p class="ca-count"><?= count($comptes) ?> compte<?= count($comptes) > 1 ? 's' : '' ?> en attente</p>

        <ul class="ca-list" id="ca-list">
            <?php foreach ($comptes as $c): ?>
            <li class="ca-item" id="ca-item-<?= $c['id'] ?>">
                <div class="ca-info">
                    <strong><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></strong>
                    <span class="ca-email"><?= htmlspecialchars($c['username']) ?></span>
                </div>
                <div class="ca-actions">
                    <div class="roles-checkboxes ca-roles">
                        <?php foreach ($validRoles as $r): ?>
                        <label class="role-check role-check--<?= $r ?>">
                            <input type="checkbox" name="ca-role-<?= $c['id'] ?>[]" value="<?= $r ?>"<?= $r === 'adherent' ? ' checked' : '' ?>>
                            <?= $roleLabels[$r] ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="ca-btns">
                        <button class="ca-btn ca-btn--activate" onclick="activateUser(<?= $c['id'] ?>)">✅ Activer</button>
                        <button class="ca-btn ca-btn--reject"   onclick="rejectUser(<?= $c['id'] ?>, '<?= htmlspecialchars($c['prenom'] . ' ' . $c['nom'], ENT_QUOTES) ?>')">❌ Refuser</button>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php endif; ?>

        <a href="/pages/admin/index.php" class="back-btn">← Retour</a>
    </div>

    <div id="footer"></div>
    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.php', 'footer');

        async function activateUser(id) {
            const checked = [...document.querySelectorAll('input[name="ca-role-' + id + '[]"]:checked')];
            if (checked.length === 0) { alert('Veuillez sélectionner au moins un rôle.'); return; }
            if (!confirm('Activer ce compte avec le(s) rôle(s) sélectionné(s) ?')) return;
            const fd = new FormData();
            fd.append('user_id', id);
            checked.forEach(cb => fd.append('roles[]', cb.value));
            const res  = await fetch('/php/auth/activate_user.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                document.getElementById('ca-item-' + id).remove();
                updateCount();
            } else {
                alert('Erreur : ' + (data.error || 'inconnue'));
            }
        }

        async function rejectUser(id, nom) {
            if (!confirm('Refuser et supprimer le compte de ' + nom + ' ?')) return;
            const fd = new FormData();
            fd.append('user_id', id);
            const res  = await fetch('/php/auth/reject_user.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                document.getElementById('ca-item-' + id).remove();
                updateCount();
            } else {
                alert('Erreur : ' + (data.error || 'inconnue'));
            }
        }

        function updateCount() {
            const remaining = document.querySelectorAll('.ca-item').length;
            const countEl   = document.querySelector('.ca-count');
            if (remaining === 0) {
                document.getElementById('ca-list').remove();
                if (countEl) countEl.remove();
                const container = document.querySelector('.ca-container');
                const p = document.createElement('p');
                p.className = 'ca-empty';
                p.textContent = '✅ Aucun compte en attente de validation.';
                container.insertBefore(p, container.firstChild);
            } else if (countEl) {
                countEl.textContent = remaining + ' compte' + (remaining > 1 ? 's' : '') + ' en attente';
            }
        }
    </script>
</body>
</html>
