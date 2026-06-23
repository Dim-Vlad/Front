<?php
require_once __DIR__ . '/../../php/auth.php';

$canEdit = is_logged_in() && has_any_role(['moderateur','admin']);

$bureau = $membres = $commissions = $entraineurs = [];
$pvag   = $statuts = [];
$descMap = [];

try {
    $pdo = get_pdo();

    $allMembres = $pdo->query(
        "SELECT * FROM staff_membres ORDER BY FIELD(section,'bureau','membres','commissions','entraineurs'), ordre"
    )->fetchAll();

    foreach ($allMembres as $m) {
        switch ($m['section']) {
            case 'bureau':       $bureau[]       = $m; break;
            case 'membres':      $membres[]      = $m; break;
            case 'commissions':  $commissions[]  = $m; $descMap[$m['id']] = $m['description']; break;
            case 'entraineurs':
                if ($canEdit || $m['visible']) $entraineurs[] = $m;
                break;
        }
    }

    $pvag    = $pdo->query("SELECT * FROM staff_documents WHERE type='pvag'   ORDER BY ordre")->fetchAll();
    $statuts = $pdo->query("SELECT * FROM staff_documents WHERE type='statuts' ORDER BY ordre")->fetchAll();
} catch (Exception $e) {}

// Séparer président (ordre 1) et bureau exécutif (le reste)
$president  = $bureau[0]           ?? null;
$executives = array_slice($bureau, 1);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

