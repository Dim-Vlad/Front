<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();

$pdo    = get_pdo();
$userId = (int)$_SESSION['user_id'];

$rows = $pdo->query(
    'SELECT u.id, u.prenom, u.nom,
            COUNT(v.id) AS nb_pronostics,
            COALESCE(SUM(
                CASE
                    WHEN m.resultat_sets IS NOT NULL AND v.choix_sets = m.resultat_sets THEN 3
                    WHEN m.resultat_victoire IS NOT NULL AND v.choix_victoire = m.resultat_victoire THEN 1
                    ELSE 0
                END
            ), 0) AS total_points
    FROM users u
    INNER JOIN pronostics_votes v ON v.user_id = u.id
    INNER JOIN pronostics_matchs m ON m.id = v.match_id
    WHERE u.actif = 1
    GROUP BY u.id, u.prenom, u.nom
    ORDER BY total_points DESC, nb_pronostics DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$nbMatchsAvecResultat = (int)$pdo->query(
    'SELECT COUNT(*) FROM pronostics_matchs WHERE resultat_victoire IS NOT NULL'
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement pronostics - VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/pronostics.css?v=20260702" rel="stylesheet">
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
                <h1>Classement pronostics</h1>
                <p><?= $nbMatchsAvecResultat ?> match<?= $nbMatchsAvecResultat > 1 ? 's' : '' ?> avec résultat · Barème : 1 pt (victoire/défaite) · 3 pts (score exact)</p>
            </div>
        </div>
    </div>

    <a href="/pages/pronostics/index.php" class="back-btn">← Retour aux pronostics</a>

    <div class="classement-container">

        <?php if (empty($rows)): ?>
        <p class="classement-empty">Aucun pronostic enregistré pour l'instant.<br>Participez aux prochains matchs !</p>
        <?php else: ?>

        <table class="classement-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Joueur</th>
                    <th style="text-align:center">Pronostics</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $rank = 0;
            $prevPts = -1;
            $display = 0;
            foreach ($rows as $row):
                $display++;
                if ((int)$row['total_points'] !== $prevPts) {
                    $rank = $display;
                    $prevPts = (int)$row['total_points'];
                }
                $isMe = (int)$row['id'] === $userId;

                if ($rank === 1)      $rankClass = 'rank-gold';
                elseif ($rank === 2)  $rankClass = 'rank-silver';
                elseif ($rank === 3)  $rankClass = 'rank-bronze';
                else                  $rankClass = '';

                $medal = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $rank };
            ?>
            <tr <?= $isMe ? 'class="is-me"' : '' ?>>
                <td class="classement-rank <?= $rankClass ?>"><?= $medal ?></td>
                <td class="classement-name">
                    <?= htmlspecialchars($row['prenom'] . ' ' . $row['nom']) ?>
                    <?= $isMe ? ' <span style="font-size:.75rem;color:#888">(moi)</span>' : '' ?>
                </td>
                <td class="classement-prono"><?= (int)$row['nb_pronostics'] ?></td>
                <td class="classement-pts"><?= (int)$row['total_points'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>

    </div>

    <div id="footer"></div>
    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.php', 'footer');
    </script>
</body>
</html>
