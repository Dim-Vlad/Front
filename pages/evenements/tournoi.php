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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['titre']) ?> - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
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
        <section class="tournoi-section">
            <h2 class="tournoi-section-title">Inscription</h2>
            <?php
            $inscSrc = $t['inscription_url'] ? extractSrc($t['inscription_url']) : '';
            ?>
            <?php if ($inscSrc): ?>
            <div class="sheet-card tournoi-inscription-card">
                <iframe
                    id="haWidgetInscription"
                    src="<?= h($inscSrc) ?>"
                    title="Inscription"
                    scrolling="auto"
                    allowtransparency="true">
                </iframe>
            </div>
            <?php else: ?>
            <p class="tournoi-empty">Les inscriptions seront bientôt disponibles.</p>
            <?php endif; ?>
        </section>

        <!-- ── Tableau des scores ──────────────────────────────── -->
        <section class="tournoi-section">
            <h2 class="tournoi-section-title">Tableau des scores</h2>
            <?php if ($t['sheet_url']): ?>
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

    </main>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.php', 'footer');

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
