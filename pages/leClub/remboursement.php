<?php
require_once __DIR__ . '/../../php/auth.php';

$pdo     = get_pdo();
$equipes = [];
try {
    $equipes = $pdo->query("SELECT nom, groupe FROM equipes ORDER BY groupe DESC, ordre ASC, nom ASC")->fetchAll();
} catch (Exception) {}

$equipesSeniors = array_values(array_filter($equipes, fn($e) => $e['groupe'] === 'seniors'));
$equipesJeunes  = array_values(array_filter($equipes, fn($e) => $e['groupe'] === 'jeunes'));

$villes = [];
try {
    $villes = $pdo->query("SELECT nom, distance_km FROM remboursement_villes ORDER BY nom ASC")->fetchAll();
} catch (Exception) {}
$distancesVilles = array_combine(array_column($villes, 'nom'), array_map('floatval', array_column($villes, 'distance_km')));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Formulaire de remboursement des frais engagés pour le VBO.">
    <link href="/css/styles.css?v=20261005" rel="stylesheet">
    <link href="/css/leClub/remboursement.css?v=20261018" rel="stylesheet">
    <title>Remboursement - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Remboursement</h1>
                <p>Vous avez engagé des frais pour le club ? Soumettez votre demande directement en ligne.</p>
            </div>
        </div>
    </div>

    <main class="remb-main">

        <div class="remb-info">
            <h2>Avant de remplir le formulaire</h2>
            <div class="remb-rules">
                <div class="remb-rule">
                    <span class="remb-rule-num">1</span>
                    <p>Les pièces justificatives doivent être scannées sur fond blanc et jointes au format <strong>PDF</strong>.</p>
                </div>
                <div class="remb-rule">
                    <span class="remb-rule-num">2</span>
                    <p>Les demandes doivent être soumises dans un délai de <strong>15 jours</strong> après la date de la dépense.</p>
                </div>
            </div>
        </div>

        <div class="remb-form-card">
            <form id="remb-form">
                <div class="remb-form-row remb-form-row--3col">
                    <div class="form-group">
                        <label for="r-prenom">Prénom</label>
                        <input type="text" id="r-prenom" name="prenom" required>
                    </div>
                    <div class="form-group">
                        <label for="r-nom">Nom</label>
                        <input type="text" id="r-nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="r-email">Votre adresse e-mail</label>
                        <input type="email" id="r-email" name="email" required>
                    </div>
                </div>

                <input type="hidden" name="nb_lignes" id="remb-nb-lignes" value="0">

                <p class="remb-lignes-hint">Vous pouvez faire autant de remboursement que vous le souhaitez en une seule demande, il suffit d'ajouter autant de lignes que nécessaire via les deux boutons ci-dessous.</p>

                <div id="remb-lignes"></div>
                <p class="remb-lignes-empty" id="remb-lignes-empty">Ajoutez une ligne pour commencer.</p>

                <div class="remb-add-buttons">
                    <button type="button" class="remb-add-btn" onclick="addLigne('km')">🚗 Remboursement kilométrique</button>
                    <button type="button" class="remb-add-btn" onclick="addLigne('facture')">📁 Remboursement sur facture</button>
                </div>

                <div class="remb-submit-row">
                    <span class="remb-status" id="remb-status"></span>
                    <button type="submit" class="btn-save" id="remb-submit-btn">Envoyer la demande</button>
                </div>
            </form>
        </div>

    </main>

    <div id="footer"></div>

    <script>
        const REMB_EQUIPES_SENIORS = <?= json_encode(array_column($equipesSeniors, 'nom')) ?>;
        const REMB_EQUIPES_JEUNES  = <?= json_encode(array_column($equipesJeunes, 'nom')) ?>;
        const REMB_DISTANCES       = <?= json_encode($distancesVilles, JSON_FORCE_OBJECT) ?>;
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="/js/main.js?v=20260705"></script>
    <script src="/js/leClub/remboursement.js?v=20261019"></script>
</body>
</html>
