<?php
require_once __DIR__ . '/../../php/auth.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /pages/evenements/evenements.php'); exit; }

$t = null;
try {
    $stmt = get_pdo()->prepare("SELECT * FROM tournois WHERE id = ?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();
} catch (Exception $e) {}

if (!$t) { header('Location: /pages/evenements/evenements.php'); exit; }

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

function extractSrc(string $val): string {
    if (stripos($val, '<iframe') !== false) {
        preg_match('/\bsrc=["\']([^"\']+)["\']/', $val, $m);
        return $m[1] ?? '';
    }
    return $val;
}

function formatTel(string $tel): string {
    $digits = preg_replace('/\D/', '', $tel);
    return trim(chunk_split($digits, 2, ' '));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['titre']) ?> - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/leClub/minibus.css?v=20260623" rel="stylesheet">
    <link href="/css/evenements/tournoi.css?v=20260705" rel="stylesheet">
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
                <h1><?= h($t['titre']) ?></h1>
                <?php if ($t['saison']): ?>
                <p>Saison <?= h($t['saison']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <a href="/pages/evenements/evenements.php" class="tournoi-back">← Retour aux événements</a>

    <main class="tournoi-main">

        <?php if ($t['description']): ?>
        <section class="tournoi-section tournoi-section--first">
            <p class="tournoi-desc"><?= nl2br(h($t['description'])) ?></p>
        </section>
        <?php endif; ?>

        <!-- ── Inscription ─────────────────────────────────────── -->
        <?php
        $inscUrl   = $t['inscription_url']   ?? $t['inscription_contact'] ?? '';
        $inscTel   = $t['inscription_tel']   ?? '';
        $inscEmail = $t['inscription_email'] ?? '';
        $hasAnyInsc = $inscUrl || $inscTel || $inscEmail;
        ?>
        <section class="tournoi-section">
            <h2 class="tournoi-section-title">Inscription</h2>
            <?php if (!$hasAnyInsc): ?>
            <p class="tournoi-empty">Les inscriptions seront bientôt disponibles.</p>
            <?php else: ?>
                <?php if ($inscUrl): ?>
                <div class="sheet-card tournoi-inscription-card">
                    <iframe
                        id="haWidgetInscription"
                        src="<?= h(extractSrc($inscUrl)) ?>"
                        title="Inscription"
                        scrolling="auto"
                        allowtransparency="true">
                    </iframe>
                </div>
                <?php endif; ?>
                <?php if ($inscTel || $inscEmail): ?>
                <div class="tournoi-contact-card<?= $inscUrl ? ' tournoi-contact-card--below' : '' ?>">
                    <?php if ($inscUrl): ?><p class="tournoi-contact-label">Vous pouvez également contacter :</p><?php else: ?><p class="tournoi-contact-label">Pour s'inscrire, contactez-nous :</p><?php endif; ?>
                    <?php if ($inscTel): ?>
                    <a class="tournoi-contact-value" href="tel:<?= h(preg_replace('/\D/', '', $inscTel)) ?>">📞 <?= h(formatTel($inscTel)) ?></a>
                    <?php endif; ?>
                    <?php if ($inscEmail): ?>
                    <a class="tournoi-contact-value" href="mailto:<?= h($inscEmail) ?>">✉️ <?= h($inscEmail) ?></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- ── Tableau des scores ──────────────────────────────── -->
        <?php
        $typesAvecScores = ['tournoi', 'match', 'stage', 'autre'];
        $showScores = !empty($t['sheet_url']) || in_array($t['type'] ?? 'tournoi', $typesAvecScores);
        ?>
        <?php if ($showScores): ?>
        <section class="tournoi-section">
            <h2 class="tournoi-section-title">Tableau des scores</h2>
            <?php if (!empty($t['sheet_url'])): ?>
            <div class="sheet-card">
                <iframe
                    src="<?= h($t['sheet_url']) ?>"
                    title="Tableau des scores"
                    loading="lazy"
                    allowfullscreen>
                </iframe>
            </div>
            <?php else: ?>
            <p class="tournoi-empty">Le tableau des scores n'est pas encore disponible.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

    </main>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script>

        // Auto-resize HelloAsso widget via postMessage
        const HA_ORIGINS = ['https://www.helloasso.com', 'https://helloasso.com'];
        window.addEventListener('message', function(e) {
            if (!HA_ORIGINS.includes(e.origin)) return;
            const data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
            if (data?.action === 'resize' && data?.params?.height) {
                const frame = document.getElementById('haWidgetInscription');
                if (frame) frame.style.height = data.params.height + 'px';
            }
        });
    </script>
</body>
</html>
