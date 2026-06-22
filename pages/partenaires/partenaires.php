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
    <link href="/css/styles.css?v=20260622" rel="stylesheet">
    <link href="/css/partenaires/partenaires.css" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/x-icon">
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

        <!-- Bannière devenir partenaire -->
        <div class="devenir-card">
            <div class="devenir-text">
                <h2>Vous souhaitez devenir partenaire ?</h2>
                <p>Contactez-nous pour en savoir plus sur les avantages que nous pouvons vous offrir.</p>
            </div>
            <a href="/pages/nousContacter.html" class="btn-contact">Nous contacter</a>
        </div>

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
                <?php foreach ($partenaires as $p):
                    $safeLogo = htmlspecialchars($p['logo'],  ENT_QUOTES);
                    $safeNom  = htmlspecialchars($p['nom'],   ENT_QUOTES);
                    $safeUrl  = htmlspecialchars($p['url'],   ENT_QUOTES);
                    $safeCat  = htmlspecialchars($p['categorie'], ENT_QUOTES);
                ?>
                <div class="partner-card"
                    data-id="<?= $p['id'] ?>"
                    data-nom="<?= $safeNom ?>"
                    data-url="<?= $safeUrl ?>"
                    data-logo="<?= $safeLogo ?>"
                    data-categorie="<?= $safeCat ?>">
                    <div class="partner-logo-wrap">
                        <?php if ($p['logo']): ?>
                        <img class="partner-logo" src="<?= $safeLogo ?>" alt="<?= $safeNom ?>">
                        <?php else: ?>
                        <div class="partner-logo-placeholder"><?= htmlspecialchars($p['nom'][0]) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="partner-footer">
                        <span class="partner-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <?php if ($p['url']): ?>
                        <a class="partner-link" href="<?= $safeUrl ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()">Visiter →</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($canEdit): ?>
                    <button class="partner-edit-btn"
                        onclick="event.stopPropagation(); openEditModal(this.closest('.partner-card'))"
                        title="Modifier ce partenaire"
                        aria-label="Modifier <?= $safeNom ?>">✏️</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($partenaires) && $canEdit): ?>
                <p class="partners-empty">Aucun partenaire dans cette catégorie.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Dossier de partenariat -->
        <div class="dossier-section">
            <h2>Dossier de partenariat</h2>
            <p>Consultez nos offres et avantages pour les partenaires.</p>
            <?php if ($dossierPath): ?>
            <a href="<?= htmlspecialchars($dossierPath) ?>" target="_blank" class="btn-dossier">Voir le dossier</a>
            <?php endif; ?>
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

    <script src="/js/main.js"></script>
    <script src="/js/partenaires.js"></script>
</body>
</html>
