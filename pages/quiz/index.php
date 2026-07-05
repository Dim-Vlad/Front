<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();

$pdo    = get_pdo();
$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT q.id, q.question, q.choix_a, q.choix_b, q.choix_c, q.choix_d,
            q.points, q.explication,
            r.choix AS mon_choix, r.correct AS mon_correct, r.points_obtenus AS mes_points
     FROM quiz_questions q
     LEFT JOIN quiz_reponses r ON r.question_id = q.id AND r.user_id = ?
     WHERE q.actif = 1
     ORDER BY q.created_at ASC'
);
$stmt->execute([$userId]);
$allQuestions = $stmt->fetchAll();

$aRepondre = array_values(array_filter($allQuestions, fn($q) => $q['mon_choix'] === null));
$repondues = array_values(array_filter($allQuestions, fn($q) => $q['mon_choix'] !== null));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz VBO</title>
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
                <h1>Quiz VBO</h1>
                <p>Testez vos connaissances sur le volley-ball et le club.</p>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;padding:0 16px;max-width:760px;margin:0 auto">
        <a href="/pages/auth/tableau-de-bord.php" class="back-btn" style="margin:0">← Tableau de bord</a>
        <a href="/pages/pronostics/classement.php" class="back-btn" style="margin:0">🏆 Classement général</a>
    </div>

    <div class="quiz-container">

        <?php if (empty($allQuestions)): ?>
        <div class="quiz-empty">
            <p>Aucune question disponible pour le moment.<br>Revenez bientôt !</p>
        </div>
        <?php else: ?>

        <?php if (!empty($aRepondre)): ?>
        <h2 class="quiz-section-title">❓ Questions à répondre (<?= count($aRepondre) ?>)</h2>
        <?php foreach ($aRepondre as $q): ?>
        <div class="quiz-card" id="quiz-card-<?= $q['id'] ?>">
            <div class="quiz-points-badge"><?= $q['points'] ?> pt<?= $q['points'] > 1 ? 's' : '' ?></div>
            <div class="quiz-question-text"><?= htmlspecialchars($q['question']) ?></div>
            <div class="quiz-choices">
                <?php foreach (['a','b','c','d'] as $l):
                    if ($q['choix_' . $l] === null) continue; ?>
                <button class="quiz-choice-btn" onclick="repondre(<?= $q['id'] ?>, '<?= $l ?>', this)">
                    <span class="quiz-choice-letter"><?= strtoupper($l) ?></span>
                    <?= htmlspecialchars($q['choix_' . $l]) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="quiz-empty" style="padding:20px 0">
            <p>🎉 Vous avez répondu à toutes les questions disponibles !</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($repondues)): ?>
        <h2 class="quiz-section-title">✅ Mes réponses (<?= count($repondues) ?>)</h2>
        <?php foreach ($repondues as $q): ?>
        <div class="quiz-card quiz-card--answered">
            <div class="quiz-points-badge"><?= $q['points'] ?> pt<?= $q['points'] > 1 ? 's' : '' ?></div>
            <div class="quiz-question-text"><?= htmlspecialchars($q['question']) ?></div>
            <div class="quiz-choices">
                <?php foreach (['a','b','c','d'] as $l):
                    if ($q['choix_' . $l] === null) continue;
                    if ($l === $q['mon_choix'] && $q['mon_correct'])  $cls = 'quiz-choice-btn--correct';
                    elseif ($l === $q['mon_choix'] && !$q['mon_correct']) $cls = 'quiz-choice-btn--wrong';
                    else $cls = 'quiz-choice-btn--neutral';
                ?>
                <button class="quiz-choice-btn <?= $cls ?>" disabled>
                    <span class="quiz-choice-letter"><?= strtoupper($l) ?></span>
                    <?= htmlspecialchars($q['choix_' . $l]) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="quiz-result-row">
                <span class="quiz-result-icon"><?= $q['mon_correct'] ? '✅' : '❌' ?></span>
                <span class="quiz-result-text">
                    <?= $q['mon_correct'] ? 'Bonne réponse !' : 'Mauvaise réponse.' ?>
                    <?= $q['explication'] ? ' ' . htmlspecialchars($q['explication']) : '' ?>
                </span>
                <span class="quiz-pts-earned<?= $q['mes_points'] > 0 ? '' : ' quiz-pts-earned--zero' ?>">
                    <?= $q['mes_points'] > 0 ? '+' . $q['mes_points'] . ' pt' . ($q['mes_points'] > 1 ? 's' : '') : '0 pt' ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <div id="footer"></div>
    <script src="/js/main.js"></script>
    <script>

    function repondre(qid, choix, clickedBtn) {
        const card = document.getElementById('quiz-card-' + qid);
        const btns = card.querySelectorAll('.quiz-choice-btn');
        btns.forEach(b => b.disabled = true);

        const fd = new FormData();
        fd.append('question_id', qid);
        fd.append('choix', choix);

        fetch('/php/quiz/repondre.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert(data.error || 'Erreur');
                    btns.forEach(b => b.disabled = false);
                    return;
                }

                btns.forEach(b => {
                    const letter = b.querySelector('.quiz-choice-letter').textContent.trim().toLowerCase();
                    if (letter === choix && data.correct)  b.classList.add('quiz-choice-btn--correct');
                    else if (letter === choix)             b.classList.add('quiz-choice-btn--wrong');
                    else                                   b.classList.add('quiz-choice-btn--neutral');
                });

                const ptsLabel = data.points > 0
                    ? '+' + data.points + ' pt' + (data.points > 1 ? 's' : '')
                    : '0 pt';

                const row = document.createElement('div');
                row.className = 'quiz-result-row';
                row.innerHTML =
                    '<span class="quiz-result-icon">' + (data.correct ? '✅' : '❌') + '</span>' +
                    '<span class="quiz-result-text">' +
                        (data.correct ? 'Bonne réponse !' : 'Mauvaise réponse.') +
                        (data.explication ? ' ' + data.explication : '') +
                    '</span>' +
                    '<span class="quiz-pts-earned' + (data.points > 0 ? '' : ' quiz-pts-earned--zero') + '">' + ptsLabel + '</span>';
                card.appendChild(row);
                card.classList.add('quiz-card--answered');
            })
            .catch(() => {
                alert('Erreur réseau');
                btns.forEach(b => b.disabled = false);
            });
    }
    </script>
</body>
</html>
