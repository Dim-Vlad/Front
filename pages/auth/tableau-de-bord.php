<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();

$user = current_user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
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
                <h1>Tableau de bord</h1>
                <?php
                    $fullname = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
                    $display  = $fullname !== '' ? $fullname : $user['username'];
                ?>
                <p>Bonjour, <strong><?= htmlspecialchars($display) ?></strong> 👋</p>
                <?php if (!empty($user['roles'])):
                    $roleLabels = ['entraineur'=>'Entraineur','admin'=>'Admin','arbitre'=>'Arbitre','bureau'=>'Bureau','moderateur'=>'Modérateur','adherent'=>'Adhérent'];
                ?>
                <div class="user-roles">
                    <?php foreach ($user['roles'] as $r): ?>
                    <span class="badge badge--<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($roleLabels[$r] ?? ucfirst($r)) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-cards">

            <?php if (has_role('admin')): ?>
            <a href="/pages/admin/index.php" class="dashboard-card card-admin card-admin--featured">
                <div class="card-icon">⚙️</div>
                <h2>Administration</h2>
                <p>Gestion des utilisateurs.</p>
            </a>
            <?php endif; ?>

            <a href="/pages/leClub/espace-entraineur.php" class="dashboard-card">
                <div class="card-icon">📥</div>
                <h2>Ressources</h2>
                <p>Documents téléchargeables et ressources FFVB.</p>
            </a>

            <?php if (has_any_role(['bureau', 'admin', 'moderateur'])): ?>
            <a href="/pages/bureau/drive.php" class="dashboard-card">
                <div class="card-icon">🗂️</div>
                <h2>Dossiers Commissions</h2>
                <p>Accédez aux documents partagés sur Google Drive.</p>
            </a>
            <?php endif; ?>

            <?php if (has_role('arbitre') || has_role('admin') || has_role('bureau')): ?>
            <a href="/pages/arbitres/arbitres.php" class="dashboard-card">
                <div class="card-icon">📣</div>
                <h2>Arbitres &amp; Marqueurs</h2>
                <p>Planning et feuilles de match pour les arbitres et marqueurs.</p>
            </a>
            <?php endif; ?>

            <?php if (!has_any_role(['arbitre', 'adherent'])): ?>
            <a href="/pages/leClub/minibus.php" class="dashboard-card">
                <div class="card-icon">🚌</div>
                <h2>Réservations Minibus</h2>
                <p>Consultez les réservations des minibus de la mairie et celui du club.</p>
            </a>

            <a href="/pages/leClub/presences.php" class="dashboard-card">
                <div class="card-icon">✅</div>
                <h2>Pointage Présences</h2>
                <p>Suivez le pointage des présences aux entraînements et aux matchs.</p>
            </a>
            <?php endif; ?>

        </div>

        <a href="/php/logout.php" class="btn-logout"
            onclick="return confirm('Voulez-vous vraiment vous déconnecter ?')">Se déconnecter</a>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.php', 'footer');
    </script>
</body>
</html>
