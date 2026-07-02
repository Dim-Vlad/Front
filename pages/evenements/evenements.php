<?php
require_once __DIR__ . '/../../php/auth.php';

$canEdit = is_logged_in() && has_any_role(['moderateur','admin']);

$aVenir  = [];
$termines = [];

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

try {
    $pdo = get_pdo();
    $rows = $pdo->query("SELECT * FROM evenements ORDER BY termine ASC, ordre ASC, id ASC")->fetchAll();
    foreach ($rows as $ev) {
        if ($ev['termine']) $termines[] = $ev;
        else                $aVenir[]   = $ev;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évènements - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/evenements/evenements.css?v=20260624" rel="stylesheet">
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
                <h1>Évènements</h1>
                <p>Tous les évènements organisés par le VBO.<br>Tournois, stages, loto, paëlla…</p>
            </div>
        </div>
    </div>

    <main class="evenements-main">

        <!-- ══ ÉVÉNEMENTS À VENIR ══════════════════════════════════════ -->
        <section class="ev-section">
            <div class="ev-section-header">
                <h2>À venir</h2>
                <?php if ($canEdit): ?>
                <button class="btn-add-ev" onclick="openAddModal()">＋ Ajouter</button>
                <?php endif; ?>
            </div>

            <?php if (empty($aVenir)): ?>
            <p class="ev-empty">Aucun événement prévu pour le moment.</p>
            <?php else: ?>
            <div class="ev-grid" id="ev-avenir-grid">
                <?php foreach ($aVenir as $ev): ?>
                <article class="ev-card" data-id="<?= $ev['id'] ?>"<?= $canEdit ? ' data-ev="'.h(json_encode($ev, JSON_UNESCAPED_UNICODE)).'"' : '' ?>>
                    <?php if ($canEdit): ?>
                    <div class="ev-card-actions">
                        <button class="btn-ev-order" onclick="moveEvent(<?= $ev['id'] ?>, 'up')" title="Monter">▲</button>
                        <button class="btn-ev-order" onclick="moveEvent(<?= $ev['id'] ?>, 'down')" title="Descendre">▼</button>
                        <button class="btn-ev-edit" onclick="openEditModal(this.closest('.ev-card'))" title="Modifier">✏</button>
                        <button class="btn-ev-toggle" onclick="toggleTermine(<?= $ev['id'] ?>, this)" title="Marquer comme terminé">✓ Terminé</button>
                        <button class="btn-ev-delete" onclick="deleteEvent(<?= $ev['id'] ?>)" title="Supprimer">🗑</button>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($ev['image_url'])): ?>
                    <img class="ev-img" src="<?= h($ev['image_url']) ?>" alt="<?= h($ev['titre']) ?>">
                    <?php endif; ?>
                    <div class="ev-date"><?= h(formatDate($ev['date_debut'], $ev['date_fin'])) ?></div>
                    <h3 class="ev-title"><?= h($ev['titre']) ?></h3>
                    <?php if ($ev['description']): ?>
                    <p class="ev-desc"><?= nl2br(h($ev['description'])) ?></p>
                    <?php endif; ?>
                    <?php if ($ev['lieu']): ?>
                    <p class="ev-lieu">📍 <?= h($ev['lieu']) ?></p>
                    <?php endif; ?>
                    <?php if ($ev['lien_url']): ?>
                    <a href="<?= h($ev['lien_url']) ?>" class="ev-btn" target="_blank" rel="noopener">
                        <?= h($ev['lien_label'] ?: 'En savoir plus') ?> →
                    </a>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- ══ ÉVÉNEMENTS PASSÉS ════════════════════════════════════════ -->
        <?php if (!empty($termines)): ?>
        <section class="ev-section">
            <details class="ev-past-details">
                <summary class="ev-past-summary">
                    <span class="ev-past-label">Événements passés</span>
                    <span class="ev-past-count"><?= count($termines) ?></span>
                </summary>
                <div class="ev-grid ev-grid-past" id="ev-termines-grid">
                    <?php foreach ($termines as $ev): ?>
                    <article class="ev-card ev-card-past" data-id="<?= $ev['id'] ?>"<?= $canEdit ? ' data-ev="'.h(json_encode($ev, JSON_UNESCAPED_UNICODE)).'"' : '' ?>>
                        <?php if ($canEdit): ?>
                        <div class="ev-card-actions">
                            <button class="btn-ev-edit" onclick="openEditModal(this.closest('.ev-card'))" title="Modifier">✏</button>
                            <button class="btn-ev-toggle btn-ev-restore" onclick="toggleTermine(<?= $ev['id'] ?>, this)" title="Remettre à venir">↩ Restaurer</button>
                            <button class="btn-ev-delete" onclick="deleteEvent(<?= $ev['id'] ?>)" title="Supprimer">🗑</button>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($ev['image_url'])): ?>
                        <img class="ev-img" src="<?= h($ev['image_url']) ?>" alt="<?= h($ev['titre']) ?>">
                        <?php endif; ?>
                        <span class="ev-past-badge">Terminé</span>
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

    </main>

    <?php if ($canEdit): ?>
    <!-- ══ MODALE AJOUT ÉVÉNEMENT ══════════════════════════════════ -->
    <div id="eventModal" class="ev-modal">
        <div class="ev-modal-content">
            <div class="ev-modal-header">
                <h3>Ajouter un événement</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <div class="ev-modal-body">
                <form id="event-form">
                    <div class="ev-form-row">
                        <label for="ev-titre">Titre *</label>
                        <input type="text" id="ev-titre" name="titre" required placeholder="Nom de l'événement">
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-desc">Description</label>
                        <textarea id="ev-desc" name="description" rows="3" placeholder="Description courte…"></textarea>
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-date-debut">Date début</label>
                        <input type="date" id="ev-date-debut" name="date_debut">
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-date-fin">Date fin</label>
                        <input type="date" id="ev-date-fin" name="date_fin">
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-lieu">Lieu</label>
                        <input type="text" id="ev-lieu" name="lieu" placeholder="Gymnase, salle…">
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-lien">Lien</label>
                        <input type="text" id="ev-lien" name="lien_url" placeholder="https://…">
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-lien-label">Libellé lien</label>
                        <input type="text" id="ev-lien-label" name="lien_label" placeholder="Inscription, En savoir plus…">
                    </div>
                    <div class="ev-form-row">
                        <label for="ev-image">Image</label>
                        <input type="file" id="ev-image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>
                    <div class="ev-form-row ev-form-check">
                        <label>
                            <input type="checkbox" name="termine">
                            Événement déjà terminé
                        </label>
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

    <!-- ══ MODALE ÉDITION ÉVÉNEMENT ════════════════════════════════ -->
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
                        <label for="edit-ev-titre">Titre *</label>
                        <input type="text" id="edit-ev-titre" name="titre" required>
                    </div>
                    <div class="ev-form-row">
                        <label for="edit-ev-desc">Description</label>
                        <textarea id="edit-ev-desc" name="description" rows="3"></textarea>
                    </div>
                    <div class="ev-form-row">
                        <label for="edit-ev-date-debut">Date début</label>
                        <input type="date" id="edit-ev-date-debut" name="date_debut">
                    </div>
                    <div class="ev-form-row">
                        <label for="edit-ev-date-fin">Date fin</label>
                        <input type="date" id="edit-ev-date-fin" name="date_fin">
                    </div>
                    <div class="ev-form-row">
                        <label for="edit-ev-lieu">Lieu</label>
                        <input type="text" id="edit-ev-lieu" name="lieu">
                    </div>
                    <div class="ev-form-row">
                        <label for="edit-ev-lien">Lien</label>
                        <input type="text" id="edit-ev-lien" name="lien_url">
                    </div>
                    <div class="ev-form-row">
                        <label for="edit-ev-lien-label">Libellé lien</label>
                        <input type="text" id="edit-ev-lien-label" name="lien_label">
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
                        <label>
                            <input type="checkbox" id="edit-ev-termine" name="termine">
                            Événement terminé
                        </label>
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
    <?php endif; ?>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script src="/js/evenements.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.php', 'footer');
    </script>
</body>
</html>
