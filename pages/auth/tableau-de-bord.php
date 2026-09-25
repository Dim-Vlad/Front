<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();

$user = current_user();
$pdo  = get_pdo();

$nbAttente = 0;
if (has_role('admin')) {
    $nbAttente = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE actif = 0')->fetchColumn();
}

$hasSections        = has_any_role(['admin', 'bureau', 'moderateur', 'entraineur', 'arbitre']);
$hasEntraineur      = $hasSections;
$hasCommissions     = has_any_role(['bureau', 'admin', 'moderateur', 'arbitre']);

$canEdit = has_any_role(['admin', 'moderateur']);

$tiles = ['jeux' => [], 'entraineur' => [], 'commissions' => []];
foreach ($pdo->query('SELECT * FROM dashboard_tiles ORDER BY section, ordre, id')->fetchAll() as $t) {
    $tiles[$t['section']][] = $t;
}

$sectionLabels = ['jeux' => '🎮 Jeux', 'entraineur' => '🏋️ Espace Entraîneur', 'commissions' => '📁 Commissions'];

function render_dashboard_tile(array $t, bool $canEdit): void {
    $isExternal = str_starts_with($t['url'], 'http');
    ?>
                <div class="dashboard-card dashboard-card--tile"
                    data-id="<?= $t['id'] ?>"
                    data-section="<?= htmlspecialchars($t['section'], ENT_QUOTES) ?>"
                    data-icone="<?= htmlspecialchars($t['icone'], ENT_QUOTES) ?>"
                    data-titre="<?= htmlspecialchars($t['titre'], ENT_QUOTES) ?>"
                    data-description="<?= htmlspecialchars($t['description'], ENT_QUOTES) ?>"
                    data-url="<?= htmlspecialchars($t['url'], ENT_QUOTES) ?>">
                    <a class="tile-link" href="<?= htmlspecialchars($t['url']) ?>" target="<?= $isExternal ? '_blank' : '_self' ?>" rel="noopener">
                        <div class="card-icon"><?= htmlspecialchars($t['icone']) ?></div>
                        <h2><?= htmlspecialchars($t['titre']) ?></h2>
                        <p><?= htmlspecialchars($t['description']) ?></p>
                    </a>
                    <?php if ($canEdit): ?>
                    <button type="button" class="tile-edit-btn" onclick="openTileModal(this.closest('.dashboard-card'))" title="Modifier">✏️</button>
                    <?php endif; ?>
                </div>
    <?php
}

