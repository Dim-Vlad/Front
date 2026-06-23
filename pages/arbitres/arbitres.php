<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('arbitre') && !has_role('admin') && !has_role('bureau')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$isAdmin = has_role('admin');

$defaultUrl = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSU4ybuxVW919JSFR-_pI7DsORsjFe8QpAWSCMdggCurNZ9bgse6apd1Dht3opADvkjbs9Sr3i3D7fZ/pubhtml?widget=true&headers=false';
try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'arbitres_sheet_url'");
    $stmt->execute();
    $row  = $stmt->fetch();
    $sheetUrl = ($row && $row['valeur']) ? $row['valeur'] : $defaultUrl;
} catch (Exception $e) {
    $sheetUrl = $defaultUrl;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Planning et feuilles de match pour les arbitres et marqueurs du VBO.">
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/arbitres/arbitres.css?v=20260623" rel="stylesheet">
    <title>Arbitres &amp; Marqueurs - VBO</title>
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
                <h1>Arbitres &amp; Marqueurs</h1>
                <p class="explication">Planning des désignations et feuilles de match pour les arbitres et marqueurs du VBO.</p>
            </div>
        </div>
    </div>

    <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    <main class="sheet-main">

        <div class="sheet-info-card">
            <p>Retrouvez ci-dessous le planning des désignations, les feuilles de match et toutes les informations utiles pour vos missions.</p>
            <?php if ($isAdmin): ?>
            <button class="btn-edit-url" onclick="openUrlModal()">✎ Modifier le lien</button>
            <?php endif; ?>
        </div>

        <div class="sheet-card">
            <iframe
                id="sheet-frame"
                src="<?= htmlspecialchars($sheetUrl) ?>"
                title="Planning arbitres et marqueurs"
                loading="lazy"
                allowfullscreen>
            </iframe>
        </div>

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
            fd.set('cle',    'arbitres_sheet_url');
            fd.set('valeur', url);
            const res  = await fetch('/php/parametres/update.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Erreur inconnue');
            document.getElementById('sheet-frame').src = url;
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

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.html', 'footer');
    </script>
</body>
</html>
