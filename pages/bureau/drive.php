<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_any_role(['bureau', 'admin', 'moderateur'])) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$pdo      = get_pdo();
$dossiers = $pdo->query("SELECT id, nom, url FROM drive_dossiers ORDER BY ordre ASC, id ASC")->fetchAll();
$isAdmin  = has_role('admin');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dossiers partagés des commissions du VBO.">
    <title>Dossiers Commissions - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/leClub/minibus.css?v=20260623" rel="stylesheet">
    <link href="/css/bureau/drive.css?v=20260707" rel="stylesheet">
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
                <h1>Dossiers Commissions</h1>
                <p class="explication">Documents partagés réservés aux membres des commissions.</p>
            </div>
        </div>
    </div>

    <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    <main class="sheet-main">

        <div class="sheet-info-card">
            <p>Accès aux dossiers des <strong>commissions du club</strong> hébergés sur le Drive VBO. Sélectionnez une commission via les onglets ci-dessous.</p>
            <?php if ($isAdmin): ?>
            <a href="/pages/admin/drive-dossiers.php" class="btn-edit-url">⚙ Gérer les dossiers</a>
            <?php endif; ?>
        </div>

        <?php if (empty($dossiers)): ?>
        <div class="sheet-card drive-empty">
            <p>Aucun dossier configuré.</p>
            <?php if ($isAdmin): ?>
            <a href="/pages/admin/drive-dossiers.php" class="btn-drive-admin">Ajouter des dossiers</a>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <div class="drive-wrapper">
            <div class="drive-tabs">
                <?php foreach ($dossiers as $i => $d): ?>
                <?php $label = trim(preg_replace('/\bcommission\b\s*/i', '', $d['nom'])); ?>
                <button class="drive-tab<?= $i === 0 ? ' active' : '' ?>"
                        data-url="<?= htmlspecialchars($d['url'], ENT_QUOTES) ?>"
                        onclick="switchTab(this)">
                    <?= htmlspecialchars($label ?: $d['nom']) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="drive-card">
                <iframe id="drive-frame"
                        src="<?= htmlspecialchars($dossiers[0]['url'], ENT_QUOTES) ?>"
                        allowfullscreen></iframe>
            </div>
        </div>

        <?php endif; ?>

    </main>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script>
    function switchTab(btn) {
        document.querySelectorAll('.drive-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('drive-frame').src = btn.dataset.url;
    }
    </script>
</body>
</html>
