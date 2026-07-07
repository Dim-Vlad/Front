<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_any_role(['admin', 'moderateur'])) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$pdo     = get_pdo();
$parPage = 30;

$pageLabels = [
    'tournois'             => 'Tournois',
    'parametres'           => 'Paramètres',
    'quiz_question'        => 'Quiz',
    'boutique_articles'    => 'Boutique',
    'entraineur_documents' => 'Espace Ressources',
    'entraineur_sections'  => 'Espace Ressources',
    'pronostics'           => 'Pronostics',
    'utilisateur'          => 'Utilisateurs',
    'drive_dossier'        => 'Drive',
    'saison_galerie'       => 'Galerie',
    'photos_galerie'       => 'Galerie',
    'partenaire'           => 'Partenaires',
    'equipe'               => 'Équipes',
    'entrainement'         => 'Entraînements',
    'entrainements_config' => 'Entraînements',
    'licence_config'       => 'Licences',
    'licence_document'     => 'Licences',
    'licence_lien'         => 'Licences',
    'staff'                => 'Staff',
    'staff_documents'      => 'Staff',
    'documents'            => 'Staff',
    'evenements'           => 'Événements',
    'inscription'          => 'Inscriptions',
    'logo_club'            => 'Logo du club',
    'dossier'              => 'Partenariat',
];

// Construit label → [entites] à partir des valeurs réellement présentes en BDD
$entiteValues = $pdo->query(
    "SELECT DISTINCT entite FROM journal_activites WHERE entite IS NOT NULL AND entite != '' ORDER BY entite"
)->fetchAll(PDO::FETCH_COLUMN);

$labelToEntites = [];
foreach ($entiteValues as $val) {
    $lbl = $pageLabels[$val] ?? ucfirst(str_replace('_', ' ', $val));
    $labelToEntites[$lbl][] = $val;
}
ksort($labelToEntites);

$filterLabel   = trim($_GET['label'] ?? '');
$filterEntites = ($filterLabel !== '' && isset($labelToEntites[$filterLabel]))
    ? $labelToEntites[$filterLabel]
    : [];
$filterParam   = $filterLabel !== '' ? '&label=' . urlencode($filterLabel) : '';

$page = max(1, (int)($_GET['page'] ?? 1));

if (!empty($filterEntites)) {
    $ph        = implode(',', array_fill(0, count($filterEntites), '?'));
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM journal_activites WHERE entite IN ($ph)");
    $stmtCount->execute($filterEntites);
    $nbTotal   = (int)$stmtCount->fetchColumn();
} else {
    $nbTotal = (int)$pdo->query('SELECT COUNT(*) FROM journal_activites')->fetchColumn();
}

$nbPages = max(1, (int)ceil($nbTotal / $parPage));
$page    = min($page, $nbPages);
$offset  = ($page - 1) * $parPage;

if (!empty($filterEntites)) {
    $ph   = implode(',', array_fill(0, count($filterEntites), '?'));
    $stmt = $pdo->prepare("SELECT * FROM journal_activites WHERE entite IN ($ph) ORDER BY created_at DESC LIMIT $parPage OFFSET $offset");
    $stmt->execute($filterEntites);
} else {
    $stmt = $pdo->prepare("SELECT * FROM journal_activites ORDER BY created_at DESC LIMIT $parPage OFFSET $offset");
    $stmt->execute();
}
$entrees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal des activités - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link href="/css/journal.css?v=20260707" rel="stylesheet">
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

        <div class="journal-toolbar">
            <p class="journal-total-line"><?= $nbTotal ?> entrée<?= $nbTotal > 1 ? 's' : '' ?><?= $filterLabel !== '' ? ' filtrées' : ' au total' ?></p>
        </div>

        <?php if (!empty($labelToEntites)): ?>
        <div class="journal-chips">
            <a href="?" class="journal-chip<?= $filterLabel === '' ? ' active' : '' ?>">Toutes</a>
            <?php foreach ($labelToEntites as $lbl => $vals): ?>
            <a href="?label=<?= urlencode($lbl) ?>" class="journal-chip<?= $filterLabel === $lbl ? ' active' : '' ?>">
                <?= htmlspecialchars($lbl) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($entrees)): ?>
        <p class="journal-empty">Aucune activité enregistrée pour le moment.</p>
        <?php else: ?>
        <ul class="journal-list">
            <?php foreach ($entrees as $e):
                $dt      = new DateTime($e['created_at']);
                $dateStr = $dt->format('d/m/Y à H\hi');

                $actionLow = strtolower($e['action']);
                if (str_contains($actionLow, 'ajout') || str_contains($actionLow, 'création') || str_contains($actionLow, 'creation') || in_array($e['action'], ['CREATE', 'UPLOAD', 'APPROVE'])) {
                    $badgeClass = 'badge-ajout';
                    $icon       = '＋';
                } elseif (str_contains($actionLow, 'suppression') || in_array($e['action'], ['DELETE', 'REJECT'])) {
                    $badgeClass = 'badge-suppression';
                    $icon       = '🗑';
                } else {
                    $badgeClass = 'badge-modif';
                    $icon       = '✏️';
                }

                $actionMap  = ['CREATE' => 'Création', 'DELETE' => 'Suppression', 'UPLOAD' => 'Upload', 'APPROVE' => 'Approbation', 'REJECT' => 'Rejet'];
                $actionText = $actionMap[$e['action']] ?? ucfirst($e['action']);

                $entite    = $e['entite'] ?? '';
                $pageLabel = $pageLabels[$entite] ?? ucfirst(str_replace('_', ' ', $entite));
            ?>
            <li class="journal-entry">
                <span class="journal-icon <?= $badgeClass ?>"><?= $icon ?></span>
                <div class="journal-body">
                    <div class="journal-meta">
                        <strong><?= htmlspecialchars($e['username']) ?></strong>
                        <span class="journal-date"><?= $dateStr ?></span>
                        <?php if ($pageLabel !== ''): ?>
                        <span class="journal-page"><?= htmlspecialchars($pageLabel) ?></span>
                        <?php endif; ?>
                        <span class="journal-action-text"><?= htmlspecialchars($actionText) ?></span>
                    </div>
                    <p class="journal-details"><?= htmlspecialchars($e['details']) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($nbPages > 1): ?>
        <div class="journal-pagination">
            <?php for ($p = 1; $p <= $nbPages; $p++): ?>
            <a href="?page=<?= $p . $filterParam ?>" class="page-btn<?= $p === $page ? ' current' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <a href="<?= has_role('admin') ? '/pages/admin/index.php' : '/pages/auth/tableau-de-bord.php' ?>" class="back-btn">← Retour</a>
    </div>

    <div id="footer"></div>
    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
