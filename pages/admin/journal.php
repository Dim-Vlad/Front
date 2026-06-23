<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_any_role(['admin', 'moderateur'])) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$pdo     = get_pdo();
$page    = max(1, (int)($_GET['page'] ?? 1));
$parPage = 30;
$offset  = ($page - 1) * $parPage;

$nbTotal = (int)$pdo->query('SELECT COUNT(*) FROM journal_activites')->fetchColumn();
$nbPages = max(1, (int)ceil($nbTotal / $parPage));

$entrees = $pdo->prepare('SELECT * FROM journal_activites ORDER BY created_at DESC LIMIT ' . $parPage . ' OFFSET ' . $offset);
$entrees->execute();
$entrees = $entrees->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal des activités - VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link href="/css/journal.css?v=20260623" rel="stylesheet">
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
                <h1>Journal des activités</h1>
                <p>Historique des modifications effectuées par les modérateurs et administrateurs.</p>
            </div>
        </div>
    </div>

    <a href="<?= has_role('admin') ? '/pages/admin/index.php' : '/pages/auth/tableau-de-bord.php' ?>" class="back-btn">← Retour</a>

    <div class="journal-container">

        <p class="journal-total-line"><?= $nbTotal ?> entrée<?= $nbTotal > 1 ? 's' : '' ?> au total</p>

        <?php if (empty($entrees)): ?>
        <p class="journal-empty">Aucune activité enregistrée pour le moment.</p>
        <?php else: ?>
        <ul class="journal-list">
            <?php foreach ($entrees as $e):
                $dt      = new DateTime($e['created_at']);
                $dateStr = $dt->format('d/m/Y à H\hi');
                $badgeClass = match($e['action']) {
                    'ajout'       => 'badge-ajout',
                    'suppression' => 'badge-suppression',
                    default       => 'badge-modif',
                };
                $icon = match($e['action']) {
                    'ajout'       => '＋',
                    'suppression' => '🗑',
                    default       => '✏️',
                };
            ?>
            <li class="journal-entry">
                <span class="journal-icon <?= $badgeClass ?>"><?= $icon ?></span>
                <div class="journal-body">
                    <div class="journal-meta">
                        <strong><?= htmlspecialchars($e['username']) ?></strong>
                        <span class="journal-date"><?= $dateStr ?></span>
                    </div>
                    <p class="journal-details"><?= htmlspecialchars($e['details']) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($nbPages > 1): ?>
        <div class="journal-pagination">
            <?php for ($p = 1; $p <= $nbPages; $p++): ?>
            <a href="?page=<?= $p ?>" class="page-btn<?= $p === $page ? ' current' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <a href="<?= has_role('admin') ? '/pages/admin/index.php' : '/pages/auth/tableau-de-bord.php' ?>" class="back-btn">← Retour</a>
    </div>

    <div id="footer"></div>
    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.html', 'footer');
    </script>
</body>
</html>
