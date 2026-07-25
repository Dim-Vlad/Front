<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('admin')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$pdo     = get_pdo();
$page    = max(1, (int)($_GET['page'] ?? 1));
$parPage = 50;
$offset  = ($page - 1) * $parPage;

$nbTotal = (int)$pdo->query('SELECT COUNT(*) FROM journal_connexions')->fetchColumn();
$nbPages = max(1, (int)ceil($nbTotal / $parPage));

$entrees = $pdo->prepare('SELECT * FROM journal_connexions ORDER BY created_at DESC LIMIT ' . $parPage . ' OFFSET ' . $offset);
$entrees->execute();
$entrees = $entrees->fetchAll(PDO::FETCH_ASSOC);

$roleLabels = [
    'admin'      => 'Admin',
    'moderateur' => 'Modérateur',
    'bureau'     => 'Bureau',
    'entraineur' => 'Entraîneur',
    'arbitre'    => 'Arbitre',
    'adherent'   => 'Adhérent',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal des connexions - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link href="/css/journal.css?v=20260802" rel="stylesheet">
    <link href="/css/admin.css?v=20260623" rel="stylesheet">
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
                <h1>Journal des connexions</h1>
                <p>Historique des connexions à l'espace membres.</p>
            </div>
        </div>
    </div>

    <a href="/pages/admin/index.php" class="back-btn">← Retour</a>

    <div class="journal-container">

        <p class="journal-total-line"><?= $nbTotal ?> connexion<?= $nbTotal > 1 ? 's' : '' ?> enregistrée<?= $nbTotal > 1 ? 's' : '' ?></p>

        <?php if (empty($entrees)): ?>
        <p class="journal-empty">Aucune connexion enregistrée pour le moment.</p>
        <?php else: ?>
        <ul class="journal-list">
            <?php foreach ($entrees as $e):
                $dt      = new DateTime($e['created_at']);
                $dateStr = $dt->format('d/m/Y à H\hi');
                $succes  = (int)($e['succes'] ?? 1);

                $isResetRequest    = $e['roles'] === 'Demande de réinitialisation mot de passe';
                $isResetCompleted  = $e['roles'] === 'Mot de passe réinitialisé';

                if ($isResetRequest) {
                    $badgeClass = 'badge-reset'; $icon = '🔐';
                } elseif ($isResetCompleted) {
                    $badgeClass = 'badge-reset'; $icon = '🔓';
                } else {
                    $badgeClass = $succes ? 'badge-connexion' : 'badge-echec';
                    $icon       = $succes ? '🔑' : '⚠️';
                }

                $roleList = $e['roles'] ? array_filter(array_map('trim', explode(',', $e['roles']))) : [];
                $showRoles = $succes && !empty($roleList) && !in_array($e['roles'], ['Compte en attente', 'Demande de réinitialisation mot de passe', 'Mot de passe réinitialisé'], true);

                $details = [];
                if ($isResetRequest) $details[] = 'Demande de réinitialisation de mot de passe';
                elseif ($isResetCompleted) $details[] = 'Mot de passe réinitialisé avec succès';
                elseif (!$succes && $e['roles'] === 'Compte en attente') $details[] = 'Compte en attente de validation';
                elseif (!$succes) $details[] = 'Identifiant ou mot de passe incorrect';
                if (!empty($e['appareil'])) $details[] = htmlspecialchars($e['appareil']);
                $details[] = 'IP : ' . htmlspecialchars($e['ip'] ?: '—');
            ?>
            <li class="journal-entry">
                <span class="journal-icon <?= $badgeClass ?>"><?= $icon ?></span>
                <div class="journal-body">
                    <div class="journal-meta">
                        <strong><?= htmlspecialchars($e['username']) ?></strong>
                        <span class="journal-date"><?= $dateStr ?></span>
                        <?php if ($showRoles):
                            foreach ($roleList as $r): ?>
                        <span class="badge badge--<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($roleLabels[$r] ?? ucfirst($r)) ?></span>
                        <?php endforeach; endif; ?>
                        <?php if (!$succes && !$isResetRequest): ?>
                        <span class="journal-echec-label">Échec</span>
                        <?php endif; ?>
                    </div>
                    <p class="journal-details"><?= implode(' · ', $details) ?></p>
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

        <a href="/pages/admin/index.php" class="back-btn">← Retour</a>
    </div>

    <div id="footer"></div>
    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
