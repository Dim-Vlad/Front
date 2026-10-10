<?php
require_once __DIR__ . '/../../php/auth.php';

$canEdit = is_logged_in() && has_any_role(['moderateur', 'admin']);
$isAdmin = is_logged_in() && has_role('admin');

try {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM partenaires ORDER BY ordre ASC, id ASC")->fetchAll();
} catch (Exception $e) {
    $rows = [];
}

$groups = ['partenaire' => [], 'institutionnel' => [], 'federation' => []];
foreach ($rows as $p) {
    if (isset($groups[$p['categorie']])) $groups[$p['categorie']][] = $p;
}

// Mise en avant réellement active : si la date de fin est dépassée, le
// partenaire repasse en affichage standard sans que la valeur enregistrée
// soit modifiée (l'admin la retrouve telle quelle pour la renouveler).
function partenaire_mis_en_avant_effectif(array $p): bool {
    if (!(int)($p['mis_en_avant'] ?? 0)) return false;
    if (!empty($p['date_fin']) && $p['date_fin'] < date('Y-m-d')) return false;
    return true;
}

$featured = array_values(array_filter($rows, 'partenaire_mis_en_avant_effectif'));
usort($featured, fn($a, $b) => ($a['ordre'] ?? 0) <=> ($b['ordre'] ?? 0));

function render_partner_card(array $p, bool $canEdit, bool $featured = false): void {
    $safeLogo   = htmlspecialchars($p['logo'], ENT_QUOTES);
    $safeNom    = htmlspecialchars($p['nom'],  ENT_QUOTES);
    $safeUrl    = htmlspecialchars($p['url'],  ENT_QUOTES);
    $safeCat    = htmlspecialchars($p['categorie'], ENT_QUOTES);
    $misEnAvant = (int)($p['mis_en_avant'] ?? 0) === 1;
    $dateFin    = $p['date_fin'] ?? '';
    $description = $p['description'] ?? '';
    $expire     = $misEnAvant && !partenaire_mis_en_avant_effectif($p);
    $cardClass  = 'partner-card' . ($featured ? ' partner-card--featured' : '');
    ?>
    <div class="<?= $cardClass ?>"
        data-id="<?= $p['id'] ?>"
        data-nom="<?= $safeNom ?>"
        data-url="<?= $safeUrl ?>"
        data-logo="<?= $safeLogo ?>"
        data-categorie="<?= $safeCat ?>"
        data-mis-en-avant="<?= $misEnAvant ? '1' : '0' ?>"
        data-date-fin="<?= htmlspecialchars($dateFin, ENT_QUOTES) ?>"
        data-description="<?= htmlspecialchars($description, ENT_QUOTES) ?>">
        <div class="partner-logo-wrap">
            <?php if ($p['logo']): ?>
            <img class="partner-logo" src="<?= $safeLogo ?>" alt="<?= $safeNom ?>">
            <?php else: ?>
            <div class="partner-logo-placeholder"><?= htmlspecialchars($p['nom'][0]) ?></div>
            <?php endif; ?>
        </div>
        <div class="partner-footer">
            <span class="partner-name"><?= htmlspecialchars($p['nom']) ?></span>
            <?php if ($featured && $description !== ''): ?>
            <p class="partner-description"><?= nl2br(htmlspecialchars($description)) ?></p>
            <?php endif; ?>
            <?php if ($p['url']): ?>
            <a class="partner-link<?= $featured ? ' partner-link--featured' : '' ?>" href="<?= $safeUrl ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()"><?= $featured ? 'Visiter le site →' : 'Visiter →' ?></a>
            <?php endif; ?>
            <?php if ($canEdit && $misEnAvant): ?>
            <span class="partner-tier-badge<?= $expire ? ' partner-tier-badge--expired' : '' ?>">
                <?= $expire ? '⚠️ Expiré (mise en avant)' : '⭐ Mise en avant' ?>
                <?php if ($dateFin && !$expire): ?> · jusqu'au <?= (new DateTime($dateFin))->format('d/m/Y') ?><?php endif; ?>
            </span>
            <?php endif; ?>
        </div>
        <?php if ($canEdit): ?>
        <button class="partner-edit-btn"
            onclick="event.stopPropagation(); openEditModal(this.closest('.partner-card'))"
            title="Modifier ce partenaire"
            aria-label="Modifier <?= $safeNom ?>">✏️</button>
        <?php endif; ?>
    </div>
    <?php
}

