<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('admin')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$user = current_user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - VBO</title>
    <link href="/css/styles.css?v=20260623" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/x-icon">
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
                <h1>Administration</h1>
                <p>Connecté en tant que <strong><?= htmlspecialchars($user['username']) ?></strong></p>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-cards">

            <a href="/pages/admin/gestion-utilisateurs.php" class="dashboard-card card-admin">
                <div class="card-icon">👥</div>
                <h2>Gestion des utilisateurs</h2>
                <p>Créer, consulter et supprimer les comptes entraineurs.</p>
            </a>

            <a href="/pages/admin/journal.php" class="dashboard-card card-admin">
                <div class="card-icon">📝</div>
                <h2>Journal des activités</h2>
                <p>Historique des modifications effectuées par les modérateurs et administrateurs.</p>
            </a>

            <a href="/pages/admin/journal-connexions.php" class="dashboard-card card-admin">
                <div class="card-icon">🔑</div>
                <h2>Journal des connexions</h2>
                <p>Historique des connexions à l'espace membres.</p>
            </a>

        </div>

        <a href="/pages/auth/tableau-de-bord.php" class="btn-logout">← Retour au tableau de bord</a>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.html', 'footer');
    </script>
</body>
</html>
