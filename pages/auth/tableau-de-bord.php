<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();

$user = current_user();
$pdo  = get_pdo();

$nbAttente = 0;
if (has_role('admin')) {
    $nbAttente = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE actif = 0')->fetchColumn();
}

$canEdit = has_any_role(['admin', 'moderateur']);

$tiles = ['jeux' => [], 'entraineur' => [], 'commissions' => [], 'general' => []];
foreach ($pdo->query('SELECT * FROM dashboard_tiles ORDER BY section, ordre, id')->fetchAll() as $t) {
    $tiles[$t['section']][] = $t;
}

// Hiérarchie des rôles : cocher un rôle donne aussi accès à tous les rôles
// au-dessus de lui (droits plus élevés). Entraîneur et arbitre sont au même niveau.
const ROLE_HIERARCHY = [
    1 => ['admin'],
    2 => ['moderateur'],
    3 => ['bureau'],
    4 => ['entraineur', 'arbitre'],
    5 => ['adherent'],
];

// Étend une liste de rôles cochés avec tous les rôles de rang supérieur.
function roles_with_hierarchy(array $checkedRoles): array {
    $tierOf = [];
    foreach (ROLE_HIERARCHY as $tier => $rolesInTier) {
        foreach ($rolesInTier as $r) $tierOf[$r] = $tier;
    }
    $allowed = [];
    foreach ($checkedRoles as $r) {
        $tier = $tierOf[$r] ?? null;
        if ($tier === null) continue;
        foreach (ROLE_HIERARCHY as $t => $rolesInTier) {
            if ($t <= $tier) $allowed = array_merge($allowed, $rolesInTier);
        }
    }
    return array_values(array_unique($allowed));
}

// Une section s'affiche si l'utilisateur peut y voir au moins une tuile
// (ou s'il peut en ajouter une) — chaque tuile porte sa propre visibilité
// par rôle, il n'y a plus de verrou au niveau de la section elle-même.
function section_has_visible_tile(array $tiles, bool $canEdit): bool {
    if ($canEdit) return true;
    foreach ($tiles as $t) {
        $roles = json_decode($t['roles'] ?? '[]', true) ?: [];
        if (empty($roles) || has_any_role(roles_with_hierarchy($roles))) return true;
    }
    return false;
}

$sectionLabels = ['jeux' => '🎮 Jeux', 'entraineur' => '🏋️ Espace Entraîneur', 'commissions' => '📁 Commissions', 'general' => '👥 Pour tous les membres'];

// Nom distinct de $roleLabels (redéfini plus bas pour les badges de rôle de l'en-tête)
// pour que les tuiles gardent toujours la bonne orthographe des libellés.
$tileRoleLabels = ['admin'=>'Admin','moderateur'=>'Modérateur','bureau'=>'Bureau','entraineur'=>'Entraîneur','arbitre'=>'Arbitre','adherent'=>'Adhérent'];