$categoryLabels = [
    'partenaire'    => 'Partenaires',
    'institutionnel' => 'Institutions &amp; Collectivités',
    'federation'    => 'Fédérations',
];

$dossierDir  = __DIR__ . '/../../documents/';
$dossierPath = file_exists($dossierDir . 'dossier-partenaires.pdf')
    ? '/documents/dossier-partenaires.pdf'
    : (file_exists($dossierDir . 'dossier-partenaires-26-27.pdf')
        ? '/documents/dossier-partenaires-26-27.pdf'
        : null);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos partenaires - VBO</title>
    <link href="/css/styles.css?v=20261005" rel="stylesheet">
    <link href="/css/partenaires/partenaires.css?v=20260928" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
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
                <h1>Nos partenaires</h1>
                <p>Grâce à leur soutien, nous pouvons proposer un encadrement de qualité avec du matériel adapté.</p>
            </div>
        </div>
    </div>

    <main class="partenaires-main">

        <!-- Bannière devenir partenaire + dossier de partenariat -->
        <div class="devenir-card">
            <div class="devenir-text">
                <h2>Vous souhaitez devenir partenaire ?</h2>
                <p>Contactez-nous pour en savoir plus sur les avantages que nous pouvons vous offrir.</p>
                <?php if ($isAdmin): ?>
                <form id="dossier-form" enctype="multipart/form-data" class="dossier-upload-form">
                    <label class="dossier-upload-label" for="dossier-file">
                        📄 Changer le dossier (PDF)
                        <input type="file" name="dossier" id="dossier-file" accept="application/pdf" required>
                    </label>
                    <button type="submit" class="btn-dossier btn-dossier--upload">Envoyer</button>
                    <span id="dossier-status" class="dossier-status"></span>
                </form>
                <?php endif; ?>
            </div>
            <div class="devenir-actions">
                <?php if ($dossierPath): ?>
                <a href="<?= htmlspecialchars($dossierPath) ?>" target="_blank" class="btn-contact btn-contact--outline">📄 Voir le dossier</a>
                <?php endif; ?>
                <a href="/pages/nousContacter.html" class="btn-contact">Nous contacter</a>
            </div>
        </div>

        <?php if (!empty($featured)): ?>
        <!-- ── Partenaires en vedette ── -->
        <div class="partners-section partners-section--featured">
            <div class="section-header">
                <div class="section-title">⭐ Nos partenaires en vedette</div>
            </div>
            <div class="partners-grid partners-grid--featured">
                <?php foreach ($featured as $p) render_partner_card($p, $canEdit, true); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($groups as $categorie => $partenaires): ?>
        <!-- ── Section <?= $categoryLabels[$categorie] ?> ── -->
        <div class="partners-section">
            <div class="section-header">
                <div class="section-title"><?= $categoryLabels[$categorie] ?></div>
                <?php if ($canEdit): ?>
                <button class="btn-add-partner" onclick="openAddModal('<?= $categorie ?>')">＋ Ajouter</button>
                <?php endif; ?>
            </div>
            <div class="partners-grid">
                <?php foreach ($partenaires as $p) render_partner_card($p, $canEdit); ?>
                <?php if (empty($partenaires) && $canEdit): ?>
                <p class="partners-empty">Aucun partenaire dans cette catégorie.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </main>

    <?php if ($canEdit): ?>
    <!-- ── Modale édition ── -->
    <div id="editModal" class="partner-modal">
        <div class="partner-modal-content">
            <div class="partner-modal-header">
                <h3>Modifier : <span id="edit-nom-display"></span></h3>
                <span class="close" onclick="closeEditModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="partner-modal-body">
                <form id="edit-form" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="modal-form-group">
                        <label for="edit-nom">Nom</label>
                        <input type="text" name="nom" id="edit-nom" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="edit-categorie">Catégorie</label>
                        <select name="categorie" id="edit-categorie">
                            <option value="partenaire">Partenaire</option>
                            <option value="institutionnel">Institution / Collectivité</option>
                            <option value="federation">Fédération</option>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="edit-url">Lien du site</label>
                        <input type="url" name="url" id="edit-url" placeholder="https://...">
                    </div>
                    <div class="modal-form-group modal-form-group--checkbox">
                        <label for="edit-mis-en-avant">
                            <input type="checkbox" name="mis_en_avant" value="1" id="edit-mis-en-avant" onchange="toggleFeaturedFields('edit')">
                            ⭐ Partenaire mis en avant (carte en vedette, visibilité payante)
                        </label>
                    </div>
                    <div class="modal-form-group" id="edit-date-fin-group">
                        <label for="edit-date-fin">Fin de la mise en avant (optionnel)</label>
                        <input type="date" name="date_fin" id="edit-date-fin">
                    </div>
                    <div class="modal-form-group" id="edit-description-group">
                        <label for="edit-description">Texte de présentation (optionnel)</label>
                        <textarea name="description" id="edit-description" rows="3" maxlength="400" placeholder="Quelques mots sur ce partenaire, affichés sur sa carte en vedette et le bandeau d'accueil…"></textarea>
                    </div>
                    <div class="modal-form-group">
                        <label for="edit-logo-file">Logo</label>
                        <img id="edit-logo-preview" class="modal-logo-preview" src="" alt="Aperçu" style="display:none">
                        <input type="file" name="logo" id="edit-logo-file" class="modal-file-input" accept="image/*">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeEditModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="edit-status"></p>
                    <div class="modal-delete-zone">
                        <button type="button" class="btn-delete-partner" id="btn-delete-partner" onclick="confirmDeletePartner()">🗑 Supprimer ce partenaire</button>
                        <div id="delete-confirm" class="delete-confirm" style="display:none">
                            <span>Confirmer la suppression ?</span>
                            <button type="button" class="btn-delete-confirm" onclick="deletePartner()">Oui, supprimer</button>
                            <button type="button" class="btn-cancel-delete" onclick="cancelDeletePartner()">Annuler</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modale ajout ── -->
    <div id="addModal" class="partner-modal">
        <div class="partner-modal-content">
            <div class="partner-modal-header">
                <h3>Ajouter — <span id="add-categorie-display"></span></h3>
                <span class="close" onclick="closeAddModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="partner-modal-body">
                <form id="add-form" enctype="multipart/form-data">
                    <input type="hidden" name="categorie" id="add-categorie">
                    <div class="modal-form-group">
                        <label for="add-nom">Nom *</label>
                        <input type="text" name="nom" id="add-nom" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="add-url">Lien du site</label>
                        <input type="url" name="url" id="add-url" placeholder="https://...">
                    </div>
                    <div class="modal-form-group modal-form-group--checkbox">
                        <label for="add-mis-en-avant">
                            <input type="checkbox" name="mis_en_avant" value="1" id="add-mis-en-avant" onchange="toggleFeaturedFields('add')">
                            ⭐ Partenaire mis en avant (carte en vedette, visibilité payante)
                        </label>
                    </div>
                    <div class="modal-form-group" id="add-date-fin-group">
                        <label for="add-date-fin">Fin de la mise en avant (optionnel)</label>
                        <input type="date" name="date_fin" id="add-date-fin">
                    </div>
                    <div class="modal-form-group" id="add-description-group">
                        <label for="add-description">Texte de présentation (optionnel)</label>
                        <textarea name="description" id="add-description" rows="3" maxlength="400" placeholder="Quelques mots sur ce partenaire, affichés sur sa carte en vedette et le bandeau d'accueil…"></textarea>
                    </div>
                    <div class="modal-form-group">
                        <label for="add-logo-file">Logo</label>
                        <img id="add-logo-preview" class="modal-logo-preview" src="" alt="Aperçu" style="display:none">
                        <input type="file" name="logo" id="add-logo-file" class="modal-file-input" accept="image/*">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Ajouter</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeAddModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="add-status"></p>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script src="/js/partenaires.js"></script>
</body>
</html>
