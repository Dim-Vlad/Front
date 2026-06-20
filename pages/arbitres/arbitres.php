<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('arbitre') && !has_role('admin') && !has_role('bureau')) {
    header('Location: /pages/tableau-de-bord.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Arbitres et Marqueurs - VBO">
    <link href="/css/styles.css" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur.css" rel="stylesheet">
    <title>Arbitres &amp; Marqueurs - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .sheet-wrapper {
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 1rem 3rem;
        }
        .sheet-frame {
            width: 100%;
            height: 700px;
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            background: white;
        }
        .sheet-back {
            display: inline-block;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: #888;
            text-decoration: none;
            transition: color 0.2s;
        }
        .sheet-back:hover { color: var(--secondary-color); }
    </style>
</head>

<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Arbitres &amp; Marqueurs</h1>
                <p class="explication">Planning et feuilles de match pour les arbitres et marqueurs.</p>
            </div>
        </div>
    </div>

    <div class="sheet-wrapper">
        <a href="/pages/tableau-de-bord.php" class="sheet-back">← Retour au tableau de bord</a>
        <iframe
            class="sheet-frame"
            <iframe src="https://docs.google.com/spreadsheets/d/e/2PACX-1vSU4ybuxVW919JSFR-_pI7DsORsjFe8QpAWSCMdggCurNZ9bgse6apd1Dht3opADvkjbs9Sr3i3D7fZ/pubhtml?widget=true&amp;headers=false"></iframe>
        </iframe>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.html', 'footer');
    </script>
</body>
</html>
