<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (has_role('arbitre')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pointage des présences - VBO">
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur.css?v=20260623" rel="stylesheet">
    <title>Pointage Présences - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
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
    </style>
</head>

<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Pointage Présences</h1>
                <p class="explication">Suivez le pointage des présences aux entraînements.</p>
            </div>
        </div>
    </div>

    <div class="sheet-wrapper">
        <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>
        <iframe
            class="sheet-frame"
            src="COLLER_ICI_URL_EMBED_GOOGLE_SHEET_PRESENCES"
            allowfullscreen>
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
