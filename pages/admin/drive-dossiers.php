<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('admin')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$pdo      = get_pdo();
$dossiers = $pdo->query("SELECT id, nom, url FROM drive_dossiers ORDER BY ordre ASC, id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dossiers Bureau - Admin VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link href="/css/admin/drive-dossiers.css?v=20260624" rel="stylesheet">
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
                <h1>Dossiers Bureau</h1>
                <p>Gérer les dossiers Google Drive accessibles au bureau.</p>
            </div>
        </div>
    </div>

    <div class="dd-container">

        <!-- Liste des dossiers -->
        <div class="dd-list" id="dd-list">
            <?php if (empty($dossiers)): ?>
            <p class="dd-empty">Aucun dossier pour l'instant.</p>
            <?php else: ?>
            <?php foreach ($dossiers as $d): ?>
            <div class="dd-item" data-id="<?= $d['id'] ?>">
                <div class="dd-item-info">
                    <strong><?= htmlspecialchars($d['nom']) ?></strong>
                    <span class="dd-url"><?= htmlspecialchars($d['url']) ?></span>
                </div>
                <div class="dd-item-actions">
                    <button class="dd-btn dd-btn--order" onclick="moveDossier(<?= $d['id'] ?>, 'up')" title="Monter">▲</button>
                    <button class="dd-btn dd-btn--order" onclick="moveDossier(<?= $d['id'] ?>, 'down')" title="Descendre">▼</button>
                    <button class="dd-btn dd-btn--edit" onclick="openEdit(<?= $d['id'] ?>, <?= htmlspecialchars(json_encode($d['nom']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($d['url']), ENT_QUOTES) ?>)">Modifier</button>
                    <button class="dd-btn dd-btn--delete" onclick="deleteDossier(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['nom'])) ?>')">Supprimer</button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulaire d'ajout -->
        <div class="dd-form-card">
            <h2>Ajouter un dossier</h2>
            <div id="dd-msg" class="dd-msg" style="display:none"></div>
            <form id="dd-form" onsubmit="addDossier(event)">
                <label for="dd-nom">Nom du dossier</label>
                <input type="text" id="dd-nom" name="nom" placeholder="Ex : Comptabilité 2025-2026" required>

                <label for="dd-url">Lien de partage Google Drive</label>
                <input type="url" id="dd-url" name="url"
                       placeholder="https://drive.google.com/drive/folders/..." required>
                <small>Colle le lien de partage du dossier Drive (accès "toute personne avec le lien").</small>

                <button type="submit" class="dd-btn-submit">Ajouter</button>
            </form>
        </div>

        <!-- Modal édition -->
        <div id="edit-modal" class="dd-modal" style="display:none" onclick="closeEdit(event)">
            <div class="dd-modal-inner">
                <h2>Modifier le dossier</h2>
                <div id="edit-msg" class="dd-msg" style="display:none"></div>
                <form id="edit-form" onsubmit="saveEdit(event)">
                    <input type="hidden" id="edit-id" name="id">
                    <label for="edit-nom">Nom du dossier</label>
                    <input type="text" id="edit-nom" name="nom" required>
                    <label for="edit-url">Lien de partage Google Drive</label>
                    <input type="url" id="edit-url" name="url" required>
                    <small>Colle le lien de partage du dossier Drive.</small>
                    <div class="dd-modal-actions">
                        <button type="button" class="dd-btn-cancel" onclick="closeEdit()">Annuler</button>
                        <button type="submit" class="dd-btn-submit">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="dd-nav">
            <a href="/pages/bureau/drive.php" class="back-btn">← Voir la page Bureau</a>
            <a href="/pages/admin/index.php" class="back-btn">← Retour admin</a>
        </div>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.php', 'footer');

        async function addDossier(e) {
            e.preventDefault();
            const form = e.target;
            const msg  = document.getElementById('dd-msg');
            const fd   = new FormData(form);
            const res  = await fetch('/php/drive/add_dossier.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                msg.textContent = 'Dossier ajouté.';
                msg.className = 'dd-msg dd-msg--ok';
                msg.style.display = 'block';
                form.reset();
                setTimeout(() => location.reload(), 800);
            } else {
                msg.textContent = data.error || 'Erreur.';
                msg.className = 'dd-msg dd-msg--err';
                msg.style.display = 'block';
            }
        }

        async function deleteDossier(id, nom) {
            if (!confirm(`Supprimer le dossier « ${nom} » ?`)) return;
            const fd = new FormData();
            fd.append('id', id);
            const res  = await fetch('/php/drive/delete_dossier.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Erreur.');
        }

        function openEdit(id, nom, url) {
            document.getElementById('edit-id').value  = id;
            document.getElementById('edit-nom').value = nom;
            document.getElementById('edit-url').value = url;
            document.getElementById('edit-msg').style.display = 'none';
            document.getElementById('edit-modal').style.display = 'flex';
        }

        function closeEdit(e) {
            if (e && e.target !== document.getElementById('edit-modal')) return;
            document.getElementById('edit-modal').style.display = 'none';
        }

        async function saveEdit(e) {
            e.preventDefault();
            const msg = document.getElementById('edit-msg');
            const fd  = new FormData(e.target);
            const res  = await fetch('/php/drive/update_dossier.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                msg.textContent = 'Modifié avec succès.';
                msg.className = 'dd-msg dd-msg--ok';
                msg.style.display = 'block';
                setTimeout(() => location.reload(), 700);
            } else {
                msg.textContent = data.error || 'Erreur.';
                msg.className = 'dd-msg dd-msg--err';
                msg.style.display = 'block';
            }
        }

        async function moveDossier(id, direction) {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('direction', direction);
            const res  = await fetch('/php/drive/reorder_dossier.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) location.reload();
        }
    </script>
</body>
</html>
