<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (has_role('arbitre')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$isAdmin = has_role('admin');

$defaultUrl = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSoX6qCPdbyniQOM5IAtl8ACquuRfZoK8KMqNMKNxmosBEIokNlY6Ky3QxiQVGoGg/pubhtml?widget=true&headers=false';
try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'minibus_sheet_url'");
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
    <meta name="description" content="Réservations des minibus du VBO.">
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/leClub/minibus.css?v=20260707" rel="stylesheet">
    <title>Réservations Minibus - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .sheet-card iframe { height: 560px; }
        @media (max-width: 700px) { .sheet-card iframe { height: 420px; } }

        .iframe-toolbar {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .6rem .9rem;
            border-bottom: 1px solid #edf2ed;
            background: #f8faf8;
        }
        .iframe-toolbar__label {
            font-size: .78rem;
            color: #888;
            margin-right: .25rem;
        }
        .zoom-btn {
            width: 28px;
            height: 28px;
            border: 1.5px solid #ccc;
            border-radius: 6px;
            background: #fff;
            color: var(--secondary-color);
            font-size: 1rem;
            line-height: 1;
            cursor: pointer;
            transition: background .15s, border-color .15s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .zoom-btn:hover { background: #eef6ee; border-color: var(--secondary-color); }
        .zoom-level {
            font-size: .78rem;
            color: var(--bg-dark);
            min-width: 36px;
            text-align: center;
        }
        .zoom-reset {
            font-size: .7rem;
            width: auto;
            padding: 0 .5rem;
            color: #aaa;
            border-color: #e0e0e0;
        }
        .zoom-reset:hover { color: var(--bg-dark); border-color: #aaa; background: #f5f5f5; }
    </style>
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Réservations Minibus</h1>
                <p class="explication">Consultez et planifiez les réservations des minibus de la mairie et du club.</p>
            </div>
        </div>
    </div>

    <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    <main class="sheet-main">

        <div class="sheet-info-card">
            <p>Retrouvez ci-dessous le planning de réservation des minibus. Contactez le bureau pour toute demande de réservation.</p>
            <div class="sheet-info-actions">
                <a href="<?= htmlspecialchars(strtok($sheetUrl, '?') ?: $sheetUrl) ?>"
                   target="_blank" rel="noopener" class="btn-open-sheet">↗ Ouvrir dans l'onglet</a>
                <?php if ($isAdmin): ?>
                <button class="btn-edit-url" onclick="openUrlModal()">✎ Modifier le lien</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="sheet-card">
            <div class="iframe-toolbar">
                <span class="iframe-toolbar__label">Zoom :</span>
                <button class="zoom-btn" onclick="adjustZoom(-0.15)" title="Dézoomer">−</button>
                <span class="zoom-level" id="zoomLabel">100%</span>
                <button class="zoom-btn" onclick="adjustZoom(0.15)" title="Zoomer">+</button>
                <button class="zoom-btn zoom-reset" onclick="resetZoom()" title="Réinitialiser">↺ Reset</button>
            </div>
            <iframe
                id="sheet-frame"
                src="<?= htmlspecialchars($sheetUrl) ?>"
                title="Planning réservations minibus"
                loading="lazy"
                allowfullscreen>
            </iframe>
        </div>

    </main>

    <script>
    var _zoom = 1;
    function adjustZoom(delta) {
        _zoom = Math.min(2, Math.max(0.4, +(_zoom + delta).toFixed(2)));
        document.getElementById('sheet-frame').style.zoom = _zoom;
        document.getElementById('zoomLabel').textContent  = Math.round(_zoom * 100) + '%';
    }
    function resetZoom() {
        _zoom = 1;
        document.getElementById('sheet-frame').style.zoom = '';
        document.getElementById('zoomLabel').textContent  = '100%';
    }
    </script>

    <?php if ($isAdmin): ?>
    <div id="urlModal" class="url-modal" onclick="if(event.target===this)closeUrlModal()">
        <div class="url-modal-content">
            <h2 class="url-modal-title">Modifier le lien Google Sheet</h2>
            <div class="url-modal-desc">
                <strong>Comment récupérer le lien ?</strong>
                <ol class="url-modal-steps">
                    <li>Ouvrez le Google Sheet des réservations minibus.</li>
                    <li>Dans le menu : <strong>Fichier → Partager → Publier sur le web</strong>.</li>
                    <li>Dans la fenêtre, sélectionnez la feuille souhaitée (le format n'a pas d'importance).</li>
                    <li>Cliquez sur <strong>« Publier »</strong>, puis confirmez.</li>
                    <li>Copiez le lien généré et collez-le ci-dessous.</li>
                </ol>
                <p class="url-modal-format">
                    Format attendu :<br>
                    <code>https://docs.google.com/spreadsheets/d/e/<em>…ID…</em>/pubhtml?…</code>
                </p>
            </div>
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
            fd.set('cle',    'minibus_sheet_url');
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

    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
