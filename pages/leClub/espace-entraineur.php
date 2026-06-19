<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Le VBO te propose un espace dédié aux entraineurs.">
    <link href="/css/styles.css" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur.css" rel="stylesheet">
    <title>Espace entraineurs - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
</head>

<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Espace entraineurs</h1>
                <p class="explication">
                    Le Volley-Ball Ollioulais met à disposition des entraineurs un espace dédié pour les aider dans leur mission.
                    <br>Vous trouverez ici des documents utiles, des conseils et des outils pour vous accompagner dans votre rôle.
                    <br>Si vous avez des questions ou des suggestions, n'hésitez pas à nous contacter.
                </p>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="doc-entraineurs">
            <h2>Documents téléchargeables</h2>
            <ul class="download-list">
                <li><a href="/documents/entraineur/Feuille-De-Composition_équipe.pdf" target="_blank">1 - Feuille de composition d'équipe (version PDF)</a></li>
            </ul>
        </div>

        <hr class="separator">

        <div class="siteffvb">
            <h2>Ressources Volley</h2>
            <p>Retrouvez sur le site mis à disposition par la FFVB des documents utiles pour les entraineurs.</p>
            <p>Identifiant : ffvolley<br>Mot de passe : ffvolley2021</p>
            <p>ENJOY !</p>
            <div class="btn">
                <a href="https://ressourcesvolley.com/" target="_blank">Accéder au site</a>
            </div>
        </div>
    </div>

    <a href="/php/logout.php" class="btn-logout-page">Se déconnecter</a>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.html', 'footer');
    </script>
</body>
</html>
