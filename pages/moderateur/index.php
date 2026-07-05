<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('moderateur')) {
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
    <title>Modération - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260705" rel="stylesheet">
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
                <h1>Modération</h1>
                <p>Connecté en tant que <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong></p>
            </div>
        </div>
    </div>

    <div class="dashboard-container">

        <div class="dashboard-two-col">

            <!-- Ligne 1 gauche : Événements -->
            <div class="dashboard-section">
                <h2 class="dashboard-section-title">📅 Événements</h2>
                <div class="dashboard-cards">

                    <a href="/pages/admin/evenements.php" class="dashboard-card card-admin">
                        <div class="card-icon">📅</div>
                        <h2>Événements</h2>
                        <p>Créer et gérer les événements du club (tournois, stages, loto…).</p>
                    </a>

                    <a href="/pages/admin/tournois.php" class="dashboard-card card-admin">
                        <div class="card-icon">🏐</div>
                        <h2>Pages Tournois</h2>
                        <p>Créer et gérer les pages des tournois (inscription, tableau des scores).</p>
                    </a>

                </div>
            </div>

            <!-- Ligne 1 droite : Jeux -->
            <div class="dashboard-section">
                <h2 class="dashboard-section-title">🎮 Jeux</h2>
                <div class="dashboard-cards">

                    <a href="/pages/admin/pronostics.php" class="dashboard-card card-admin">
                        <div class="card-icon">🎯</div>
                        <h2>Pronostics</h2>
                        <p>Créer des matchs et saisir les résultats pour les pronostics des membres.</p>
                    </a>

                    <a href="/pages/admin/quiz.php" class="dashboard-card card-admin">
                        <div class="card-icon">🧠</div>
                        <h2>Quiz</h2>
                        <p>Créer et gérer les questions du quiz pour les membres.</p>
                    </a>

                </div>
            </div>

        </div>

        <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
