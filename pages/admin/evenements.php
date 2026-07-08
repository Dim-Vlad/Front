<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_any_role(['moderateur', 'admin'])) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$user    = current_user();
$backUrl = has_role('admin') ? '/pages/admin/index.php' : '/pages/moderateur/index.php';

$eventTypes = [
    'tournoi' => ['label' => 'Tournoi',  'icon' => '🏐'],
    'match'   => ['label' => 'Match',    'icon' => '⚔️'],
    'loto'    => ['label' => 'Loto',     'icon' => '🎰'],
    'tombola' => ['label' => 'Tombola',  'icon' => '🎟'],
    'stage'   => ['label' => 'Stage',    'icon' => '📚'],
    'autre'   => ['label' => 'Autre',    'icon' => '📅'],
];

function formatDate(?string $debut, ?string $fin): string {
    if (!$debut) return 'Date à confirmer';
    $mois = ['','jan.','fév.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
    $d1   = new DateTime($debut);
    $s    = intval($d1->format('j')) . ' ' . $mois[(int)$d1->format('n')] . ' ' . $d1->format('Y');
    if ($fin && $fin !== $debut) {
        $d2 = new DateTime($fin);
        $s .= ' – ' . intval($d2->format('j')) . ' ' . $mois[(int)$d2->format('n')] . ' ' . $d2->format('Y');
    }
    return $s;
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

$aVenir   = [];
$termines = [];
$tournois = [];
try {
    $pdo  = get_pdo();
    $rows = $pdo->query("SELECT * FROM evenements ORDER BY termine ASC, ordre ASC, id ASC")->fetchAll();
    foreach ($rows as $ev) {
        if ($ev['termine']) $termines[] = $ev;
        else                $aVenir[]   = $ev;
    }
    $tournois = $pdo->query("SELECT * FROM tournois ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda du club - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/evenements/evenements.css?v=20260708" rel="stylesheet">
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
                <h1>Agenda du club</h1>
                <p>Calendrier des événements et pages tournois.</p>
            </div>
        </div>
    </div>

    <a href="<?= $backUrl ?>" class="back-btn">← Retour</a>

    <main class="evenements-main">

        <!-- ══ ONGLETS ══════════════════════════════════════════════ -->
        <div class="ev-tabs">
            <button class="ev-tab active" data-tab="calendrier" onclick="switchEvTab('calendrier')">
                📅 Calendrier <span class="ev-tab-count"><?= count($aVenir) ?></span>
            </button>
            <button class="ev-tab" data-tab="tournois" onclick="switchEvTab('tournois')">
                🏆 Pages Tournois <span class="ev-tab-count"><?= count($tournois) ?></span>
            </button>
        </div>

        <!-- ══ TAB 1 : CALENDRIER ════════════════════════════════════ -->
        <div id="tab-calendrier" class="ev-tab-panel">

            <section class="ev-section">
                <div class="ev-section-header">
                    <h2>À venir</h2>
                    <button class="btn-add-ev" onclick="openAddModal()">＋ Ajouter un événement</button>
                </div>

                <?php if (empty($aVenir)): ?>
                <p class="ev-empty">Aucun événement prévu pour le moment.</p>
                <?php else: ?>
                <div class="ev-grid" id="ev-avenir-grid">
                    <?php foreach ($aVenir as $ev):
                        $t = $eventTypes[$ev['type'] ?? 'autre'] ?? $eventTypes['autre'];
                    ?>
                    <article class="ev-card" data-id="<?= $ev['id'] ?>" data-ev="<?= h(json_encode($ev, JSON_UNESCAPED_UNICODE)) ?>">
                        <div class="ev-card-actions">
                            <button class="btn-ev-order" onclick="moveEvent(<?= $ev['id'] ?>, 'up')" title="Monter">▲</button>
                            <button class="btn-ev-order" onclick="moveEvent(<?= $ev['id'] ?>, 'down')" title="Descendre">▼</button>
                            <button class="btn-ev-edit" onclick="openEditModal(this.closest('.ev-card'))" title="Modifier">✏</button>
                            <button class="btn-ev-toggle" onclick="toggleTermine(<?= $ev['id'] ?>, this)" title="Marquer comme terminé">✓ Terminé</button>
                            <button class="btn-ev-delete" onclick="deleteEvent(<?= $ev['id'] ?>)" title="Supprimer">🗑</button>
                        </div>
                        <?php if (!empty($ev['image_url'])): ?>
                        <img class="ev-img" src="<?= h($ev['image_url']) ?>" alt="<?= h($ev['titre']) ?>">
                        <?php endif; ?>
                        <span class="ev-type-badge ev-type-<?= h($ev['type'] ?? 'autre') ?>"><?= $t['icon'] ?> <?= $t['label'] ?></span>
                        <div class="ev-date"><?= h(formatDate($ev['date_debut'], $ev['date_fin'])) ?></div>
                        <h3 class="ev-title"><?= h($ev['titre']) ?></h3>
                        <?php if ($ev['description']): ?>
                        <p class="ev-desc"><?= nl2br(h($ev['description'])) ?></p>
                        <?php endif; ?>
                        <?php if ($ev['lieu']): ?>
                        <p class="ev-lieu">📍 <?= h($ev['lieu']) ?></p>
                        <?php endif; ?>
                        <?php if ($ev['lien_url']): ?>
                        <a href="<?= h($ev['lien_url']) ?>" class="ev-btn"><?= h($ev['lien_label'] ?: 'En savoir plus') ?> →</a>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <?php if (!empty($termines)): ?>
            <section class="ev-section">
                <details class="ev-past-details">
                    <summary class="ev-past-summary">
                        <span class="ev-past-label">Événements passés</span>
                        <span class="ev-past-count"><?= count($termines) ?></span>
                    </summary>
                    <div class="ev-grid ev-grid-past" id="ev-termines-grid">
                        <?php foreach ($termines as $ev):
                            $t = $eventTypes[$ev['type'] ?? 'autre'] ?? $eventTypes['autre'];
                        ?>
                        <article class="ev-card ev-card-past" data-id="<?= $ev['id'] ?>" data-ev="<?= h(json_encode($ev, JSON_UNESCAPED_UNICODE)) ?>">
                            <div class="ev-card-actions">
                                <button class="btn-ev-edit" onclick="openEditModal(this.closest('.ev-card'))" title="Modifier">✏</button>
                                <button class="btn-ev-toggle btn-ev-restore" onclick="toggleTermine(<?= $ev['id'] ?>, this)" title="Remettre à venir">↩ Restaurer</button>
                                <button class="btn-ev-delete" onclick="deleteEvent(<?= $ev['id'] ?>)" title="Supprimer">🗑</button>
                            </div>
                            <?php if (!empty($ev['image_url'])): ?>
                            <img class="ev-img" src="<?= h($ev['image_url']) ?>" alt="<?= h($ev['titre']) ?>">
                            <?php endif; ?>
                            <span class="ev-past-badge">Terminé</span>
                            <span class="ev-type-badge ev-type-<?= h($ev['type'] ?? 'autre') ?>"><?= $t['icon'] ?> <?= $t['label'] ?></span>
                            <div class="ev-date"><?= h(formatDate($ev['date_debut'], $ev['date_fin'])) ?></div>
                            <h3 class="ev-title"><?= h($ev['titre']) ?></h3>
                            <?php if ($ev['description']): ?>
                            <p class="ev-desc"><?= nl2br(h($ev['description'])) ?></p>
                            <?php endif; ?>
                            <?php if ($ev['lieu']): ?>
                            <p class="ev-lieu">📍 <?= h($ev['lieu']) ?></p>
                            <?php endif; ?>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </details>
            </section>
            <?php endif; ?>

        </div><!-- /tab-calendrier -->

        <!-- ══ TAB 2 : PAGES TOURNOIS ════════════════════════════════ -->
        <div id="tab-tournois" class="ev-tab-panel" style="display:none">

            <div class="ev-section">
                <div class="ev-section-header">
                    <h2>Pages Tournois</h2>
                    <button class="btn-add-ev" onclick="openTournoiModal()">＋ Créer une page</button>
                </div>
                <p class="ev-section-hint">Créez une page dédiée pour chaque événement : inscription par lien, téléphone ou email — tableau de scores Google Sheets en option.</p>

                <?php if (empty($tournois)): ?>
                <p class="ev-empty">Aucune page tournoi créée.</p>
                <?php else: ?>
                <div class="t-list">
                    <?php foreach ($tournois as $t): ?>
                    <?php
                        $url = '/pages/evenements/tournoi.php?id=' . $t['id'];
                        $tType = $t['type'] ?? 'tournoi';
                        $tTypeDef = $eventTypes[$tType] ?? $eventTypes['tournoi'];
                    ?>
                    <div class="t-card" data-t="<?= h(json_encode($t, JSON_UNESCAPED_UNICODE)) ?>">
                        <div class="t-card-top">
                            <div>
                                <p class="t-card-title"><?= h($t['titre']) ?></p>
                                <div class="t-card-meta">
                                    <span class="ev-type-badge ev-type-<?= h($tType) ?>"><?= $tTypeDef['icon'] ?> <?= $tTypeDef['label'] ?></span>
                                    <?php if ($t['saison']): ?>
                                    <span class="t-card-saison">Saison <?= h($t['saison']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="t-card-actions">
                                <a href="<?= h($url) ?>" target="_blank" class="btn-view">Voir →</a>
                                <button class="btn-edit-t" onclick="openTournoiModal(this.closest('.t-card'))">✏ Modifier</button>
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

        </div><!-- /tab-tournois -->

    </main>

    <!-- ══ MODALE AJOUT ÉVÉNEMENT ════════════════════════════════════ -->
    <div id="eventModal" class="ev-modal">
        <div class="ev-modal-content">
            <div class="ev-modal-header">
                <h3>Ajouter un événement</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <div class="ev-modal-body">
                <form id="event-form">
                    <div class="ev-form-row">
                        <label for="ev-type">Type *</label>
                        <select id="ev-type" name="type">
                            <?php foreach ($eventTypes as $key => $t): ?>
                            <option value="<?= $key ?>"><?= $t['icon'] ?> <?= $t['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-titre">Titre *</label>
                        <input type="text" id="ev-titre" name="titre" required placeholder="Nom de l'événement">
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-desc">Description</label>
                        <textarea id="ev-desc" name="description" rows="3" placeholder="Description courte…"></textarea>
                    </div>
                    <div class="ev-form-row ev-form-row--half">
                        <div>
                            <label for="ev-date-debut">Date début</label>
                            <input type="date" id="ev-date-debut" name="date_debut">
                        </div>
                        <div>
                            <label for="ev-date-fin">Date fin</label>
                            <input type="date" id="ev-date-fin" name="date_fin">
                        </div>
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-lieu">Lieu</label>
                        <input type="text" id="ev-lieu" name="lieu" placeholder="Gymnase, salle…">
                    </div>
                    <div class="ev-form-row ev-form-row--half">
                        <div>
                            <label for="ev-lien">Lien URL</label>
                            <input type="text" id="ev-lien" name="lien_url" placeholder="https://…">
                        </div>
                        <div>
                            <label for="ev-lien-label">Libellé du lien</label>
                            <input type="text" id="ev-lien-label" name="lien_label" placeholder="Inscription…">
                        </div>
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-image">Image</label>
                        <input type="file" id="ev-image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>
                    <div class="ev-form-row ev-form-check">
                        <label><input type="checkbox" name="termine"> Événement déjà terminé</label>
                    </div>
                    <div class="ev-modal-actions">
                        <button type="button" class="btn-cancel-modal" onclick="closeAddModal()">Annuler</button>
                        <button type="submit" class="btn-save">Ajouter</button>
                    </div>
                    <p class="modal-status" id="ev-status"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- ══ MODALE ÉDITION ÉVÉNEMENT ══════════════════════════════════ -->
    <div id="editEventModal" class="ev-modal">
        <div class="ev-modal-content">
            <div class="ev-modal-header">
                <h3>Modifier l'événement</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="ev-modal-body">
                <form id="edit-event-form">
                    <input type="hidden" id="edit-ev-id" name="id">
                    <input type="hidden" id="edit-ev-image-existing" name="image_url_existing">
                    <div class="ev-form-row">
                        <label for="edit-ev-type">Type *</label>
                        <select id="edit-ev-type" name="type">
                            <?php foreach ($eventTypes as $key => $t): ?>
                            <option value="<?= $key ?>"><?= $t['icon'] ?> <?= $t['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ev-form-row">
                        <label for="edit-ev-titre">Titre *</label>
                        <input type="text" id="edit-ev-titre" name="titre" required>
                    </div>
                    <div class="ev-form-row">
                        <label for="edit-ev-desc">Description</label>
                        <textarea id="edit-ev-desc" name="description" rows="3"></textarea>
                    </div>
                    <div class="ev-form-row ev-form-row--half">
                        <div>
                            <label for="edit-ev-date-debut">Date début</label>
                            <input type="date" id="edit-ev-date-debut" name="date_debut">
                        </div>
                        <div>
                            <label for="edit-ev-date-fin">Date fin</label>
                            <input type="date" id="edit-ev-date-fin" name="date_fin">
                        </div>
                    </div>
                    <div class="ev-form-row">
                        <label for="edit-ev-lieu">Lieu</label>
                        <input type="text" id="edit-ev-lieu" name="lieu">
                    </div>
                    <div class="ev-form-row ev-form-row--half">
                        <div>
                            <label for="edit-ev-lien">Lien URL</label>
                            <input type="text" id="edit-ev-lien" name="lien_url">
                        </div>
                        <div>
                            <label for="edit-ev-lien-label">Libellé du lien</label>
                            <input type="text" id="edit-ev-lien-label" name="lien_label">
                        </div>
                    </div>
                    <div class="ev-form-row">
                        <label>Image</label>
                        <div>
                            <div id="edit-ev-img-preview" class="ev-edit-img-preview" style="display:none">
                                <img id="edit-ev-img-thumb" src="" alt="">
                            </div>
                            <input type="file" id="edit-ev-image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>
                    </div>
                    <div class="ev-form-row ev-form-check">
                        <label><input type="checkbox" id="edit-ev-termine" name="termine"> Événement terminé</label>
                    </div>
                    <div class="ev-modal-actions">
                        <button type="button" class="btn-cancel-modal" onclick="closeEditModal()">Annuler</button>
                        <button type="submit" class="btn-save">Enregistrer</button>
                    </div>
                    <p class="modal-status" id="edit-ev-status"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- ══ MODALE TOURNOI ════════════════════════════════════════════ -->
    <div id="tModal" class="ev-modal" onclick="if(event.target===this)closeTournoiModal()">
        <div class="ev-modal-content">
            <div class="ev-modal-header">
                <h3 id="tModalTitle">Créer une page</h3>
                <span class="close" onclick="closeTournoiModal()">&times;</span>
            </div>
            <div class="ev-modal-body">
                <input type="hidden" id="t-id">
                <div class="ev-form-row">
                    <label>Type *</label>
                    <select id="t-type" onchange="updateTournoiForm()">
                        <?php foreach ($eventTypes as $key => $et): ?>
                        <option value="<?= $key ?>"><?= $et['icon'] ?> <?= $et['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ev-form-row">
                    <label>Titre *</label>
                    <input type="text" id="t-titre" placeholder="Ex : Loto du club 2026">
                </div>
                <div class="ev-form-row">
                    <label>Saison</label>
                    <input type="text" id="t-saison" placeholder="Ex : 2026-2027">
                </div>
                <div class="ev-form-row">
                    <label>Description</label>
                    <textarea id="t-description" rows="3" placeholder="Informations pratiques, programme…"></textarea>
                </div>
                <div class="ev-form-section-title">Inscription</div>
                <div class="ev-form-row">
                    <label>Lien URL</label>
                    <div>
                        <input type="text" id="t-inscription-url" placeholder="https://www.helloasso.com/…">
                        <p class="t-insc-hint">URL seule, sans le code iframe (HelloAsso, Eventbrite…)</p>
                    </div>
                </div>
                <div class="ev-form-row">
                    <label>Téléphone</label>
                    <input type="tel" id="t-inscription-tel" placeholder="06 12 34 56 78">
                </div>
                <div class="ev-form-row">
                    <label>Email</label>
                    <input type="email" id="t-inscription-email" placeholder="contact@monclub.fr">
                </div>
                <div class="ev-form-section-title" id="t-scores-title">Tableau de scores</div>
                <div class="ev-form-row" id="t-scores-row">
                    <label>Google Sheet</label>
                    <div>
                        <input type="text" id="t-sheet" placeholder="https://docs.google.com/spreadsheets/…/pubhtml">
                        <p class="t-insc-hint">Fichier → Partager → Publier sur le web → pubhtml.</p>
                    </div>
                </div>
                <div class="ev-modal-actions">
                    <button type="button" class="btn-cancel-modal" onclick="closeTournoiModal()">Annuler</button>
                    <button class="btn-save" id="tSaveBtn" onclick="saveTournoi()">Enregistrer</button>
                </div>
                <p class="modal-status" id="tStatus"></p>
            </div>
        </div>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script src="/js/evenements.js?v=20260708"></script>
    <script>
    const CSRF_TOKEN = '<?= csrf_token() ?>';

    // ── Onglets ──────────────────────────────────────────────────────
    function switchEvTab(name) {
        document.querySelectorAll('.ev-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.ev-tab-panel').forEach(p => p.style.display = 'none');
        document.querySelector(`.ev-tab[data-tab="${name}"]`).classList.add('active');
        document.getElementById('tab-' + name).style.display = '';
        sessionStorage.setItem('ev-tab', name);
    }
    (function() {
        const saved = sessionStorage.getItem('ev-tab');
        if (saved && saved !== 'calendrier') switchEvTab(saved);
    })();

    // ── Pages Tournois ───────────────────────────────────────────────
    const TYPES_WITH_SCORES = new Set(['tournoi', 'match', 'stage', 'autre']);

    function updateTournoiForm() {
        const show = TYPES_WITH_SCORES.has(document.getElementById('t-type').value);
        document.getElementById('t-scores-title').style.display = show ? '' : 'none';
        document.getElementById('t-scores-row').style.display   = show ? '' : 'none';
    }

    function openTournoiModal(cardEl) {
        document.getElementById('tModalTitle').textContent = cardEl ? 'Modifier la page' : 'Créer une page';
        document.getElementById('tStatus').textContent     = '';
        document.getElementById('tStatus').className       = 'modal-status';
        if (cardEl) {
            const t = JSON.parse(cardEl.dataset.t);
            document.getElementById('t-id').value               = t.id;
            document.getElementById('t-type').value             = t.type            || 'tournoi';
            document.getElementById('t-titre').value            = t.titre           || '';
            document.getElementById('t-saison').value           = t.saison          || '';
            document.getElementById('t-description').value      = t.description     || '';
            document.getElementById('t-inscription-url').value  = t.inscription_url || t.inscription_contact || '';
            document.getElementById('t-inscription-tel').value  = t.inscription_tel  || '';
            document.getElementById('t-inscription-email').value = t.inscription_email || '';
            document.getElementById('t-sheet').value            = t.sheet_url       || '';
        } else {
            ['t-id','t-titre','t-saison','t-description',
             't-inscription-url','t-inscription-tel','t-inscription-email','t-sheet']
                .forEach(id => document.getElementById(id).value = '');
            document.getElementById('t-type').value = 'tournoi';
        }
        updateTournoiForm();
        document.getElementById('tModal').classList.add('open');
        document.body.style.overflow = 'hidden';
        document.getElementById('t-titre').focus();
    }
    function closeTournoiModal() {
        document.getElementById('tModal').classList.remove('open');
        document.body.style.overflow = '';
    }
    async function saveTournoi() {
        const statusEl = document.getElementById('tStatus');
        const saveBtn  = document.getElementById('tSaveBtn');
        const titre    = document.getElementById('t-titre').value.trim();
        const inscUrl  = document.getElementById('t-inscription-url').value.trim();
        if (!titre) { statusEl.textContent = 'Le titre est requis.'; statusEl.className = 'modal-status error'; return; }
        if (inscUrl && !inscUrl.match(/^https?:\/\//i)) {
            statusEl.textContent = 'Le lien URL doit commencer par http:// ou https://';
            statusEl.className   = 'modal-status error'; return;
        }
        saveBtn.disabled = true; saveBtn.textContent = 'Enregistrement…';
        statusEl.textContent = '';
        const type = document.getElementById('t-type').value;
        const fd = new FormData();
        fd.set('_csrf',             CSRF_TOKEN);
        fd.set('id',                document.getElementById('t-id').value);
        fd.set('type',              type);
        fd.set('titre',             titre);
        fd.set('saison',            document.getElementById('t-saison').value.trim());
        fd.set('description',       document.getElementById('t-description').value.trim());
        fd.set('inscription_url',   inscUrl);
        fd.set('inscription_tel',   document.getElementById('t-inscription-tel').value.trim());
        fd.set('inscription_email', document.getElementById('t-inscription-email').value.trim());
        fd.set('sheet_url',         TYPES_WITH_SCORES.has(type) ? document.getElementById('t-sheet').value.trim() : '');
        try {
            const res  = await fetch('/php/tournois/save.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Erreur inconnue');
            location.reload();
        } catch (err) {
            statusEl.textContent = err.message;
            statusEl.className   = 'modal-status error';
        } finally {
            saveBtn.disabled = false; saveBtn.textContent = 'Enregistrer';
        }
    }
    async function deleteTournoi(id, cardEl) {
        if (!confirm('Supprimer cette page tournoi ?')) return;
        const fd = new FormData(); fd.set('_csrf', CSRF_TOKEN); fd.set('id', id);
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
