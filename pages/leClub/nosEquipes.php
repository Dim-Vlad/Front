<?php
require_once __DIR__ . '/../../php/auth.php';

$canEdit = is_logged_in() && has_any_role(['moderateur', 'admin']);

try {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM equipes ORDER BY groupe DESC, ordre ASC")->fetchAll();
    $seniors = array_values(array_filter($rows, fn($e) => $e['groupe'] === 'seniors'));
    $jeunes  = array_values(array_filter($rows, fn($e) => $e['groupe'] === 'jeunes'));
} catch (Exception $e) {
    $seniors = [];
    $jeunes  = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos équipes - VBO</title>
    <link href="/css/styles.css?v=20260623" rel="stylesheet">
    <link href="/css/leClub/nosEquipes.css?v=20260623" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <script async defer src="https://widgets.scorenco.com/host/widgets.js"></script>
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Nos équipes</h1>
                <p>Cliquez sur une équipe pour voir l'entraîneur, le classement ou nous contacter.</p>
            </div>
        </div>
    </div>

    <main class="equipes-main">

        <!-- ── Seniors ── -->
        <div class="teams-group">
            <div class="group-header">
                <div class="group-title">Seniors</div>
                <?php if ($canEdit): ?>
                <button class="btn-add-team" onclick="openAddModal('seniors')">＋ Ajouter une équipe</button>
                <?php endif; ?>
            </div>
            <div class="teams-grid">
                <?php foreach ($seniors as $e):
                    $safeNom   = htmlspecialchars($e['nom'],   ENT_QUOTES);
                    $safeCoach = htmlspecialchars($e['coach'], ENT_QUOTES);
                    $safeLien  = htmlspecialchars($e['lien'],  ENT_QUOTES);
                    $safePhoto = htmlspecialchars($e['photo'], ENT_QUOTES);
                    $safePoule = htmlspecialchars($e['niveau'], ENT_QUOTES);
                ?>
                <div class="team-card" role="button" tabindex="0"
                    data-id="<?= $e['id'] ?>"
                    data-nom="<?= $safeNom ?>"
                    data-coach="<?= $safeCoach ?>"
                    data-lien="<?= $safeLien ?>"
                    data-photo="<?= $safePhoto ?>"
                    data-poule="<?= $safePoule ?>"
                    onclick="openModalFromCard(this)"
                    onkeypress="if(event.key==='Enter') openModalFromCard(this)">
                    <div class="card-photo-wrap">
                        <img class="card-photo" src="<?= $safePhoto ?>" alt="<?= $safeNom ?>">
                    </div>
                    <div class="card-footer">
                        <div class="card-name"><?= htmlspecialchars($e['nom']) ?></div>
                        <div class="card-level"><?= htmlspecialchars($e['niveau']) ?></div>
                    </div>
                    <?php if ($canEdit): ?>
                    <button class="card-edit-btn"
                        onclick="event.stopPropagation(); openEditModal(this.closest('.team-card'))"
                        title="Modifier cette équipe"
                        aria-label="Modifier <?= $safeNom ?>">✏️</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Jeunes ── -->
        <div class="teams-group">
            <div class="group-header">
                <div class="group-title">Jeunes</div>
                <?php if ($canEdit): ?>
                <button class="btn-add-team" onclick="openAddModal('jeunes')">＋ Ajouter une équipe</button>
                <?php endif; ?>
            </div>
            <div class="teams-grid">
                <?php foreach ($jeunes as $e):
                    $safeNom   = htmlspecialchars($e['nom'],   ENT_QUOTES);
                    $safeCoach = htmlspecialchars($e['coach'], ENT_QUOTES);
                    $safeLien  = htmlspecialchars($e['lien'],  ENT_QUOTES);
                    $safePhoto = htmlspecialchars($e['photo'], ENT_QUOTES);
                    $safePoule = htmlspecialchars($e['niveau'], ENT_QUOTES);
                ?>
                <div class="team-card" role="button" tabindex="0"
                    data-id="<?= $e['id'] ?>"
                    data-nom="<?= $safeNom ?>"
                    data-coach="<?= $safeCoach ?>"
                    data-lien="<?= $safeLien ?>"
                    data-photo="<?= $safePhoto ?>"
                    data-poule="<?= $safePoule ?>"
                    onclick="openModalFromCard(this)"
                    onkeypress="if(event.key==='Enter') openModalFromCard(this)">
                    <div class="card-photo-wrap">
                        <img class="card-photo" src="<?= $safePhoto ?>" alt="<?= $safeNom ?>">
                    </div>
                    <div class="card-footer">
                        <div class="card-name"><?= htmlspecialchars($e['nom']) ?></div>
                        <div class="card-level"><?= htmlspecialchars($e['niveau']) ?></div>
                    </div>
                    <?php if ($canEdit): ?>
                    <button class="card-edit-btn"
                        onclick="event.stopPropagation(); openEditModal(this.closest('.team-card'))"
                        title="Modifier cette équipe"
                        aria-label="Modifier <?= $safeNom ?>">✏️</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>

    <!-- ── Modale info ── -->
    <div id="infoModal" class="modal" role="dialog" aria-modal="true">
        <div class="modal-content" role="document">
            <div class="modal-header">
                <h2 id="modal-title"></h2>
                <span class="close" role="button" tabindex="0"
                    onclick="closeModal()"
                    onkeypress="if(event.key==='Enter') closeModal()"
                    aria-label="Fermer">&times;</span>
            </div>
            <img id="modal-image" src="" alt="Photo de l'équipe">
            <div class="modal-body">
                <p id="modal-body" class="modal-coach"></p>
                <div class="modal-actions">
                    <a class="btn" id="modal-link" href="" target="_blank">Voir le classement</a>
                    <a class="btn btn-outline" id="contact-link" href="/pages/nousContacter.html">Nous contacter</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <!-- ── Modale édition (moderateur/admin) ── -->
    <div id="editModal" class="edit-modal">
        <div class="edit-modal-content">
            <div class="edit-modal-header">
                <h3>Modifier : <span id="edit-nom-display"></span></h3>
                <span class="close" onclick="closeEditModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="edit-modal-body">
                <form id="edit-form" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="edit-form-group">
                        <label for="edit-poule">Poule</label>
                        <input type="text" name="poule" id="edit-poule" placeholder="Ex :Poule A, Excellence, etc.">
                    </div>
                    <div class="edit-form-group">
                        <label for="edit-coach">Entraîneur</label>
                        <input type="text" name="coach" id="edit-coach" placeholder="Nom de l'entraîneur">
                    </div>
                    <div class="edit-form-group">
                        <label for="edit-lien">Lien championnat</label>
                        <input type="url" name="lien" id="edit-lien" placeholder="https://...">
                    </div>
                    <div class="edit-form-group">
                        <label for="edit-file">Photo de l'équipe</label>
                        <img id="edit-photo-preview" class="edit-photo-preview" src="" alt="Aperçu actuel">
                        <input type="file" name="photo" id="edit-file" class="edit-file-input" accept="image/*">
                    </div>
                    <div class="edit-modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-edit" onclick="closeEditModal()">Annuler</button>
                    </div>
                    <p class="edit-status" id="edit-status"></p>
                    <div class="edit-delete-zone">
                        <button type="button" class="btn-delete" id="btn-delete-equipe" onclick="confirmDeleteEquipe()">🗑 Supprimer l'équipe</button>
                        <div id="delete-confirm" class="delete-confirm" style="display:none">
                            <span>Confirmer la suppression ?</span>
                            <button type="button" class="btn-delete-confirm" onclick="deleteEquipe()">Oui, supprimer</button>
                            <button type="button" class="btn-cancel-delete" onclick="cancelDeleteEquipe()">Annuler</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modale ajout ── -->
    <div id="addModal" class="edit-modal">
        <div class="edit-modal-content">
            <div class="edit-modal-header">
                <h3>Ajouter — <span id="add-groupe-display"></span></h3>
                <span class="close" onclick="closeAddModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="edit-modal-body">
                <form id="add-form" enctype="multipart/form-data">
                    <input type="hidden" name="groupe" id="add-groupe">
                    <div class="edit-form-group">
                        <label for="add-nom">Nom de l'équipe *</label>
                        <input type="text" name="nom" id="add-nom" placeholder="Ex : Masculin 1" required>
                    </div>
                    <div class="edit-form-group">
                        <label for="add-poule">Poule</label>
                        <input type="text" name="poule" id="add-poule" placeholder="Ex : Poule A, Excellence…">
                    </div>
                    <div class="edit-form-group">
                        <label for="add-coach">Entraîneur</label>
                        <input type="text" name="coach" id="add-coach" placeholder="Nom de l'entraîneur">
                    </div>
                    <div class="edit-form-group">
                        <label for="add-lien">Lien championnat</label>
                        <input type="url" name="lien" id="add-lien" placeholder="https://...">
                    </div>
                    <div class="edit-form-group">
                        <label for="add-file">Photo de l'équipe</label>
                        <img id="add-photo-preview" class="edit-photo-preview" src="" alt="Aperçu" style="display:none">
                        <input type="file" name="photo" id="add-file" class="edit-file-input" accept="image/*">
                    </div>
                    <div class="edit-modal-actions">
                        <button type="submit" class="btn-save">Ajouter</button>
                        <button type="button" class="btn-cancel-edit" onclick="closeAddModal()">Annuler</button>
                    </div>
                    <p class="edit-status" id="add-status"></p>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script src="/js/nosEquipes.js"></script>
</body>
</html>