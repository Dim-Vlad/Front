<?php
require_once __DIR__ . '/../../php/auth.php';

$aVenir  = [];
$termines = [];

function formatDate(?string $debut, ?string $fin): string {
    if (!$debut) return 'Date à confirmer';
    $mois = ['','jan.','fév.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
    $d1   = new DateTime($debut);
    $s    = intval($d1->format('j')) . ' ' . $mois[(int)$d1->format('n')] . ' ' . $d1->format('Y');
    if ($fin && $fin !== $debut) {
        $d2 = new DateTime($fin);
        $s .= ' – ' . intval($d2->format('j')) . ' ' . $mois[(int)$d2->format('n')] . ' ' . $d2->format('Y');
    }
    return $s;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

try {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM evenements ORDER BY termine ASC, ordre ASC, id ASC")->fetchAll();
    foreach ($rows as $ev) {
        if ($ev['termine']) $termines[] = $ev;
        else                $aVenir[]   = $ev;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évènements - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/evenements/evenements.css?v=20260624" rel="stylesheet">
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
                <h1>Évènements</h1>
                <p>Tous les évènements organisés par le VBO.<br>Tournois, stages, loto, paëlla…</p>
            </div>
        </div>
    </div>

    <main class="evenements-main">

        <!-- ══ ÉVÉNEMENTS À VENIR ══════════════════════════════════════ -->
        <section class="ev-section">
            <div class="ev-section-header">
                <h2>À venir</h2>
            </div>

            <?php if (empty($aVenir)): ?>
            <p class="ev-empty">Aucun événement prévu pour le moment.</p>
            <?php else: ?>
            <div class="ev-grid" id="ev-avenir-grid">
                <?php foreach ($aVenir as $ev): ?>
                <article class="ev-card" data-id="<?= $ev['id'] ?>">
                    <?php if (!empty($ev['image_url'])): ?>
                    <img class="ev-img" src="<?= h($ev['image_url']) ?>" alt="<?= h($ev['titre']) ?>">
                    <?php endif; ?>
                    <div class="ev-date"><?= h(formatDate($ev['date_debut'], $ev['date_fin'])) ?></div>
                    <h3 class="ev-title"><?= h($ev['titre']) ?></h3>
                    <?php if ($ev['description']): ?>
                    <p class="ev-desc"><?= nl2br(h($ev['description'])) ?></p>
                    <?php endif; ?>
                    <?php if ($ev['lieu']): ?>
                    <p class="ev-lieu">📍 <?= h($ev['lieu']) ?></p>
                    <?php endif; ?>
                    <?php if ($ev['lien_url']): ?>
                    <a href="<?= h($ev['lien_url']) ?>" class="ev-btn">
                        <?= h($ev['lien_label'] ?: 'En savoir plus') ?> →
                    </a>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- ══ ÉVÉNEMENTS PASSÉS ════════════════════════════════════════ -->
        <?php if (!empty($termines)): ?>
        <section class="ev-section">
            <details class="ev-past-details">
                <summary class="ev-past-summary">
                    <span class="ev-past-label">Événements passés</span>
                    <span class="ev-past-count"><?= count($termines) ?></span>
                </summary>
                <div class="ev-grid ev-grid-past">
                    <?php foreach ($termines as $ev): ?>
                    <article class="ev-card ev-card-past" data-id="<?= $ev['id'] ?>">
                        <?php if (!empty($ev['image_url'])): ?>
                        <img class="ev-img" src="<?= h($ev['image_url']) ?>" alt="<?= h($ev['titre']) ?>">
                        <?php endif; ?>
                        <span class="ev-past-badge">Terminé</span>
                        <div class="ev-date"><?= h(formatDate($ev['date_debut'], $ev['date_fin'])) ?></div>
                        <h3 class="ev-title"><?= h($ev['titre']) ?></h3>
                        <?php if ($ev['description']): ?>
                        <p class="ev-desc"><?= nl2br(h($ev['description'])) ?></p>
                        <?php endif; ?>
                        <?php if ($ev['lieu']): ?>
                        <p class="ev-lieu">📍 <?= h($ev['lieu']) ?></p>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
            </details>
        </section>
        <?php endif; ?>

    </main>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