function render_dashboard_tile(array $t, bool $canEdit, array $roleLabels): void {
    $isExternal  = str_starts_with($t['url'], 'http');
    $tileRoles   = json_decode($t['roles'] ?? '[]', true) ?: [];
    $isRestricted = !empty($tileRoles);

    // Les cases cochées filtrent qui voit la tuile ; les rôles de rang supérieur
    // (cf. ROLE_HIERARCHY) la voient aussi automatiquement. Admin/modérateur la
    // voient toujours (pour pouvoir la gérer), même si leur rôle n'est pas coché.
    if ($isRestricted && !$canEdit && !has_any_role(roles_with_hierarchy($tileRoles))) return;
    ?>
                <div class="dashboard-card dashboard-card--tile"
                    data-id="<?= $t['id'] ?>"
                    data-section="<?= htmlspecialchars($t['section'], ENT_QUOTES) ?>"
                    data-icone="<?= htmlspecialchars($t['icone'], ENT_QUOTES) ?>"
                    data-titre="<?= htmlspecialchars($t['titre'], ENT_QUOTES) ?>"
                    data-description="<?= htmlspecialchars($t['description'], ENT_QUOTES) ?>"
                    data-url="<?= htmlspecialchars($t['url'], ENT_QUOTES) ?>"
                    data-roles="<?= htmlspecialchars(json_encode($tileRoles), ENT_QUOTES) ?>">
                    <a class="tile-link" href="<?= htmlspecialchars($t['url']) ?>" target="<?= $isExternal ? '_blank' : '_self' ?>" rel="noopener">
                        <div class="card-icon"><?= htmlspecialchars($t['icone']) ?></div>
                        <h2><?= htmlspecialchars($t['titre']) ?></h2>
                        <p><?= htmlspecialchars($t['description']) ?></p>
                        <?php if ($canEdit && $isRestricted): ?>
                        <span class="tile-roles-badge">🔒 <?= htmlspecialchars(implode(', ', array_map(fn($r) => $roleLabels[$r] ?? $r, $tileRoles))) ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($canEdit): ?>
                    <button type="button" class="tile-edit-btn" onclick="openTileModal(this.closest('.dashboard-card'))" title="Modifier">✏️</button>
                    <?php endif; ?>
                </div>
    <?php
}
$rolePriority = ['admin','moderateur','bureau','entraineur','arbitre','adherent'];
$titleRole = 'Membre';
foreach ($rolePriority as $r) {
    if (in_array($r, $user['roles'] ?? [])) {
        $titleRole = $tileRoleLabels[$r];
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
    <link href="/css/tableau-de-bord.css?v=20261001" rel="stylesheet">
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
        <?php if (section_has_visible_tile($tiles['jeux'], $canEdit)): ?>
        <div class="dashboard-section">
            <div class="dashboard-section-header">
                <h2 class="dashboard-section-title">🎮 Jeux</h2>
                <?php if ($canEdit): ?>
                <button type="button" class="btn-add-tile" onclick="openTileModal(null, 'jeux')">+ Ajouter une tuile</button>
                <?php endif; ?>
            </div>
            <div class="dashboard-cards">

                <?php foreach ($tiles['jeux'] as $t) render_dashboard_tile($t, $canEdit, $tileRoleLabels); ?>

            </div>
        </div>
        <?php endif; ?>

        <!-- Section générique, visible à tous les comptes connectés -->
        <?php if (section_has_visible_tile($tiles['general'], $canEdit)): ?>
        <div class="dashboard-section">
            <div class="dashboard-section-header">
                <h2 class="dashboard-section-title">👥 Pour tous les membres</h2>
                <?php if ($canEdit): ?>
                <button type="button" class="btn-add-tile" onclick="openTileModal(null, 'general')">+ Ajouter une tuile</button>
                <?php endif; ?>
            </div>
            <div class="dashboard-cards">

                <?php foreach ($tiles['general'] as $t) render_dashboard_tile($t, $canEdit, $tileRoleLabels); ?>

            </div>
        </div>
        <?php endif; ?>

        <!-- Section Espace Entraîneur -->
        <?php if (section_has_visible_tile($tiles['entraineur'], $canEdit)): ?>
        <div class="dashboard-section">
            <div class="dashboard-section-header">
                <h2 class="dashboard-section-title">🏋️ Espace Entraîneur</h2>
                <?php if ($canEdit): ?>
                <button type="button" class="btn-add-tile" onclick="openTileModal(null, 'entraineur')">+ Ajouter une tuile</button>
                <?php endif; ?>
            </div>
            <div class="dashboard-cards">

                <?php foreach ($tiles['entraineur'] as $t) render_dashboard_tile($t, $canEdit, $tileRoleLabels); ?>

            </div>
        </div>
        <?php endif; ?>

        <!-- Section Commissions -->
        <?php if (section_has_visible_tile($tiles['commissions'], $canEdit)): ?>
        <div class="dashboard-section">
            <div class="dashboard-section-header">
                <h2 class="dashboard-section-title">📁 Commissions</h2>
                <?php if ($canEdit): ?>
                <button type="button" class="btn-add-tile" onclick="openTileModal(null, 'commissions')">+ Ajouter une tuile</button>
                <?php endif; ?>
            </div>
            <div class="dashboard-cards">

                <?php foreach ($tiles['commissions'] as $t) render_dashboard_tile($t, $canEdit, $tileRoleLabels); ?>

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
                    <div class="modal-form-group">
                        <label>Visible par</label>
                        <p class="tile-roles-hint">Aucune case cochée = visible par tous les comptes connectés. Cocher un rôle le rend aussi visible aux rôles au-dessus dans la hiérarchie (Admin → Modérateur → Bureau → Entraîneur/Arbitre → Adhérent).</p>
                        <div class="tile-roles-group" id="tile-roles-group">
                            <?php foreach ($tileRoleLabels as $key => $label): ?>
                            <label class="tile-role-cb"><input type="checkbox" name="roles[]" value="<?= $key ?>"> <?= htmlspecialchars($label) ?></label>
                            <?php endforeach; ?>
                        </div>
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
    <script src="/js/dashboard-tiles.js?v=20261001"></script>
    <?php endif; ?>
</body>
</html>
