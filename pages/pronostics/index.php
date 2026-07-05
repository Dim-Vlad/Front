<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();

$pdo    = get_pdo();
$userId = (int)$_SESSION['user_id'];
$now    = date('Y-m-d H:i:s');

$matchs = $pdo->prepare(
    'SELECT m.*,
            v.choix_victoire, v.choix_sets, v.updated_at AS vote_at,
            (SELECT COUNT(*) FROM pronostics_votes pv WHERE pv.match_id = m.id) AS nb_votes
    FROM pronostics_matchs m
    LEFT JOIN pronostics_votes v ON v.match_id = m.id AND v.user_id = :uid
    ORDER BY m.date_match DESC'
);
$matchs->execute([':uid' => $userId]);
$matchs = $matchs->fetchAll(PDO::FETCH_ASSOC);

$aVenir     = array_filter($matchs, fn($m) => $m['date_match'] > $now && $m['resultat_victoire'] === null);
$sansResult = array_filter($matchs, fn($m) => $m['date_match'] <= $now && $m['resultat_victoire'] === null);
$termines   = array_filter($matchs, fn($m) => $m['resultat_victoire'] !== null);

function match_titre(string $adversaire, int $domicile): string {
    $adv = htmlspecialchars($adversaire);
    return $domicile ? 'VBO vs ' . $adv : $adv . ' vs VBO';
}

function fmt_date_court(string $d): string {
    $ts   = strtotime($d);
    $mois = ['','jan.','fév.','mar.','avr.','mai','juin','juil.','aoû.','sep.','oct.','nov.','déc.'];
    return date('d', $ts) . ' ' . $mois[(int)date('n', $ts)] . ' ' . date('Y', $ts) . ' · ' . date('H\hi', $ts);
}

function pts_label(int $pts): string {
    return match($pts) {
        3 => '<span class="prono-points prono-points--3">🏆 +3 pts</span>',
        1 => '<span class="prono-points prono-points--1">✅ +1 pt</span>',
        default => '<span class="prono-points prono-points--0">❌ 0 pt</span>',
    };
}