$roleLabels = ['admin'=>'Admin','bureau'=>'Bureau','moderateur'=>'Modérateur','entraineur'=>'Entraîneur','arbitre'=>'Arbitre','adherent'=>'Adhérent'];
$rolePriority = ['admin','moderateur','bureau','entraineur','arbitre','adherent'];
$titleRole = 'Membre';
foreach ($rolePriority as $r) {
    if (in_array($r, $user['roles'] ?? [])) {
        $titleRole = $roleLabels[$r];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - <?= $titleRole ?></title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260925" rel="stylesheet">
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
                <h1>Tableau de bord</h1>
                <?php
                    $fullname = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
                    $display  = $fullname !== '' ? $fullname : $user['username'];
                ?>
                <p>Bonjour, <strong><?= htmlspecialchars($display) ?></strong> 👋</p>
                <?php if (!empty($user['roles'])):
                    $roleLabels = ['entraineur'=>'Entraineur','admin'=>'Admin','arbitre'=>'Arbitre','bureau'=>'Bureau','moderateur'=>'Modérateur','adherent'=>'Adhérent'];
                ?>
                <div class="user-roles">
                    <?php foreach ($user['roles'] as $r): ?>
                    <span class="badge badge--<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($roleLabels[$r] ?? ucfirst($r)) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-container">

        <?php if (has_role('admin') || has_role('moderateur')): ?>
        <!-- Tuiles privilégiées (pleine largeur, sans titre de section) -->
        <div class="dashboard-cards">
            <?php if (has_role('admin')): ?>
            <div class="dashboard-card card-admin card-admin--featured">
                <?php if ($nbAttente > 0): ?>
                <a href="/pages/admin/comptes-attente.php" class="card-badge-link" title="Voir les comptes en attente">
                    <span class="card-badge-number"><?= $nbAttente ?></span>
                    <span class="card-badge-label">en attente</span>
                </a>
                <?php endif; ?>
                <a href="/pages/admin/index.php" class="card-main-link">
                    <div class="card-icon">⚙️</div>
                    <div>
                        <h2>Administration</h2>
                        <p>Gestion du site et des utilisateurs.</p>
                    </div>
                </a>
            </div>
            <?php endif; ?>

            <?php if (has_role('moderateur') && !has_role('admin')): ?>
            <div class="dashboard-card card-admin card-admin--featured">
                <a href="/pages/moderateur/index.php" class="card-main-link">
                    <div class="card-icon">🛡️</div>
                    <div>
                        <h2>Modération</h2>
                        <p>Gestion des pronostics et du quiz.</p>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Section Jeux -->
        <?php if ($hasSections): ?>
        <div class="dashboard-section">
            <div class="dashboard-section-header">
                <h2 class="dashboard-section-title">🎮 Jeux</h2>
                <?php if ($canEdit): ?>
                <button type="button" class="btn-add-tile" onclick="openTileModal(null, 'jeux')">+ Ajouter une tuile</button>
                <?php endif; ?>
            </div>
            <div class="dashboard-cards">
        <?php else: ?>
        <div class="dashboard-cards">
        <?php endif; ?>

                <a href="/pages/pronostics/classement.php" class="dashboard-card">
                    <div class="card-icon">🏆</div>
                    <h2>Classement</h2>
                    <p>Voir le classement général des pronostics et du quiz.</p>
                </a>

                <a href="/pages/pronostics/index.php" class="dashboard-card">
                    <div class="card-icon">🔮</div>
                    <h2>Pronostics</h2>
                    <p>Pronostiques les résultats des matchs du VBO et grimpez au classement.</p>
                </a>

                <a href="/pages/quiz/index.php" class="dashboard-card">
                    <div class="card-icon">🧠</div>
                    <h2>Quiz VBO</h2>
                    <p>Testez vos connaissances sur le volley-ball et le club.</p>
                </a>

                <?php foreach ($tiles['jeux'] as $t) render_dashboard_tile($t, $canEdit); ?>

        <?php if ($hasSections): ?>
            </div>
        </div>
        <?php else: ?>
        </div>
        <?php endif; ?>

        <!-- Section Espace Entraîneur -->
        <?php if ($hasEntraineur): ?>
        <div class="dashboard-section">
            <div class="dashboard-section-header">
                <h2 class="dashboard-section-title">🏋️ Espace Entraîneur</h2>
                <?php if ($canEdit): ?>
                <button type="button" class="btn-add-tile" onclick="openTileModal(null, 'entraineur')">+ Ajouter une tuile</button>
                <?php endif; ?>
            </div>
            <div class="dashboard-cards">

                <?php if (!has_any_role(['arbitre', 'adherent'])): ?>
                <a href="/pages/leClub/espace-entraineur/minibus.php" class="dashboard-card">
                    <div class="card-icon">🚌</div>
                    <h2>Réservations Minibus</h2>
                    <p>Consultez les réservations des minibus de la mairie et celui du club.</p>
                </a>

                <a href="/pages/leClub/espace-entraineur/presences.php" class="dashboard-card">
                    <div class="card-icon">✅</div>
                    <h2>Pointage Présences</h2>
                    <p>Suivez le pointage des présences aux entraînements et aux matchs.</p>
                </a>
                <?php endif; ?>

                <a href="/pages/leClub/espace-entraineur/espace-entraineur.php" class="dashboard-card">
                    <div class="card-icon">📥</div>
                    <h2>Ressources</h2>
                    <p>Documents téléchargeables et ressources FFVB.</p>
                </a>

                <a href="/pages/leClub/espace-entraineur/formations.php" class="dashboard-card">
                    <div class="card-icon">🎓</div>
                    <h2>Formations</h2>
                    <p>Ressources de formation par public : entraîneurs, arbitres, marqueurs, joueurs et détection.</p>
                </a>

                <?php foreach ($tiles['entraineur'] as $t) render_dashboard_tile($t, $canEdit); ?>

            </div>
        </div>
        <?php endif; ?>

        <!-- Section Commissions -->
        <?php if ($hasCommissions): ?>
        <div class="dashboard-section">
            <div class="dashboard-section-header">
                <h2 class="dashboard-section-title">📁 Commissions</h2>
                <?php if ($canEdit): ?>
                <button type="button" class="btn-add-tile" onclick="openTileModal(null, 'commissions')">+ Ajouter une tuile</button>
                <?php endif; ?>
            </div>
            <div class="dashboard-cards">

                <?php if (has_any_role(['bureau', 'admin', 'moderateur'])): ?>
                <a href="/pages/bureau/drive.php" class="dashboard-card">
                    <div class="card-icon">🗂️</div>
                    <h2>Dossiers Commissions</h2>
                    <p>Accédez aux documents partagés sur Google Drive.</p>
                </a>
                <?php endif; ?>

                <?php if (has_any_role(['arbitre', 'admin', 'bureau', 'moderateur'])): ?>
                <a href="/pages/arbitres/arbitres.php" class="dashboard-card">
                    <div class="card-icon">📣</div>
                    <h2>Arbitres &amp; Marqueurs</h2>
                    <p>Planning et feuilles de match pour les arbitres et marqueurs.</p>
                </a>
                <?php endif; ?>

                <?php foreach ($tiles['commissions'] as $t) render_dashboard_tile($t, $canEdit); ?>

            </div>
        </div>
        <?php endif; ?>

        <a href="/php/logout.php" class="btn-logout"
            onclick="return confirm('Voulez-vous vraiment vous déconnecter ?')">Se déconnecter</a>

    </div>

    <?php if ($canEdit): ?>
    <!-- ── Modale tuile ── -->
    <div id="tileModal" class="tile-modal">
        <div class="tile-modal-content">
            <div class="tile-modal-header">
                <h3 id="tileModalTitle">Ajouter une tuile</h3>
                <span class="close" onclick="closeTileModal()">&times;</span>
            </div>
            <div class="tile-modal-body">
                <form id="tile-form">
                    <input type="hidden" name="id" id="tile-id" value="0">
                    <div class="modal-form-group">
                        <label for="tile-section">Section</label>
                        <select name="section" id="tile-section">
                            <?php foreach ($sectionLabels as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="tile-icone">Pictogramme</label>
                        <input type="text" name="icone" id="tile-icone" maxlength="10" placeholder="📌" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="tile-titre">Titre</label>
                        <input type="text" name="titre" id="tile-titre" maxlength="100" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="tile-description">Description</label>
                        <textarea name="description" id="tile-description" rows="2" maxlength="255"></textarea>
                    </div>
                    <div class="modal-form-group">
                        <label for="tile-url">Lien</label>
                        <input type="text" name="url" id="tile-url" placeholder="/pages/... ou https://..." required>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeTileModal()">Annuler</button>
                    </div>
                    <button type="button" class="btn-delete-tile" id="tile-delete-btn" onclick="deleteTile()" style="display:none">🗑 Supprimer cette tuile</button>
                    <p class="modal-status" id="tile-status"></p>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <?php if ($canEdit): ?>
    <script src="/js/dashboard-tiles.js?v=20260925"></script>
    <?php endif; ?>
</body>
</html>
