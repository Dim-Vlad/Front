<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('admin')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$user      = current_user();
$pdo       = get_pdo();
$nbAttente = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE actif = 0')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260703" rel="stylesheet">
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
                <h1>Administration</h1>
                <p>Connecté en tant que <strong><?= htmlspecialchars($user['username']) ?></strong></p>
            </div>
        </div>
    </div>

    <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    <div class="dashboard-container">

        <!-- Section Utilisateurs -->
        <div class="dashboard-section">
            <h2 class="dashboard-section-title">👥 Utilisateurs</h2>
            <div class="dashboard-cards">

                <a href="/pages/admin/gestion-utilisateurs.php" class="dashboard-card card-admin">
                    <div class="card-icon">👤</div>
                    <h2>Gestion des utilisateurs</h2>
                    <p>Créer, consulter et supprimer les comptes membres.</p>
                </a>

                <a href="/pages/admin/comptes-attente.php" class="dashboard-card card-admin">
                    <div class="card-icon">
                        ⏳<?php if ($nbAttente > 0): ?>
                        <span class="badge-attente"><?= $nbAttente ?></span>
                        <?php endif; ?>
                    </div>
                    <h2>Comptes en attente</h2>
                    <p>Valider ou refuser les demandes d'inscription des adhérents.</p>
                </a>

            </div>
        </div>

        <!-- Section Jeux -->
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

        <!-- Section Journaux -->
        <div class="dashboard-section">
            <h2 class="dashboard-section-title">📋 Journaux</h2>
            <div class="dashboard-cards">

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
        </div>

        <!-- Section Événements -->
        <div class="dashboard-section">
            <h2 class="dashboard-section-title">📅 Événements</h2>
            <div class="dashboard-cards">

                <a href="/pages/admin/tournois.php" class="dashboard-card card-admin">
                    <div class="card-icon">🏐</div>
                    <h2>Pages Tournois</h2>
                    <p>Créer et gérer les pages des tournois (inscription, tableau des scores).</p>
                </a>

            </div>
        </div>

        <!-- Section Paramètres -->
        <div class="dashboard-section">
            <h2 class="dashboard-section-title">⚙️ Paramètres</h2>
            <div class="dashboard-cards">

                <a href="/pages/admin/gestion-site.php" class="dashboard-card card-admin">
                    <div class="card-icon">🌐</div>
                    <h2>Gestion du site</h2>
                    <p>Modifier l'adresse, l'email de contact, les réseaux sociaux et les informations du club.</p>
                </a>

            </div>
        </div>

        <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    </div>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.php', 'footer');
    </script>
</body>
</html>