function compute_pts(array $m): ?int {
    if ($m['resultat_victoire'] === null) return null;
    if ($m['choix_victoire'] === null) return null;
    if ($m['choix_sets'] !== null && $m['choix_sets'] === $m['resultat_sets']) return 3;
    if ((int)$m['choix_victoire'] === (int)$m['resultat_victoire']) return 1;
    return 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pronostics - VBO</title>
    <link href="/css/styles.css?v=20260624" rel="stylesheet">
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
                <h1>Pronostics</h1>
                <p>Tentez de prédire les résultats des matchs du VBO.</p>
            </div>
        </div>
    </div>

    <div class="prono-container">

        <div class="prono-top-bar">
            <a href="/pages/pronostics/classement.php" class="back-btn">🏅 Classement</a>
            <?php if (has_any_role(['admin', 'moderateur'])): ?>
            <a href="/pages/admin/pronostics.php" class="back-btn">⚙️ Gérer les matchs</a>
            <?php endif; ?>
            <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Tableau de bord</a>
        </div>

        <!-- Barème -->
        <div style="background:#f8f8f8;border-radius:10px;padding:12px 16px;font-size:.82rem;color:#666;margin-bottom:4px;line-height:1.8">
            <strong>Barème :</strong>
            Victoire / Défaite correcte → <strong style="color:#2d6a2d">1 pt</strong> &nbsp;·&nbsp;
            Score en sets exact → <strong style="color:#c17900">3 pts</strong>
        </div>

        <!-- Matchs à venir -->
        <p class="prono-section-title">📅 Matchs à venir</p>
        <?php if (empty($aVenir)): ?>
            <p class="prono-empty">Aucun match à venir pour l'instant.</p>
        <?php else: foreach ($aVenir as $m):
            $voted = $m['choix_victoire'] !== null;
        ?>
        <div class="prono-card prono-card--avenir <?= $voted ? 'prono-card--voted' : '' ?>" id="card-<?= $m['id'] ?>">

            <!-- Ligne principale -->
            <div class="prono-done-info">
                <div class="prono-match-title"><?= match_titre($m['adversaire'], (int)$m['domicile']) ?></div>
                <div class="prono-match-meta">
                    <?= fmt_date_court($m['date_match']) ?>
                    <?php if ($m['competition']): ?> · <?= htmlspecialchars($m['competition']) ?><?php endif; ?>
                </div>
            </div>
            <span class="prono-badge-dom <?= $m['domicile'] ? 'prono-badge-dom--home' : 'prono-badge-dom--away' ?>">
                <?= $m['domicile'] ? 'Domicile' : 'Extérieur' ?>
            </span>
            <div class="prono-done-col" id="vote-status-<?= $m['id'] ?>">
                <span class="prono-done-label">Mon pronostic</span>
                <?php if ($voted): ?>
                <div class="prono-done-badges">
                    <span class="prono-result-badge <?= (int)$m['choix_victoire'] === 1 ? 'prono-result-badge--win' : 'prono-result-badge--lose' ?>">
                        <?= (int)$m['choix_victoire'] === 1 ? 'Victoire' : 'Défaite' ?>
                    </span>
                    <?php if ($m['choix_sets']): ?><span class="prono-sets-val"><?= htmlspecialchars($m['choix_sets']) ?></span><?php endif; ?>
                </div>
                <?php else: ?>
                <span class="prono-no-vote">Non voté</span>
                <?php endif; ?>
            </div>
            <span class="prono-nb-votes"><?= $m['nb_votes'] ?> pronostic<?= $m['nb_votes'] > 1 ? 's' : '' ?></span>
            <button class="prono-vote-submit prono-vote-toggle" style="padding:7px 16px;font-size:.82rem" onclick="toggleVoteForm(<?= $m['id'] ?>)">
                <?= $voted ? '✏️ Modifier' : '🎯 Voter' ?>
            </button>

            <!-- Panneau de vote (collapsible) -->
            <div class="prono-vote-panel" id="vote-panel-<?= $m['id'] ?>">
                <div class="prono-choices">
                    <label class="prono-choice prono-choice--win">
                        <input type="radio" name="choix-<?= $m['id'] ?>" value="1" <?= ($voted && (int)$m['choix_victoire'] === 1) ? 'checked' : '' ?> onchange="onChoix(<?= $m['id'] ?>)">
                        ✅ Victoire
                    </label>
                    <label class="prono-choice prono-choice--lose">
                        <input type="radio" name="choix-<?= $m['id'] ?>" value="0" <?= ($voted && (int)$m['choix_victoire'] === 0) ? 'checked' : '' ?> onchange="onChoix(<?= $m['id'] ?>)">
                        ❌ Défaite
                    </label>
                </div>
                <div class="prono-sets-row" id="sets-row-<?= $m['id'] ?>" style="<?= $voted ? '' : 'display:none' ?>">
                    <span class="prono-sets-label">Score en sets (optionnel) :</span>
                    <select class="prono-sets-select" id="sets-<?= $m['id'] ?>">
                        <option value="">-- Je ne pronostique pas --</option>
                    </select>
                    <span class="prono-pts-hint">→ 3 pts si exact</span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <button class="prono-vote-submit" onclick="submitVote(<?= $m['id'] ?>)">
                        <?= $voted ? 'Enregistrer la modification' : 'Valider mon pronostic' ?>
                    </button>
                    <button class="btn-sm" style="background:#f0f0f0;color:#666" onclick="toggleVoteForm(<?= $m['id'] ?>)">Annuler</button>
                    <span class="prono-vote-saved" id="saved-<?= $m['id'] ?>">✅ Enregistré</span>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const mid = <?= $m['id'] ?>;
            const savedChoice = <?= $voted ? (int)$m['choix_victoire'] : 'null' ?>;
            const savedSets   = <?= $m['choix_sets'] ? '"' . $m['choix_sets'] . '"' : 'null' ?>;
            initSetsSelect(mid, savedChoice, savedSets);
        })();
        </script>
        <?php endforeach; endif; ?>

        <!-- En attente de résultat -->
        <?php if (!empty($sansResult)): ?>
        <p class="prono-section-title">⏳ Résultats en attente</p>
        <?php foreach ($sansResult as $m): ?>
        <div class="prono-card prono-card--done prono-card--closed">
            <div class="prono-done-info">
                <div class="prono-match-title"><?= match_titre($m['adversaire'], (int)$m['domicile']) ?></div>
                <div class="prono-match-meta">
                    <?= fmt_date_court($m['date_match']) ?>
                    <?php if ($m['competition']): ?> · <?= htmlspecialchars($m['competition']) ?><?php endif; ?>
                </div>
            </div>
            <div class="prono-done-col">
                <span class="prono-done-label">Mon pronostic</span>
                <?php if ($m['choix_victoire'] !== null): ?>
                <div class="prono-done-badges">
                    <span class="prono-result-badge <?= (int)$m['choix_victoire'] === 1 ? 'prono-result-badge--win' : 'prono-result-badge--lose' ?>">
                        <?= (int)$m['choix_victoire'] === 1 ? 'Victoire' : 'Défaite' ?>
                    </span>
                    <?php if ($m['choix_sets']): ?><span class="prono-sets-val"><?= htmlspecialchars($m['choix_sets']) ?></span><?php endif; ?>
                </div>
                <?php else: ?>
                <span class="prono-no-vote">—</span>
                <?php endif; ?>
            </div>
            <div class="prono-done-col">
                <span class="prono-done-label">&nbsp;</span>
                <span class="prono-waiting" style="margin:0">⏳ En attente</span>
            </div>
        </div>
        <?php endforeach; endif; ?>

        <!-- Terminés -->
        <p class="prono-section-title">✅ Matchs terminés</p>
        <?php if (empty($termines)): ?>
            <p class="prono-empty">Aucun résultat encore disponible.</p>
        <?php else: foreach ($termines as $m):
            $pts = compute_pts($m);
        ?>
        <div class="prono-card prono-card--done">
            <div class="prono-done-info">
                <div class="prono-match-title"><?= match_titre($m['adversaire'], (int)$m['domicile']) ?></div>
                <div class="prono-match-meta">
                    <?= fmt_date_court($m['date_match']) ?>
                    <?php if ($m['competition']): ?> · <?= htmlspecialchars($m['competition']) ?><?php endif; ?>
                </div>
            </div>
            <div class="prono-done-col">
                <span class="prono-done-label">Résultat</span>
                <div class="prono-done-badges">
                    <span class="prono-result-badge <?= (int)$m['resultat_victoire'] === 1 ? 'prono-result-badge--win' : 'prono-result-badge--lose' ?>">
                        <?= (int)$m['resultat_victoire'] === 1 ? 'Victoire' : 'Défaite' ?>
                    </span>
                    <?php if ($m['resultat_sets']): ?><span class="prono-sets-val"><?= htmlspecialchars($m['resultat_sets']) ?></span><?php endif; ?>
                </div>
            </div>
            <div class="prono-done-col">
                <span class="prono-done-label">Mon pronostic</span>
                <?php if ($m['choix_victoire'] !== null): ?>
                <div class="prono-done-badges">
                    <span class="prono-result-badge <?= (int)$m['choix_victoire'] === 1 ? 'prono-result-badge--win' : 'prono-result-badge--lose' ?>">
                        <?= (int)$m['choix_victoire'] === 1 ? 'Victoire' : 'Défaite' ?>
                    </span>
                    <?php if ($m['choix_sets']): ?><span class="prono-sets-val"><?= htmlspecialchars($m['choix_sets']) ?></span><?php endif; ?>
                    <?= pts_label($pts ?? 0) ?>
                </div>
                <?php else: ?>
                <span class="prono-no-vote">—</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>

    </div>

    <div id="footer"></div>
    <script src="/js/main.js"></script>
    <script>

        const SETS_WIN  = ['3-0', '3-1', '3-2'];
        const SETS_LOSE = ['0-3', '1-3', '2-3'];

        function initSetsSelect(mid, savedChoice, savedSets) {
            if (savedChoice === null) return;
            buildSetsOpts(mid, savedChoice, savedSets);
        }

        function buildSetsOpts(mid, choix, selectedSets) {
            const sel = document.getElementById('sets-' + mid);
            if (!sel) return;
            const opts = choix === 1 ? SETS_WIN : SETS_LOSE;
            sel.innerHTML = '<option value="">-- Je ne pronostique pas --</option>';
            opts.forEach(s => {
                const o = document.createElement('option');
                o.value = s;
                o.textContent = s;
                if (s === selectedSets) o.selected = true;
                sel.appendChild(o);
            });
        }

        function onChoix(mid) {
            const checked = document.querySelector('input[name="choix-' + mid + '"]:checked');
            const row = document.getElementById('sets-row-' + mid);
            if (!checked) { row.style.display = 'none'; return; }
            row.style.display = '';
            buildSetsOpts(mid, parseInt(checked.value), null);
        }

        function toggleVoteForm(mid) {
            document.getElementById('vote-panel-' + mid).classList.toggle('open');
        }

        async function submitVote(mid) {
            const checked = document.querySelector('input[name="choix-' + mid + '"]:checked');
            if (!checked) { alert('Choisissez Victoire ou Défaite.'); return; }
            const sets = document.getElementById('sets-' + mid)?.value || '';

            const fd = new FormData();
            fd.append('match_id', mid);
            fd.append('choix_victoire', checked.value);
            if (sets) fd.append('choix_sets', sets);

            const submitBtn = document.querySelector('#vote-panel-' + mid + ' .prono-vote-submit');
            const saved     = document.getElementById('saved-' + mid);
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi…';

            const res  = await fetch('/php/pronostics/vote.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                // Mise à jour du statut sur la ligne principale
                const isWin  = checked.value === '1';
                const label  = isWin ? 'Victoire' : 'Défaite';
                const cls    = isWin ? 'prono-result-badge--win' : 'prono-result-badge--lose';
                let html = `<span class="prono-done-label">Mon pronostic</span><div class="prono-done-badges"><span class="prono-result-badge ${cls}">${label}</span>`;
                if (sets) html += `<span class="prono-sets-val">${sets}</span>`;
                html += '</div>';
                document.getElementById('vote-status-' + mid).innerHTML = html;

                // Bouton de la ligne principale → Modifier
                document.querySelector('#card-' + mid + ' .prono-vote-toggle').textContent = '✏️ Modifier';

                // Fermer le panneau
                document.getElementById('vote-panel-' + mid).classList.remove('open');
                document.getElementById('card-' + mid).classList.add('prono-card--voted');

                saved.style.display = 'inline';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enregistrer la modification';
                setTimeout(() => saved.style.display = 'none', 3000);
            } else {
                alert(data.error || 'Erreur');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Valider mon pronostic';
            }
        }
    </script>
</body>
</html>
