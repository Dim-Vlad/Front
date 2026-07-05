<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (has_role('arbitre')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$isAdmin = has_role('admin');

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'presences_sheet_url'");
    $stmt->execute();
    $row      = $stmt->fetch();
    $sheetUrl = ($row && $row['valeur']) ? $row['valeur'] : '';
} catch (Exception $e) {
    $sheetUrl = '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pointage des présences - VBO">
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/leClub/minibus.css?v=20260623" rel="stylesheet">
    <title>Pointage Présences - VBO</title>
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
                <h1>Pointage Présences</h1>
                <p class="explication">Suivez le pointage des présences aux entraînements et aux matchs.</p>
            </div>
        </div>
    </div>

    <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    <main class="sheet-main">

        <div class="sheet-info-card">
            <p>Retrouvez ci-dessous le tableau de pointage des présences.</p>
            <?php if ($isAdmin): ?>
            <button class="btn-edit-url" onclick="openUrlModal()">✎ Modifier le lien</button>
            <?php endif; ?>
        </div>

        <?php if ($sheetUrl): ?>
        <div class="sheet-card">
            <iframe
                id="sheet-frame"
                src="<?= htmlspecialchars($sheetUrl) ?>"
                title="Pointage présences"
                loading="lazy"
                allowfullscreen>
            </iframe>
        </div>
        <?php else: ?>
        <div class="sheet-info-card" style="text-align:center; color: var(--text-muted, #888);">
            <?php if ($isAdmin): ?>
                <p>Aucun lien configuré. Cliquez sur <strong>✎ Modifier le lien</strong> pour ajouter le Google Sheet.</p>
            <?php else: ?>
                <p>Le tableau de présences n'est pas encore configuré.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>

    <?php if ($isAdmin): ?>
    <div id="urlModal" class="url-modal" onclick="if(event.target===this)closeUrlModal()">
        <div class="url-modal-content">
            <h2 class="url-modal-title">Modifier le lien Google Sheet</h2>
            <p class="url-modal-desc">
                Collez l'URL <strong>« Publier sur le web »</strong> du Google Sheet.<br>
                Dans Google Sheet : <strong>Fichier → Partager → Publier sur le web</strong>, puis copiez le lien (format pubhtml).
            </p>
            <input type="url" id="sheetUrlInput" class="url-input"
                   value="<?= htmlspecialchars($sheetUrl) ?>"
                   placeholder="https://docs.google.com/spreadsheets/…/pubhtml?…">
            <p class="url-status" id="urlStatus"></p>
            <div class="url-modal-actions">
                <button class="btn-cancel" onclick="closeUrlModal()">Annuler</button>
                <button class="btn-save" id="urlSaveBtn" onclick="saveUrl()">Enregistrer</button>
            </div>
        </div>
    </div>

    <script>
    function openUrlModal() {
        document.getElementById('urlModal').classList.add('open');
        document.body.style.overflow = 'hidden';
        document.getElementById('sheetUrlInput').focus();
    }
    function closeUrlModal() {
        document.getElementById('urlModal').classList.remove('open');
        document.body.style.overflow = '';
        document.getElementById('urlStatus').textContent = '';
        document.getElementById('urlStatus').className   = 'url-status';
    }
    async function saveUrl() {
        const statusEl = document.getElementById('urlStatus');
        const saveBtn  = document.getElementById('urlSaveBtn');
        const url      = document.getElementById('sheetUrlInput').value.trim();
        if (!url) { statusEl.textContent = 'URL requise.'; return; }
        statusEl.textContent = '';
        saveBtn.disabled     = true;
        saveBtn.textContent  = 'Enregistrement…';
        try {
            const fd = new FormData();
            fd.set('cle',    'presences_sheet_url');
            fd.set('valeur', url);
            const res  = await fetch('/php/parametres/update.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Erreur inconnue');
            const frame = document.getElementById('sheet-frame');
            if (frame) {
                frame.src = url;
            } else {
                location.reload();
            }
            statusEl.textContent = '✓ Lien mis à jour !';
            statusEl.className   = 'url-status ok';
            setTimeout(closeUrlModal, 1400);
        } catch (err) {
            statusEl.textContent = err.message;
        } finally {
            saveBtn.disabled    = false;
            saveBtn.textContent = 'Enregistrer';
        }
    }
    </script>
    <?php endif; ?>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
