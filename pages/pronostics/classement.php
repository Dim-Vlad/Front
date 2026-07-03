<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();

$pdo    = get_pdo();
$userId = (int)$_SESSION['user_id'];

$rows = $pdo->query(
    'SELECT * FROM (
        SELECT u.id, u.prenom, u.nom,
               COALESCE(SUM(
                   CASE
                       WHEN m.resultat_sets IS NOT NULL AND v.choix_sets = m.resultat_sets THEN 3
                       WHEN m.resultat_victoire IS NOT NULL AND v.choix_victoire = m.resultat_victoire THEN 1
                       ELSE 0
                   END
               ), 0) AS pts_prono,
               COALESCE((
                   SELECT SUM(qr.points_obtenus) FROM quiz_reponses qr WHERE qr.user_id = u.id
               ), 0) AS pts_quiz,
               COUNT(DISTINCT v.id) AS nb_votes,
               COALESCE((
                   SELECT COUNT(*) FROM quiz_reponses qr WHERE qr.user_id = u.id
               ), 0) AS nb_quiz
        FROM users u
        LEFT JOIN pronostics_votes v ON v.user_id = u.id
        LEFT JOIN pronostics_matchs m ON m.id = v.match_id
        WHERE u.actif = 1
        GROUP BY u.id, u.prenom, u.nom
    ) AS sub
    WHERE nb_votes > 0 OR nb_quiz > 0
    ORDER BY (pts_prono + pts_quiz) DESC, pts_prono DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$nbMatchsAvecResultat = (int)$pdo->query(
    'SELECT COUNT(*) FROM pronostics_matchs WHERE resultat_victoire IS NOT NULL'
)->fetchColumn();

$nbQuizActifs = (int)$pdo->query(
    'SELECT COUNT(*) FROM quiz_questions WHERE actif = 1'
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement général - VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/quiz.css?v=20260703" rel="stylesheet">
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
                <h1>Classement général</h1>
                <p>
                    <?= $nbMatchsAvecResultat ?> match<?= $nbMatchsAvecResultat > 1 ? 's' : '' ?> avec résultat
                    · <?= $nbQuizActifs ?> question<?= $nbQuizActifs > 1 ? 's' : '' ?> de quiz
                </p>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;padding:0 16px;max-width:860px;margin:0 auto">
        <a href="/pages/auth/tableau-de-bord.php" class="back-btn" style="margin:0">← Tableau de bord</a>
        <a href="/pages/pronostics/index.php" class="back-btn" style="margin:0">← Pronostics</a>
        <a href="/pages/quiz/index.php" class="back-btn" style="margin:0">← Quiz</a>
    </div>

    <div class="classement-container">

        <?php if (empty($rows)): ?>
        <p class="classement-empty">Aucune activité enregistrée pour l'instant.<br>Participez aux pronostics et au quiz !</p>
        <?php else: ?>

        <div class="classement-legend">
            <span><span class="legend-dot legend-dot--prono"></span> Pronostics — 1 pt (V/D) · 3 pts (score exact)</span>
            <span><span class="legend-dot legend-dot--quiz"></span> Quiz — points selon difficulté</span>
        </div>

        <table class="classement-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Joueur</th>
                    <th class="cl-pts cl-pts--prono">🔮 Pronostics</th>
                    <th class="cl-pts cl-pts--quiz">🧠 Quiz</th>
                    <th class="cl-total">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $rank    = 0;
            $prevPts = -1;
            $display = 0;
            foreach ($rows as $row):
                $display++;
                $total = (int)$row['pts_prono'] + (int)$row['pts_quiz'];
                if ($total !== $prevPts) {
                    $rank   = $display;
                    $prevPts = $total;
                }
                $isMe  = (int)$row['id'] === $userId;
                $medal = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $rank };
            ?>
            <tr <?= $isMe ? 'class="is-me"' : '' ?>>
                <td class="cl-rank"><?= $medal ?></td>
                <td class="cl-name">
                    <?= htmlspecialchars($row['prenom'] . ' ' . $row['nom']) ?>
                    <?= $isMe ? '<span class="me-tag">(moi)</span>' : '' ?>
                </td>
                <td class="cl-pts cl-pts--prono"><?= (int)$row['pts_prono'] ?></td>
                <td class="cl-pts cl-pts--quiz"><?= (int)$row['pts_quiz'] ?></td>
                <td class="cl-total"><?= $total ?></td>
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
