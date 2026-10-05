<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_any_role(['admin', 'moderateur'])) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
require_once __DIR__ . '/../../php/newsletter/lib.php';

$pdo = get_pdo();
$backUrl = has_role('admin') ? '/pages/admin/index.php' : '/pages/moderateur/index.php';

$nbDestinataires = 0;
foreach ($pdo->query('SELECT username FROM users WHERE actif = 1 AND newsletter = 1')->fetchAll(PDO::FETCH_COLUMN) as $mail) {
    if (filter_var($mail, FILTER_VALIDATE_EMAIL)) $nbDestinataires++;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Letter - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link href="/css/admin.css?v=20260702" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .nl-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; align-items: start; }
        @media (max-width: 860px) { .nl-grid { grid-template-columns: 1fr; } }
        .nl-field { display: flex; flex-direction: column; gap: .3rem; margin-bottom: .9rem; }
        .nl-field label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #888; }
        .nl-field input[type="text"], .nl-field input[type="url"], .nl-field textarea {
            width: 100%; padding: .55rem .7rem; border: 1.5px solid #d5ddd5; border-radius: 7px;
            font: inherit; font-size: .9rem; background: #fafafa; box-sizing: border-box;
        }
        .nl-field textarea { resize: vertical; min-height: 90px; }
        .nl-hint { font-size: .75rem; color: #999; margin: 0; }
        .nl-check { display: flex; gap: .5rem; align-items: center; font-size: .85rem; color: #444; }
        .nl-check input { width: auto; }
        .nl-sub { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--secondary-color); border-top: 1px solid #e8ece8; padding-top: .9rem; margin: .4rem 0 .8rem; }
        .nl-actions { display: flex; gap: .6rem; flex-wrap: wrap; margin-top: 1rem; }
        .nl-btn { border: none; border-radius: 8px; padding: .65rem 1.1rem; font: inherit; font-size: .88rem; font-weight: 600; cursor: pointer; }
        .nl-btn--ghost { background: #f0f0f0; color: #333; }
        .nl-btn--primary { background: var(--secondary-color); color: #fff; }
        .nl-btn:disabled { opacity: .55; cursor: wait; }
        .nl-preview { width: 100%; height: 620px; border: 1px solid #e0e8e0; border-radius: 10px; background: #eef3ee; }
        .nl-status { font-size: .85rem; font-weight: 500; min-height: 1.2em; margin: .6rem 0 0; }
        .nl-status.ok { color: #1a7a3c; }
        .nl-status.err { color: #c0392b; }
        .nl-count { font-size: .9rem; color: #444; margin: 0 0 .6rem; }
        .nl-count strong { color: var(--secondary-color); }
        .nl-image-row { display: flex; gap: .5rem; }
        .nl-image-row input { flex: 1; }
        .nl-image-thumb { width: 120px; height: auto; border-radius: 8px; border: 1px solid #e0e8e0; }
        .nl-picker { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 1100; display: none; align-items: center; justify-content: center; padding: 1rem; }
        .nl-picker.open { display: flex; }
        .nl-picker-box { background: #fff; border-radius: 12px; width: 100%; max-width: 760px; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; }
        .nl-picker-head { display: flex; justify-content: space-between; align-items: center; padding: .9rem 1.2rem; background: var(--secondary-color); color: #fff; }
        .nl-picker-head h3 { margin: 0; font-size: 1rem; color: #fff; }
        .nl-picker-head button { background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; line-height: 1; }
        .nl-picker-search { padding: .7rem 1.2rem 0; }
        .nl-picker-search input { width: 100%; padding: .5rem .7rem; border: 1.5px solid #d5ddd5; border-radius: 7px; font: inherit; box-sizing: border-box; }
        .nl-picker-grid { padding: 1rem 1.2rem 1.2rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: .6rem; overflow-y: auto; }
        .nl-picker-item { border: 2px solid transparent; border-radius: 8px; padding: 0; background: #f4f8f4; cursor: pointer; overflow: hidden; aspect-ratio: 4 / 3; }
        .nl-picker-item:hover, .nl-picker-item:focus-visible { border-color: var(--secondary-color); outline: none; }
        .nl-picker-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .nl-picker-empty { color: #888; font-style: italic; padding: 1rem; }
    </style>
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>News Letter</h1>
                <p>Rédigez une édition et envoyez-la aux adhérents abonnés.</p>
            </div>
        </div>
    </div>

    <a href="<?= $backUrl ?>" class="back-btn">← Retour</a>

    <div class="admin-container">

        <div class="admin-card">
            <p class="nl-count"><strong><?= $nbDestinataires ?></strong> adhérent<?= $nbDestinataires > 1 ? 's' : '' ?> abonné<?= $nbDestinataires > 1 ? 's' : '' ?> recevront cette édition.</p>
        </div>

        <div class="nl-grid">

            <div class="admin-card">
                <h2>Contenu</h2>
                <form id="nl-form">
                    <div class="nl-field">
                        <label for="nl-titre">Titre de la newsletter</label>
                        <input type="text" id="nl-titre" name="titre" maxlength="150" placeholder="Ex : Les nouvelles du VBO — octobre 2026" required>
                    </div>
                    <div class="nl-field">
                        <label for="nl-intro">Mot d'introduction</label>
                        <textarea id="nl-intro" name="intro" placeholder="Quelques mots pour introduire cette édition…"></textarea>
                        <p class="nl-hint">Un saut de ligne vide sépare deux paragraphes.</p>
                    </div>

                    <p class="nl-sub">À la une (optionnel)</p>
                    <div class="nl-field">
                        <label for="nl-une-titre">Titre</label>
                        <input type="text" id="nl-une-titre" name="une_titre" maxlength="150" placeholder="Ex : Tournoi de préparation réussi">
                    </div>
                    <div class="nl-field">
                        <label for="nl-une-texte">Texte</label>
                        <textarea id="nl-une-texte" name="une_texte" placeholder="Résumé de l'actualité…"></textarea>
                    </div>
                    <div class="nl-field">
                        <label for="nl-une-image">Image</label>
                        <div class="nl-image-row">
                            <input type="text" id="nl-une-image" name="une_image" placeholder="/photos/… ou https://…">
                            <button type="button" class="nl-btn nl-btn--ghost" id="nl-pick-btn">🖼 Galerie</button>
                            <button type="button" class="nl-btn nl-btn--ghost" id="nl-upload-btn">📁 Mon ordinateur</button>
                            <input type="file" id="nl-upload-file" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
                        </div>
                        <img id="nl-image-thumb" class="nl-image-thumb" alt="" style="display:none">
                        <p class="nl-hint" id="nl-upload-status">Choisissez une photo de la galerie, importez-en une depuis votre ordinateur, ou collez une adresse complète.</p>
                    </div>

                    <p class="nl-sub">Contenu automatique</p>
                    <label class="nl-check">
                        <input type="checkbox" name="show_events" value="1" checked>
                        Ajouter la liste des prochains événements
                    </label>

                    <div class="nl-actions">
                        <button type="button" class="nl-btn nl-btn--ghost" id="nl-preview-btn">👁 Aperçu</button>
                        <button type="button" class="nl-btn nl-btn--ghost" id="nl-test-btn">✉️ M'envoyer un test</button>
                        <button type="button" class="nl-btn nl-btn--primary" id="nl-send-btn">📨 Envoyer à <?= $nbDestinataires ?> adhérent<?= $nbDestinataires > 1 ? 's' : '' ?></button>
                    </div>
                    <p class="nl-status" id="nl-status"></p>
                </form>
            </div>

            <div class="admin-card">
                <h2>Aperçu</h2>
                <p class="nl-hint" style="margin-bottom:.6rem;">C'est ainsi que l'édition apparaîtra dans la boîte mail. Cliquez sur « Aperçu » après chaque modification.</p>
                <iframe id="nl-preview" class="nl-preview" title="Aperçu de la newsletter" srcdoc="<p style='font-family:Arial;padding:24px;color:#888'>L'aperçu apparaîtra ici.</p>"></iframe>
            </div>

        </div>
    </div>

    <div class="nl-picker" id="nl-picker" onclick="if(event.target===this)closePicker()">
        <div class="nl-picker-box">
            <div class="nl-picker-head">
                <h3>Choisir une photo de la galerie</h3>
                <button type="button" onclick="closePicker()" aria-label="Fermer">&times;</button>
            </div>
            <div class="nl-picker-search">
                <input type="search" id="nl-picker-q" placeholder="Rechercher par description…">
            </div>
            <div class="nl-picker-grid" id="nl-picker-grid"></div>
        </div>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script>
    (function () {
        const form   = document.getElementById('nl-form');
        const status = document.getElementById('nl-status');
        const btnPrev = document.getElementById('nl-preview-btn');
        const btnSend = document.getElementById('nl-send-btn');
        const frame   = document.getElementById('nl-preview');
        const nbDest  = <?= $nbDestinataires ?>;

        function setStatus(text, cls) {
            status.textContent = text;
            status.className   = 'nl-status ' + (cls || '');
        }

        btnPrev.addEventListener('click', async () => {
            if (!form.reportValidity()) return;
            btnPrev.disabled = true;
            try {
                const res  = await fetch('/php/newsletter/preview.php', { method: 'POST', body: new FormData(form) });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Erreur');
                frame.srcdoc = json.html;
                setStatus('');
            } catch (err) {
                setStatus(err.message, 'err');
            } finally {
                btnPrev.disabled = false;
            }
        });

        // ── Sélecteur d'image (galerie) ──
        const imgInput = document.getElementById('nl-une-image');
        const thumb    = document.getElementById('nl-image-thumb');
        const picker   = document.getElementById('nl-picker');
        const grid     = document.getElementById('nl-picker-grid');
        const pickerQ  = document.getElementById('nl-picker-q');
        let photos = null;

        function showThumb() {
            const v = imgInput.value.trim();
            if (!v) { thumb.style.display = 'none'; thumb.removeAttribute('src'); return; }
            thumb.src = v;
            thumb.style.display = '';
        }
        imgInput.addEventListener('input', showThumb);
        showThumb();

        function renderPhotos() {
            const q = pickerQ.value.trim().toLowerCase();
            const list = (photos || []).filter(p => !q || p.alt.toLowerCase().includes(q));
            grid.innerHTML = '';
            if (list.length === 0) {
                grid.innerHTML = '<p class="nl-picker-empty">Aucune photo trouvée.</p>';
                return;
            }
            list.forEach(p => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'nl-picker-item';
                b.title = p.alt || p.url;
                const img = document.createElement('img');
                img.src = p.url;
                img.alt = p.alt || '';
                img.loading = 'lazy';
                b.appendChild(img);
                b.addEventListener('click', () => {
                    imgInput.value = p.url;
                    showThumb();
                    closePicker();
                });
                grid.appendChild(b);
            });
        }

        async function openPicker() {
            picker.classList.add('open');
            pickerQ.value = '';
            if (photos === null) {
                grid.innerHTML = '<p class="nl-picker-empty">Chargement…</p>';
                try {
                    const res  = await fetch('/php/newsletter/images.php');
                    const json = await res.json();
                    if (!json.success) throw new Error(json.error);
                    photos = json.photos;
                } catch (err) {
                    photos = [];
                    grid.innerHTML = '<p class="nl-picker-empty">Impossible de charger la galerie.</p>';
                    return;
                }
            }
            renderPhotos();
            pickerQ.focus();
        }
        window.closePicker = function () { picker.classList.remove('open'); };
        document.getElementById('nl-pick-btn').addEventListener('click', openPicker);

        const uploadBtn   = document.getElementById('nl-upload-btn');
        const uploadFile  = document.getElementById('nl-upload-file');
        const uploadInfo  = document.getElementById('nl-upload-status');
        uploadBtn.addEventListener('click', () => uploadFile.click());
        uploadFile.addEventListener('change', async () => {
            const file = uploadFile.files[0];
            if (!file) return;
            uploadBtn.disabled = true;
            uploadInfo.textContent = 'Import de l\'image en cours…';
            try {
                const fd = new FormData();
                fd.append('image', file);
                const res  = await fetch('/php/newsletter/upload_image.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Erreur lors de l\'import');
                imgInput.value = json.url;
                showThumb();
                uploadInfo.textContent = '✓ Image importée. Elle sera incluse dans la newsletter.';
            } catch (err) {
                uploadInfo.textContent = err.message;
            } finally {
                uploadBtn.disabled = false;
                uploadFile.value = '';
            }
        });
        pickerQ.addEventListener('input', renderPhotos);

        const btnTest = document.getElementById('nl-test-btn');
        btnTest.addEventListener('click', async () => {
            if (!form.reportValidity()) return;
            btnTest.disabled = true;
            setStatus('Envoi du test…');
            try {
                const res  = await fetch('/php/newsletter/send_test.php', { method: 'POST', body: new FormData(form) });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Erreur lors de l\'envoi du test');
                setStatus('✓ Test envoyé à ' + json.email + '. Vérifiez votre boîte (et vos spams).', 'ok');
            } catch (err) {
                setStatus(err.message, 'err');
            } finally {
                btnTest.disabled = false;
            }
        });

        btnSend.addEventListener('click', async () => {
            if (!form.reportValidity()) return;
            if (nbDest === 0) { setStatus('Aucun adhérent abonné à qui envoyer.', 'err'); return; }
            if (!confirm('Envoyer cette newsletter à ' + nbDest + ' adhérent(s) ? Cette action est irréversible.')) return;
            btnSend.disabled = true;
            btnSend.textContent = 'Envoi en cours…';
            setStatus('Envoi en cours, merci de patienter…');
            try {
                const res  = await fetch('/php/newsletter/send.php', { method: 'POST', body: new FormData(form) });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Erreur lors de l\'envoi');
                setStatus('✓ Newsletter envoyée à ' + json.envoyes + ' adhérent(s)' + (json.ignores ? ' (' + json.ignores + ' non envoyé(s))' : '') + '.', 'ok');
            } catch (err) {
                setStatus(err.message, 'err');
            } finally {
                btnSend.disabled = false;
                btnSend.textContent = '📨 Envoyer à ' + nbDest + ' adhérent' + (nbDest > 1 ? 's' : '');
            }
        });
    })();
    </script>
</body>
</html>
