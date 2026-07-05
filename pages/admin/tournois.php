<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_any_role(['admin', 'moderateur'])) {
    header('Location: /pages/auth/tableau-de-bord.php'); exit;
}

$pdo      = get_pdo();
$tournois = $pdo->query("SELECT * FROM tournois ORDER BY created_at DESC")->fetchAll();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Tournois - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260703" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    .t-container { max-width: 860px; margin: 0 auto; padding: 0 1.5rem 4rem; }
    .t-toolbar { display: flex; align-items: center; justify-content: space-between; margin: 1.5rem 0 1rem; flex-wrap: wrap; gap: .75rem; }
    .t-toolbar h2 { font-size: 1.1rem; font-weight: 700; color: var(--secondary-color); margin: 0; }
    .btn-add-t { background: var(--secondary-color); color: #fff; border: none; border-radius: 20px; padding: .4rem 1.1rem; font-size: .85rem; font-weight: 600; font-family: inherit; cursor: pointer; transition: background .2s; }
    .btn-add-t:hover { background: var(--bg-dark); }
    .t-empty { color: #888; font-style: italic; font-size: .95rem; padding: 2rem 0; text-align: center; }
    .t-list { display: flex; flex-direction: column; gap: .85rem; }
    .t-card { background: #fff; border-radius: 12px; border-left: 5px solid var(--primary-color); box-shadow: 0 2px 10px rgba(0,0,0,.07); padding: 1rem 1.2rem; }
    .t-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .t-card-title { font-weight: 700; color: var(--secondary-color); font-size: 1rem; margin: 0 0 .2rem; }
    .t-card-saison { font-size: .8rem; color: #777; }
    .t-card-actions { display: flex; gap: .5rem; flex-shrink: 0; }
    .t-url { font-size: .78rem; color: #555; background: #f3f6f3; border-radius: 6px; padding: .3rem .65rem; margin-top: .6rem; display: flex; align-items: center; gap: .6rem; font-family: monospace; word-break: break-all; }
    @media (max-width: 600px) {
        .t-card { border-left: none; border-top: 5px solid var(--primary-color); }
        .t-card-top { flex-direction: column; align-items: center; gap: .6rem; }
        .t-card-title { text-align: center; }
        .t-card-saison { display: block; text-align: center; }
        .t-card-actions { justify-content: center; flex-wrap: wrap; }
        .t-url { flex-direction: column; align-items: stretch; text-align: center; }
        .t-url span { word-break: break-all; }
        .btn-copy { width: 100%; }
    }
    .btn-copy { font-size: .72rem; font-family: inherit; padding: .22rem .6rem; background: var(--primary-color); color: var(--secondary-color); border: none; border-radius: 8px; cursor: pointer; font-weight: 600; white-space: nowrap; transition: background .2s; flex-shrink: 0; }
    .btn-copy:hover { background: #9ab89a; }
    .btn-view { font-size: .78rem; color: var(--secondary-color); text-decoration: none; padding: .28rem .65rem; border: 1.5px solid var(--primary-color); border-radius: 10px; font-weight: 600; transition: background .2s; }
    .btn-view:hover { background: #eef4ee; }
    .btn-edit-t { font-size: .78rem; padding: .28rem .6rem; border: 1px solid #a8c4d4; background: #fff; color: #1565c0; border-radius: 10px; cursor: pointer; font-family: inherit; transition: background .2s; }
    .btn-edit-t:hover { background: #e3f2fd; }
    .btn-del-t { font-size: .78rem; padding: .28rem .6rem; border: 1px solid #e0a0a0; background: #fff; color: #c0392b; border-radius: 10px; cursor: pointer; font-family: inherit; transition: background .2s; }
    .btn-del-t:hover { background: #fdecea; }

    /* Modale */
    .t-modal { display: none; position: fixed; inset: 0; z-index: 1100; background: rgba(0,0,0,.52); align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box; }
    .t-modal.open { display: flex; }
    .t-modal-box { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; max-height: 92vh; overflow-y: auto; animation: fadeup .2s; }
    @keyframes fadeup { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }
    .t-modal-head { background: var(--secondary-color); color: #fff; border-radius: 14px 14px 0 0; padding: 1rem 1.4rem; display: flex; align-items: center; justify-content: space-between; }
    .t-modal-head h3 { margin: 0; font-size: 1rem; }
    .t-modal-close { font-size: 1.6rem; cursor: pointer; line-height: 1; color: rgba(255,255,255,.8); }
    .t-modal-close:hover { color: #fff; }
    .t-modal-body { padding: 1.4rem 1.5rem; }
    .t-form-row { margin-bottom: .95rem; }
    .t-form-row label { display: block; font-size: .85rem; font-weight: 600; color: var(--bg-dark); margin-bottom: .3rem; }
    .t-form-row input, .t-form-row textarea { width: 100%; padding: .5rem .75rem; border: 1.5px solid #d0dbd0; border-radius: 8px; font-size: .87rem; font-family: inherit; background: #fafafa; box-sizing: border-box; transition: border-color .2s; }
    .t-form-row input:focus, .t-form-row textarea:focus { outline: none; border-color: var(--secondary-color); background: #fff; }
    .t-form-row textarea { resize: vertical; }
    .t-form-hint { font-size: .78rem; color: #888; margin-top: .25rem; }
    .t-modal-footer { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.2rem; }
    .btn-t-save { background: var(--secondary-color); color: #fff; border: none; border-radius: 8px; padding: .55rem 1.5rem; font-weight: 600; font-family: inherit; font-size: .9rem; cursor: pointer; transition: background .2s; }
    .btn-t-save:hover { background: var(--bg-dark); }
    .btn-t-cancel { background: #eee; color: #444; border: none; border-radius: 8px; padding: .55rem 1.2rem; font-family: inherit; font-size: .9rem; cursor: pointer; }
    .btn-t-cancel:hover { background: #ddd; }
    .t-status { font-size: .85rem; min-height: 1.1rem; margin-top: .5rem; }
    .t-status.ok { color: #2e7d32; }
    .t-status.err { color: #c62828; }
    </style>
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Gestion des Tournois</h1>
                <p><?= count($tournois) ?> page<?= count($tournois) > 1 ? 's' : '' ?> créée<?= count($tournois) > 1 ? 's' : '' ?></p>
            </div>
        </div>
    </div>

    <?php if (has_role('admin')): ?>
    <a href="/pages/admin/index.php" class="back-btn">← Administration</a>
    <?php else: ?>
    <a href="/pages/moderateur/index.php" class="back-btn">← Modération</a>
    <?php endif; ?>

    <div class="t-container">

        <div class="t-toolbar">
            <h2>Toutes les pages</h2>
            <button class="btn-add-t" onclick="openModal()">＋ Créer une page</button>
        </div>

        <?php if (empty($tournois)): ?>
        <p class="t-empty">Aucune page tournoi. Créez-en une pour pouvoir la lier à un événement.</p>
        <?php else: ?>
        <div class="t-list">
            <?php foreach ($tournois as $t): ?>
            <?php $url = '/pages/evenements/tournoi.php?id=' . $t['id']; ?>
            <div class="t-card" data-t="<?= h(json_encode($t, JSON_UNESCAPED_UNICODE)) ?>">
                <div class="t-card-top">
                    <div>
                        <p class="t-card-title"><?= h($t['titre']) ?></p>
                        <?php if ($t['saison']): ?>
                        <span class="t-card-saison">Saison <?= h($t['saison']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="t-card-actions">
                        <a href="<?= h($url) ?>" class="btn-view">Voir →</a>
                        <button class="btn-edit-t" onclick="openModal(this.closest('.t-card'))">✏ Modifier</button>
                        <button class="btn-del-t" onclick="deleteTournoi(<?= $t['id'] ?>, this.closest('.t-card'))">🗑</button>
                    </div>
                </div>
                <div class="t-url">
                    <span><?= h($url) ?></span>
                    <button class="btn-copy" onclick="copyUrl('<?= h($url) ?>', this)">Copier</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Modale ajout / édition ──────────────────────────────── -->
    <div id="tModal" class="t-modal" onclick="if(event.target===this)closeModal()">
        <div class="t-modal-box">
            <div class="t-modal-head">
                <h3 id="tModalTitle">Créer une page tournoi</h3>
                <span class="t-modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div class="t-modal-body">
                <input type="hidden" id="t-id">
                <div class="t-form-row">
                    <label>Titre *</label>
                    <input type="text" id="t-titre" placeholder="Ex : Tournoi de préparation 2026-2027">
                </div>
                <div class="t-form-row">
                    <label>Saison</label>
                    <input type="text" id="t-saison" placeholder="Ex : 2026-2027">
                </div>
                <div class="t-form-row">
                    <label>Description</label>
                    <textarea id="t-description" rows="3" placeholder="Présentation du tournoi, informations pratiques…"></textarea>
                </div>
                <div class="t-form-row">
                    <label>Lien d'inscription</label>
                    <input type="url" id="t-inscription" placeholder="https://www.helloasso.com/…">
                    <p class="t-form-hint">Entrez <strong>l'URL seule</strong>, pas le code iframe. Ex&nbsp;: <code>https://www.helloasso.com/…/widget-vignette</code></p>
                </div>
                <div class="t-form-row">
                    <label>Tableau Google Sheet (scores)</label>
                    <input type="url" id="t-sheet" placeholder="https://docs.google.com/spreadsheets/…/pubhtml">
                    <p class="t-form-hint">Fichier → Partager → Publier sur le web → format pubhtml.</p>
                </div>
                <div class="t-modal-footer">
                    <button class="btn-t-cancel" onclick="closeModal()">Annuler</button>
                    <button class="btn-t-save" id="tSaveBtn" onclick="saveTournoi()">Enregistrer</button>
                </div>
                <p class="t-status" id="tStatus"></p>
            </div>
        </div>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script>

    function openModal(cardEl) {
        document.getElementById('tModalTitle').textContent = cardEl ? 'Modifier la page' : 'Créer une page tournoi';
        document.getElementById('tStatus').textContent = '';
        document.getElementById('tStatus').className   = 't-status';
        if (cardEl) {
            const t = JSON.parse(cardEl.dataset.t);
            document.getElementById('t-id').value          = t.id;
            document.getElementById('t-titre').value       = t.titre       || '';
            document.getElementById('t-saison').value      = t.saison      || '';
            document.getElementById('t-description').value = t.description || '';
            document.getElementById('t-inscription').value = t.inscription_url || '';
            document.getElementById('t-sheet').value       = t.sheet_url   || '';
        } else {
            document.getElementById('t-id').value          = '';
            document.getElementById('t-titre').value       = '';
            document.getElementById('t-saison').value      = '';
            document.getElementById('t-description').value = '';
            document.getElementById('t-inscription').value = '';
            document.getElementById('t-sheet').value       = '';
        }
        document.getElementById('tModal').classList.add('open');
        document.body.style.overflow = 'hidden';
        document.getElementById('t-titre').focus();
    }

    function closeModal() {
        document.getElementById('tModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    async function saveTournoi() {
        const statusEl = document.getElementById('tStatus');
        const saveBtn  = document.getElementById('tSaveBtn');
        const titre    = document.getElementById('t-titre').value.trim();
        if (!titre) { statusEl.textContent = 'Le titre est requis.'; statusEl.className = 't-status err'; return; }

        saveBtn.disabled    = true;
        saveBtn.textContent = 'Enregistrement…';
        statusEl.textContent = '';

        const fd = new FormData();
        fd.set('id',              document.getElementById('t-id').value);
        fd.set('titre',           titre);
        fd.set('saison',          document.getElementById('t-saison').value.trim());
        fd.set('description',     document.getElementById('t-description').value.trim());
        fd.set('inscription_url', document.getElementById('t-inscription').value.trim());
        fd.set('sheet_url',       document.getElementById('t-sheet').value.trim());

        try {
            const res  = await fetch('/php/tournois/save.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Erreur inconnue');
            location.reload();
        } catch (err) {
            statusEl.textContent = err.message;
            statusEl.className   = 't-status err';
        } finally {
            saveBtn.disabled    = false;
            saveBtn.textContent = 'Enregistrer';
        }
    }

    async function deleteTournoi(id, cardEl) {
        if (!confirm('Supprimer cette page tournoi ? Les événements qui y sont liés perdront leur lien.')) return;
        const fd = new FormData();
        fd.set('id', id);
        try {
            const res  = await fetch('/php/tournois/delete.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) cardEl.remove();
            else alert(json.error || 'Erreur');
        } catch { alert('Erreur réseau'); }
    }

    function copyUrl(url, btn) {
        navigator.clipboard.writeText(url).then(() => {
            const orig = btn.textContent;
            btn.textContent = '✓ Copié';
            setTimeout(() => btn.textContent = orig, 1800);
        });
    }
    </script>
</body>
</html>
