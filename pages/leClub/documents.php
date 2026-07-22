<?php
require_once __DIR__ . '/../../php/auth.php';

$canEdit = is_logged_in() && has_any_role(['moderateur','admin']);

$pvag = $statuts = [];

try {
    $pdo = get_pdo();
    $pvag    = $pdo->query("SELECT * FROM staff_documents WHERE type='pvag'    ORDER BY ordre")->fetchAll();
    $statuts = $pdo->query("SELECT * FROM staff_documents WHERE type='statuts' ORDER BY ordre")->fetchAll();
} catch (Exception $e) {}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents officiels - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/leClub/staff.css?v=20260717" rel="stylesheet">
    <link href="/css/leClub/documents.css?v=20260716" rel="stylesheet">
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
                <h1>Documents officiels</h1>
                <p>Procès-verbaux d'assemblées générales, statuts et règlement intérieur du club</p>
            </div>
        </div>
    </div>

    <main class="documents-main">

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
            <?php if (!$pvag && !$canEdit): ?>
            <p class="documents-empty">Aucun PV disponible pour le moment.</p>
            <?php endif; ?>
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
        <div id="statuts-container" class="button-container">
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
            <?php if (!$statuts && !$canEdit): ?>
            <p class="documents-empty">Aucun document disponible pour le moment.</p>
            <?php endif; ?>
        </div>
    </section>

    </main><!-- /.documents-main -->

    <?php if ($canEdit): ?>
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
                        <label for="doc-label" id="doc-label-lbl">Label</label>
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

    <div id="pdf-modal-slot"></div>

    <div id="footer"></div>

    <script>
        const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    </script>
    <script src="/js/pdf-modal.js?v=20260721"></script>
    <script src="/js/main.js?v=20260705"></script>
    <script src="/js/documents.js"></script>
</body>
</html>