function cardAttrs(array $m): string {
    return 'data-id="'        . $m['id']        . '" '
        . 'data-section="'   . $m['section']   . '" '
        . 'data-nom="'       . h($m['nom'])     . '" '
        . 'data-sous_titre="'. h($m['sous_titre']) . '" '
        . 'data-photo="'     . h($m['photo'])   . '" '
        . 'data-vcard="'     . h($m['lien_vcard'] ?? '') . '" '
        . 'data-visible="'   . $m['visible']    . '"';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Le staff - VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/leClub/staff.css?v=20260623" rel="stylesheet">
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
                <h1>Staff VBO</h1>
                <p>Toutes les infos du staff, bureau, commissions, entraîneurs, PV assemblées générales</p>
            </div>
        </div>
    </div>

    <main class="staff-main">

    <!-- ══ BUREAU DIRECTEUR ══════════════════════════════════════════ -->
    <section class="organigramme">
        <div class="staff-section-header">
            <h2>Bureau directeur</h2>
            <?php if ($canEdit): ?>
            <button class="btn-add-staff" onclick="openAddModal('bureau')" title="Ajouter un membre">＋ Ajouter</button>
            <?php endif; ?>
        </div>

        <?php if ($president): ?>
        <div class="level president">
            <div class="position" <?= cardAttrs($president) ?>>
                <?php if ($canEdit): ?>
                <button class="staff-edit-btn" onclick="openEditModal(this.closest('.position'))" title="Modifier">✏️</button>
                <?php endif; ?>
                <img src="<?= h($president['photo']) ?>" alt="<?= h($president['nom']) ?>">
                <p class="categorie"><?= h($president['sous_titre']) ?></p>
                <p class="name"><?= h($president['nom']) ?></p>
                <?php if ($president['lien_vcard']): ?>
                <a href="<?= h($president['lien_vcard']) ?>" class="see-more" onclick="event.stopPropagation()">Voir plus</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="level executives">
            <?php foreach ($executives as $m): ?>
            <div class="position" <?= cardAttrs($m) ?>>
                <?php if ($canEdit): ?>
                <button class="staff-edit-btn" onclick="openEditModal(this.closest('.position'))" title="Modifier">✏️</button>
                <?php endif; ?>
                <img src="<?= h($m['photo']) ?>" alt="<?= h($m['nom']) ?>">
                <p class="categorie"><?= h($m['sous_titre']) ?></p>
                <p class="name"><?= h($m['nom']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <hr class="separator">

        <div class="staff-section-header">
            <h2>Membres du bureau</h2>
            <?php if ($canEdit): ?>
            <button class="btn-add-staff" onclick="openAddModal('membres')" title="Ajouter un membre">＋ Ajouter</button>
            <?php endif; ?>
        </div>
        <div class="level members">
            <?php foreach ($membres as $m): ?>
            <div class="position" <?= cardAttrs($m) ?>>
                <?php if ($canEdit): ?>
                <button class="staff-edit-btn" onclick="openEditModal(this.closest('.position'))" title="Modifier">✏️</button>
                <?php endif; ?>
                <img src="<?= h($m['photo']) ?>" alt="<?= h($m['nom']) ?>">
                <p class="name"><?= h($m['nom']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <hr class="separator">

    <!-- ══ COMMISSIONS ═══════════════════════════════════════════════ -->
    <section class="trombinoscope" id="cible-commissions">
        <div class="staff-section-header">
            <h2>Commissions</h2>
            <?php if ($canEdit): ?>
            <button class="btn-add-staff" onclick="openAddModal('commissions')" title="Ajouter une commission">＋ Ajouter</button>
            <?php endif; ?>
        </div>
        <div class="level-coaches">
            <?php foreach ($commissions as $m): ?>
            <div class="position" <?= cardAttrs($m) ?>
                    role="button" tabindex="0" onclick="openViewModal(this)">
                <?php if ($canEdit): ?>
                <button class="staff-edit-btn" onclick="event.stopPropagation();openEditModal(this.closest('.position'))" title="Modifier">✏️</button>
                <?php endif; ?>
                <img src="<?= h($m['photo']) ?>" alt="<?= h($m['sous_titre']) ?>">
                <p class="name-commission"><?= h($m['sous_titre']) ?></p>
                <?php if ($m['nom']): ?><p class="categorie"><?= h($m['nom']) ?></p><?php endif; ?>
                <p class="see-more">Voir plus</p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <hr class="separator">

    <!-- ══ ENTRAÎNEURS ═══════════════════════════════════════════════ -->
    <section class="trombinoscope">
        <div class="staff-section-header">
            <h2>Entraîneurs</h2>
            <?php if ($canEdit): ?>
            <button class="btn-add-staff" onclick="openAddModal('entraineurs')" title="Ajouter un entraîneur">＋ Ajouter</button>
            <?php endif; ?>
        </div>
        <div class="level-coaches">
            <?php foreach ($entraineurs as $m): ?>
            <div class="position <?= (!$m['visible'] && $canEdit) ? 'coach-hidden' : '' ?>" <?= cardAttrs($m) ?>>
                <?php if ($canEdit): ?>
                <div class="coach-edit-group">
                    <button class="btn-coach-toggle <?= $m['visible'] ? 'is-visible' : 'is-hidden' ?>"
                            onclick="toggleCoachVisible(this.closest('.position'))"
                            title="<?= $m['visible'] ? 'Masquer aux visiteurs' : 'Rendre visible' ?>">👁</button>
                    <button class="staff-edit-btn-inline" onclick="openEditModal(this.closest('.position'))" title="Modifier">✏️</button>
                </div>
                <?php endif; ?>
                <img src="<?= h($m['photo']) ?>" alt="<?= h($m['nom']) ?>">
                <p class="name"><?= h($m['nom']) ?></p>
                <p class="categorie"><u>Catégorie :</u><br><?= h($m['sous_titre']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <hr class="separator">

    <!-- ══ PV ASSEMBLÉES GÉNÉRALES ═══════════════════════════════════ -->
    <section class="pvag">
        <div class="staff-section-header">
            <h2>Procès-verbaux<br>Assemblées Générales</h2>
            <?php if ($canEdit): ?>
            <button class="btn-add-staff" onclick="openDocModal('pvag')" title="Ajouter un PV">＋ Ajouter</button>
            <?php endif; ?>
        </div>
        <div class="button-container" id="pvag-container">
            <?php foreach ($pvag as $doc): ?>
            <div class="pvag-item" data-id="<?= $doc['id'] ?>">
                <button class="btn" onclick="openPdfModal('<?= h($doc['path']) ?>', '<?= h($doc['label']) ?>')"><?= h($doc['label']) ?><br><u>Consulter</u></button>
                <?php if ($canEdit): ?>
                <div class="doc-admin-btns">
                    <button class="btn-doc-move" onclick="moveDoc(this,-1)" title="Monter">▲</button>
                    <button class="btn-doc-move" onclick="moveDoc(this,1)" title="Descendre">▼</button>
                    <button class="btn-pvag-delete" onclick="deleteDocument(<?= $doc['id'] ?>, 'pvag')" title="Supprimer">🗑</button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <hr class="separator">

    <!-- ══ STATUTS & RÈGLEMENT INTÉRIEUR ════════════════════════════ -->
    <section class="pvag">
        <div class="staff-section-header">
            <h2>Statuts<br>Règlement Intérieur</h2>
            <?php if ($canEdit): ?>
            <button class="btn-add-staff" onclick="openDocModal('statuts')" title="Ajouter un document">＋ Ajouter</button>
            <?php endif; ?>
        </div>
        <div id="statuts-container">
            <?php foreach ($statuts as $doc): ?>
            <div class="pvag-item" data-id="<?= $doc['id'] ?>">
                <button class="btn" onclick="openPdfModal('<?= h($doc['path']) ?>', '<?= h($doc['label']) ?>')"><?= h($doc['label']) ?><br><u>Consulter</u></button>
                <?php if ($canEdit): ?>
                <div class="doc-admin-btns">
                    <button class="btn-doc-move" onclick="moveDoc(this,-1)" title="Monter">▲</button>
                    <button class="btn-doc-move" onclick="moveDoc(this,1)" title="Descendre">▼</button>
                    <button class="btn-pvag-delete" onclick="deleteDocument(<?= $doc['id'] ?>, 'statuts')" title="Supprimer">🗑</button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    </main><!-- /.staff-main -->

    <!-- ══ MODALE VOIR PLUS (commissions — publique) ═════════════════ -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeViewModal()">&times;</span>
            <img id="modal-image" src="" alt="">
            <h2 id="modal-title"></h2>
            <p id="modal-subtitle"></p>
            <div id="modal-description" class="modal-description"></div>
            <div id="modal-links"></div>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <!-- ══ MODALE AJOUT / ÉDITION MEMBRE ════════════════════════════ -->
    <div id="memberModal" class="staff-modal">
        <div class="staff-modal-content">
            <div class="staff-modal-header">
                <h3 id="mb-modal-title">Modifier le membre</h3>
                <span class="close" onclick="closeMemberModal()">&times;</span>
            </div>
            <div class="staff-modal-body">
                <form id="member-form">
                    <input type="hidden" name="id"      id="mb-id">
                    <input type="hidden" name="section" id="mb-section">

                    <div class="staff-form-row">
                        <label for="mb-nom">Nom</label>
                        <input type="text" name="nom" id="mb-nom" required placeholder="Prénom Nom">
                    </div>
                    <div class="staff-form-row">
                        <label for="mb-sous_titre">Sous-titre</label>
                        <input type="text" name="sous_titre" id="mb-sous_titre" placeholder="Rôle / Commission / Catégorie">
                    </div>
                    <div class="staff-form-row">
                        <label>Photo</label>
                        <div class="photo-picker">
                            <select id="mb-photo-sel" onchange="onPhotoSelectChange()">
                                <option value="/images/icones/Asset 8@3x.png">Silhouette masculine</option>
                                <option value="/images/icones/Asset 1@3x.png">Silhouette féminine</option>
                                <option value="/images/icones/Asset 3@3x.png">Silhouette neutre</option>
                                <option value="/images/icones/interrogation-point.png">Point d'interrogation</option>
                                <option value="_upload">📁 Importer une photo…</option>
                                <option value="_custom">Chemin personnalisé…</option>
                            </select>
                            <div id="mb-upload-zone" style="display:none;margin-top:.5rem">
                                <input type="file" id="mb-photo-file" accept="image/*" onchange="uploadPhotoPreview(this)">
                                <p id="mb-upload-status" class="photo-upload-status"></p>
                            </div>
                            <input type="text" id="mb-photo-custom" placeholder="/images/..." style="display:none;margin-top:.4rem;width:100%" oninput="syncCustomPhoto(this)">
                            <div class="photo-preview-wrap">
                                <img id="mb-photo-preview" src="/images/icones/interrogation-point.png" alt="Aperçu">
                            </div>
                            <input type="hidden" name="photo" id="mb-photo">
                        </div>
                    </div>
                    <div class="staff-form-row" id="mb-vcard-row" style="display:none">
                        <label for="mb-vcard">Lien vCard</label>
                        <input type="text" name="lien_vcard" id="mb-vcard" placeholder="/vCard/vCard-xxx.html">
                    </div>
                    <div class="staff-form-row" id="mb-desc-row" style="display:none">
                        <label for="mb-desc">Description<br><small>(HTML basique)</small></label>
                        <textarea name="description" id="mb-desc" rows="8" placeholder="<h3>Objectif</h3><p>...</p><h3>Missions</h3><ul><li>...</li></ul>"></textarea>
                    </div>

                    <div class="staff-modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeMemberModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="mb-status"></p>

                    <div id="mb-delete-zone" class="staff-delete-zone" style="display:none">
                        <button type="button" class="btn-delete-item" id="btn-delete-member" onclick="confirmDeleteMember()">🗑 Supprimer ce membre</button>
                        <div id="mb-delete-confirm" class="delete-confirm" style="display:none">
                            <span>Confirmer la suppression ?</span>
                            <button type="button" class="btn-delete-confirm" onclick="deleteMember()">Oui</button>
                            <button type="button" class="btn-cancel-delete" onclick="cancelDeleteMember()">Annuler</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ══ MODALE AJOUT DOCUMENT ════════════════════════════════════ -->
    <div id="docModal" class="staff-modal">
        <div class="staff-modal-content">
            <div class="staff-modal-header">
                <h3 id="doc-modal-title">Ajouter un document</h3>
                <span class="close" onclick="closeDocModal()">&times;</span>
            </div>
            <div class="staff-modal-body">
                <form id="doc-form" enctype="multipart/form-data">
                    <input type="hidden" name="type" id="doc-type">
                    <div class="staff-form-row">
                        <label for="doc-label">Label</label>
                        <input type="text" name="label" id="doc-label" required placeholder="PV AG 2025-2026">
                    </div>
                    <div class="staff-form-row">
                        <label for="doc-fichier">Fichier PDF</label>
                        <input type="file" name="fichier" id="doc-fichier" accept=".pdf" required>
                    </div>
                    <div class="staff-modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeDocModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="doc-status"></p>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ MODALE VISIONNEUSE PDF ═══════════════════════════════════ -->
    <div id="pdfModal" class="pdf-modal" onclick="if(event.target===this)closePdfModal()">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <span class="pdf-modal-title" id="pdf-modal-title"></span>
                <div class="pdf-modal-btns">
                    <button class="btn-pdf-action" onclick="printPdf()">🖨 Imprimer</button>
                    <a id="pdf-download-btn" href="#" download class="btn-pdf-action">⬇ Télécharger</a>
                    <button class="btn-pdf-close" onclick="closePdfModal()" title="Fermer">✕</button>
                </div>
            </div>
            <iframe id="pdf-iframe" src="" title="Visionneuse PDF"></iframe>
            <div id="pdf-fallback">
                <p>L'aperçu PDF n'est pas disponible sur cet appareil.</p>
                <a id="pdf-open-link" href="#" target="_blank">Ouvrir le PDF ↗</a>
            </div>
        </div>
    </div>

    <div id="footer"></div>

    <script>
        const CAN_EDIT         = <?= $canEdit ? 'true' : 'false' ?>;
        const STAFF_DESC       = <?= json_encode($descMap) ?>;
    </script>
    <script src="/js/main.js"></script>
    <script src="/js/staff.js"></script>
</body>
</html>
