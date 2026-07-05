<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_any_role(['admin', 'moderateur'])) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$pdo = get_pdo();
$now = date('Y-m-d H:i:s');

$matchs = $pdo->query(
    'SELECT m.*, u.prenom, u.nom,
            (SELECT COUNT(*) FROM pronostics_votes v WHERE v.match_id = m.id) AS nb_votes
    FROM pronostics_matchs m
    LEFT JOIN users u ON u.id = m.created_by
    ORDER BY m.date_match DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$aVenir     = array_filter($matchs, fn($m) => $m['date_match'] > $now && $m['resultat_victoire'] === null);
$sansResult = array_filter($matchs, fn($m) => $m['date_match'] <= $now && $m['resultat_victoire'] === null);
$termines   = array_filter($matchs, fn($m) => $m['resultat_victoire'] !== null);

function fmt_date(string $d): string {
    $ts = strtotime($d);
    $jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
    $mois  = ['','jan.','fév.','mar.','avr.','mai','juin','juil.','aoû.','sep.','oct.','nov.','déc.'];
    return $jours[date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $mois[(int)date('n', $ts)] . ' ' . date('Y', $ts) . ' à ' . date('H\hi', $ts);
}

function match_titre(string $adversaire, int $domicile): string {
    $adv = htmlspecialchars($adversaire);
    return $domicile ? 'VBO vs ' . $adv : $adv . ' vs VBO';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les pronostics - VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link href="/css/pronostics.css?v=20260702" rel="stylesheet">
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
                <h1>Gérer les pronostics</h1>
                <p>Créez des matchs et saisissez les résultats.</p>
            </div>
        </div>
    </div>

    <?php if (has_role('admin')): ?>
    <a href="/pages/admin/index.php" class="back-btn">← Administration</a>
    <?php else: ?>
    <a href="/pages/moderateur/index.php" class="back-btn">← Modération</a>
    <?php endif; ?>

    <div class="admin-prono-container">

        <!-- Créer un match -->
        <div class="admin-prono-form">
            <h2>➕ Créer un match</h2>
            <div id="create-error" class="login-error" style="display:none"></div>
            <div class="admin-prono-grid">
                <div class="form-group">
                    <label for="adversaire">Adversaire <span class="form-hint">*</span></label>
                    <input type="text" id="adversaire" placeholder="Ex : Toulon VB" required>
                </div>
                <div class="form-group">
                    <label for="date_match">Date et heure <span class="form-hint">*</span></label>
                    <input type="datetime-local" id="date_match" required>
                </div>
                <div class="form-group">
                    <label for="competition">Compétition</label>
                    <input type="text" id="competition" placeholder="Ex : Régionale 1">
                </div>
                <label class="admin-prono-check">
                    <input type="checkbox" id="domicile" checked>
                    Match à domicile
                </label>
                <button class="admin-prono-submit" onclick="createMatch()">Créer le match</button>
            </div>
        </div>

        <!-- Matchs à venir -->
        <p class="prono-section-title">📅 Matchs à venir (<?= count($aVenir) ?>)</p>
        <?php if (empty($aVenir)): ?>
            <p class="prono-empty">Aucun match à venir.</p>
        <?php else: foreach ($aVenir as $m):
            $dateLocal = substr($m['date_match'], 0, 16); // "YYYY-MM-DD HH:MM" → "YYYY-MM-DDTHH:MM"
            $dateLocal = str_replace(' ', 'T', $dateLocal);
        ?>
        <div class="admin-match-item" id="admin-match-<?= $m['id'] ?>">
            <div class="admin-match-info">
                <div class="admin-match-title" id="title-<?= $m['id'] ?>"><?= match_titre($m['adversaire'], (int)$m['domicile']) ?></div>
                <div class="admin-match-meta" id="meta-<?= $m['id'] ?>">
                    <?= fmt_date($m['date_match']) ?>
                    <?php if ($m['competition']): ?> · <?= htmlspecialchars($m['competition']) ?><?php endif; ?>
                    · <?= $m['nb_votes'] ?> vote<?= $m['nb_votes'] > 1 ? 's' : '' ?>
                </div>
            </div>
            <div class="admin-match-actions">
                <button class="btn-sm btn-sm--edit" onclick="toggleEditForm(<?= $m['id'] ?>)">✏️ Modifier</button>
                <button class="btn-sm btn-sm--delete" onclick="deleteMatch(<?= $m['id'] ?>, '<?= htmlspecialchars($m['adversaire'], ENT_QUOTES) ?>', <?= (int)$m['domicile'] ?>)">🗑 Supprimer</button>
            </div>
            <div class="result-form-inline" id="edit-form-<?= $m['id'] ?>">
                    <p class="result-form-inline-title" style="color:var(--secondary-color)">✏️ Modifier le match</p>
                    <div class="result-form-fields" style="flex-wrap:wrap;gap:14px">
                        <div class="result-form-group" style="flex:1;min-width:160px">
                            <label for="e-adv-<?= $m['id'] ?>">Adversaire</label>
                            <input type="text" id="e-adv-<?= $m['id'] ?>" value="<?= htmlspecialchars($m['adversaire']) ?>" placeholder="Ex : Toulon VB">
                        </div>
                        <div class="result-form-group" style="flex:1;min-width:180px">
                            <label for="e-date-<?= $m['id'] ?>">Date et heure</label>
                            <input type="datetime-local" id="e-date-<?= $m['id'] ?>" value="<?= $dateLocal ?>">
                        </div>
                        <div class="result-form-group" style="flex:1;min-width:160px">
                            <label for="e-comp-<?= $m['id'] ?>">Compétition</label>
                            <input type="text" id="e-comp-<?= $m['id'] ?>" value="<?= htmlspecialchars($m['competition'] ?? '') ?>" placeholder="Ex : Régionale 1">
                        </div>
                        <div class="result-form-group" style="justify-content:flex-end">
                            <label style="opacity:0;user-select:none">.</label>
                            <label style="display:flex;align-items:center;gap:7px;font-size:.85rem;font-weight:500;cursor:pointer;text-transform:none;letter-spacing:0;color:#444">
                                <input type="checkbox" id="e-dom-<?= $m['id'] ?>" <?= $m['domicile'] ? 'checked' : '' ?> style="accent-color:var(--secondary-color);width:15px;height:15px">
                                Domicile
                            </label>
                        </div>
                    </div>
                    <div class="result-form-footer">
                        <button class="btn-sm btn-sm--result" onclick="updateMatch(<?= $m['id'] ?>)">✓ Enregistrer</button>
                        <button class="btn-sm" style="background:#f0f0f0;color:#666" onclick="toggleEditForm(<?= $m['id'] ?>)">Annuler</button>
                        <span class="result-err" id="edit-err-<?= $m['id'] ?>"></span>
                    </div>
                </div>
        </div>
        <?php endforeach; endif; ?>

        <!-- Sans résultat -->
        <p class="prono-section-title">⏳ En attente de résultat (<?= count($sansResult) ?>)</p>
        <?php if (empty($sansResult)): ?>
            <p class="prono-empty">Aucun match en attente de résultat.</p>
        <?php else: foreach ($sansResult as $m): ?>
        <div class="admin-match-item" id="admin-match-<?= $m['id'] ?>">
            <div class="admin-match-info">
                <div class="admin-match-title"><?= match_titre($m['adversaire'], (int)$m['domicile']) ?></div>
                <div class="admin-match-meta">
                    <?= fmt_date($m['date_match']) ?>
                    <?php if ($m['competition']): ?> · <?= htmlspecialchars($m['competition']) ?><?php endif; ?>
                    · <?= $m['nb_votes'] ?> pronostic<?= $m['nb_votes'] > 1 ? 's' : '' ?>
                </div>
            </div>
            <div class="admin-match-actions" style="flex-direction:column;align-items:flex-start;width:100%">
                <button class="btn-sm btn-sm--result" onclick="toggleResultForm(<?= $m['id'] ?>)">📝 Saisir le résultat</button>
                <div class="result-form-inline" id="result-form-<?= $m['id'] ?>">
                    <p class="result-form-inline-title">📝 Résultat officiel</p>
                    <div class="result-form-fields">
                        <div class="result-form-group">
                            <label for="rv-<?= $m['id'] ?>">Résultat</label>
                            <select id="rv-<?= $m['id'] ?>" onchange="updateSetsOpts(<?= $m['id'] ?>)">
                                <option value="">-- Choisir --</option>
                                <option value="1">✅ Victoire</option>
                                <option value="0">❌ Défaite</option>
                            </select>
                        </div>
                        <div class="result-form-group">
                            <label for="rs-<?= $m['id'] ?>">Score en sets</label>
                            <select id="rs-<?= $m['id'] ?>" disabled>
                                <option value="">-- Choisir un résultat d'abord --</option>
                            </select>
                        </div>
                    </div>
                    <div class="result-form-footer">
                        <button class="btn-sm btn-sm--result" onclick="setResult(<?= $m['id'] ?>)">✓ Valider le résultat</button>
                        <span class="result-err" id="result-err-<?= $m['id'] ?>"></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>

        <!-- Terminés -->
        <p class="prono-section-title">✅ Terminés (<?= count($termines) ?>)</p>
        <?php if (empty($termines)): ?>
            <p class="prono-empty">Aucun match terminé.</p>
        <?php else: foreach ($termines as $m): ?>
        <div class="admin-match-item admin-match-item--inline prono-card--closed">
            <span class="admin-match-title"><?= match_titre($m['adversaire'], (int)$m['domicile']) ?></span>
            <span class="admin-match-meta-inline">
                <?= fmt_date($m['date_match']) ?>
                <?php if ($m['competition']): ?> · <?= htmlspecialchars($m['competition']) ?><?php endif; ?>
                · <?= $m['nb_votes'] ?> pronostic<?= $m['nb_votes'] > 1 ? 's' : '' ?>
            </span>
            <span class="prono-result-badge <?= $m['resultat_victoire'] ? 'prono-result-badge--win' : 'prono-result-badge--lose' ?>">
                <?= $m['resultat_victoire'] ? 'Victoire' : 'Défaite' ?> <?= htmlspecialchars($m['resultat_sets'] ?? '') ?>
            </span>
        </div>
        <?php endforeach; endif; ?>

        <!-- Zone danger -->
        <?php if (has_role('admin')): ?>
        <div class="prono-danger-zone">
            <div class="prono-danger-title">⚠️ Zone de danger</div>
            <p class="prono-danger-desc">Supprime tous les matchs et tous les votes de façon irréversible.</p>
            <button class="btn-sm btn-sm--delete" style="padding:8px 18px;font-size:.85rem" onclick="resetAll()">
                🗑 Réinitialiser tous les pronostics
            </button>
        </div>
        <?php endif; ?>

    </div>

    <div id="footer"></div>
    <script src="/js/main.js"></script>
    <script>

        const SETS_WIN  = ['3-0', '3-1', '3-2'];
        const SETS_LOSE = ['0-3', '1-3', '2-3'];

        async function resetAll() {
            if (!confirm('⚠️ Réinitialiser tous les pronostics ?\n\nTous les matchs et tous les votes seront définitivement supprimés.')) return;
            if (!confirm('Dernière confirmation : cette action est irréversible. Continuer ?')) return;

            const res  = await fetch('/php/pronostics/reset_all.php', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                alert(data.nb_matchs + ' match(s) et ' + data.nb_votes + ' vote(s) supprimés.');
                location.reload();
            } else {
                alert('Erreur : ' + (data.error || 'inconnue'));
            }
        }

        function matchTitre(adversaire, domicile) {
            return domicile ? 'VBO vs ' + adversaire : adversaire + ' vs VBO';
        }

        async function createMatch() {
            const adversaire  = document.getElementById('adversaire').value.trim();
            const date_match  = document.getElementById('date_match').value;
            const competition = document.getElementById('competition').value.trim();
            const domicile    = document.getElementById('domicile').checked;
            const errEl       = document.getElementById('create-error');
            errEl.style.display = 'none';

            if (!adversaire || !date_match) {
                errEl.textContent = 'Adversaire et date sont obligatoires.';
                errEl.style.display = 'block';
                return;
            }

            const fd = new FormData();
            fd.append('adversaire', adversaire);
            fd.append('date_match', date_match);
            if (competition) fd.append('competition', competition);
            if (domicile) fd.append('domicile', '1');

            const res  = await fetch('/php/pronostics/create_match.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                errEl.textContent   = data.error || 'Erreur';
                errEl.style.display = 'block';
            }
        }

        function toggleEditForm(id) {
            document.getElementById('edit-form-' + id).classList.toggle('open');
        }

        async function updateMatch(id) {
            const adversaire  = document.getElementById('e-adv-' + id).value.trim();
            const date_match  = document.getElementById('e-date-' + id).value;
            const competition = document.getElementById('e-comp-' + id).value.trim();
            const domicile    = document.getElementById('e-dom-' + id).checked;
            const err         = document.getElementById('edit-err-' + id);
            err.textContent   = '';

            if (!adversaire || !date_match) { err.textContent = 'Adversaire et date sont obligatoires.'; return; }

            const fd = new FormData();
            fd.append('match_id', id);
            fd.append('adversaire', adversaire);
            fd.append('date_match', date_match);
            if (competition) fd.append('competition', competition);
            if (domicile) fd.append('domicile', '1');

            const res  = await fetch('/php/pronostics/update_match.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                document.getElementById('title-' + id).textContent = matchTitre(data.adversaire, data.domicile);
                document.getElementById('edit-form-' + id).classList.remove('open');
                location.reload();
            } else {
                err.textContent = data.error || 'Erreur';
            }
        }

        async function deleteMatch(id, nom, domicile) {
            const titre = matchTitre(nom, domicile);
            if (!confirm('Supprimer le match ' + titre + ' ? Tous les votes seront perdus.')) return;
            const fd = new FormData(); fd.append('match_id', id);
            const res  = await fetch('/php/pronostics/delete_match.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                document.getElementById('admin-match-' + id)?.remove();
            } else {
                alert('Erreur : ' + (data.error || 'inconnue'));
            }
        }

        function toggleResultForm(id) {
            document.getElementById('result-form-' + id).classList.toggle('open');
        }

        function updateSetsOpts(id) {
            const rv  = document.getElementById('rv-' + id).value;
            const sel = document.getElementById('rs-' + id);
            if (!rv) {
                sel.innerHTML = '<option value="">-- Choisir un résultat d\'abord --</option>';
                sel.disabled = true;
                return;
            }
            const opts = rv === '1' ? SETS_WIN : SETS_LOSE;
            sel.innerHTML = '<option value="">-- Score en sets --</option>';
            opts.forEach(s => { const o = document.createElement('option'); o.value = s; o.textContent = s; sel.appendChild(o); });
            sel.disabled = false;
        }

        async function setResult(id) {
            const rv  = document.getElementById('rv-' + id).value;
            const rs  = document.getElementById('rs-' + id).value;
            const err = document.getElementById('result-err-' + id);
            err.textContent = '';

            if (!rv || !rs) { err.textContent = 'Choisissez le résultat et le score.'; return; }

            const fd = new FormData();
            fd.append('match_id', id);
            fd.append('resultat_victoire', rv);
            fd.append('resultat_sets', rs);

            const res  = await fetch('/php/pronostics/set_result.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                err.textContent = data.error || 'Erreur';
            }
        }
    </script>
</body>
</html>
