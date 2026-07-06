<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (has_role('arbitre')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$isAdmin      = has_role('admin');
$isPrivileged = has_role('admin') || has_role('moderateur') || has_role('bureau');
$isEntraineur = has_role('entraineur');
$me           = current_user();
$myFullName   = trim(($me['prenom'] ?? '') . ' ' . ($me['nom'] ?? ''));

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'presences_sheet_url'");
    $stmt->execute();
    $row      = $stmt->fetch();
    $sheetUrl = ($row && $row['valeur']) ? $row['valeur'] : '';
} catch (Exception $e) {
    $sheetUrl = '';
}

// ------- Fetch CSV (pubhtml URL → pub?output=csv) -------
function fetchCsv(string $url): string|false {
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
        return @file_get_contents($url, false, $ctx);
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 6,
        ]);
        $r = curl_exec($ch);
        return $r ?: false;
    }
    return false;
}

$header     = [];
$rows       = [];
$fetchError = '';

if ($sheetUrl) {
    $csvUrl = '';
    if (preg_match('#(https://docs\.google\.com/spreadsheets/d/e/[^/]+)#', $sheetUrl, $m)) {
        $csvUrl = $m[1] . '/pub?output=csv';
    }

    if ($csvUrl) {
        $raw = fetchCsv($csvUrl);
        if ($raw !== false && trim($raw) !== '') {
            $lines  = preg_split('/\r\n|\r|\n/', trim($raw));
            $parsed = array_values(array_filter(
                array_map(fn($l) => str_getcsv($l, ',', '"', ''), $lines),
                fn($r) => array_filter($r, fn($v) => trim($v) !== '') !== []
            ));
            if (!empty($parsed)) {
                $header = array_shift($parsed);
                if ($isPrivileged || !$isEntraineur) {
                    $rows = $parsed;
                } else {
                    // Entraîneur : colonne B (index 1) = Prénom + Nom
                    $rows = array_values(array_filter(
                        $parsed,
                        fn($r) => isset($r[1]) && strcasecmp(trim($r[1]), $myFullName) === 0
                    ));
                }
            }
        } else {
            $fetchError = 'Impossible de récupérer le tableau (sheet inaccessible ou non publié).';
        }
    }
}

// Noms uniques de la colonne B pour le filtre admin/modérateur
$trainerNames = [];
if ($isPrivileged && !empty($rows)) {
    foreach ($rows as $r) {
        $name = trim($r[1] ?? '');
        if ($name !== '') $trainerNames[$name] = true;
    }
    $trainerNames = array_keys($trainerNames);
    sort($trainerNames);
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
    <style>
        .presences-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 10px;
            border: 1px solid #e4ebe4;
        }
        .presences-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            white-space: nowrap;
        }
        .presences-table thead tr {
            background: var(--secondary-color);
        }
        .presences-table th {
            color: #fff;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.6rem 0.9rem;
            text-align: left;
        }
        .presences-table th:first-child { border-radius: 9px 0 0 0; }
        .presences-table th:last-child  { border-radius: 0 9px 0 0; }
        .presences-table td {
            padding: 0.45rem 0.9rem;
            border-bottom: 1px solid #edf2ed;
            color: var(--bg-dark);
            vertical-align: middle;
        }
        .presences-table tbody tr:nth-child(even) td { background: #f6fbf6; }
        .presences-table tr:last-child td { border-bottom: none; }
        .presences-table tbody tr:hover td {
            background: #eaf4ea;
            transition: background 0.12s;
        }
        .presences-meta {
            font-size: 0.82rem;
            color: #888;
            margin-bottom: 0.8rem;
            padding: 0.9rem 0.9rem 0;
        }
        .presences-meta strong { color: var(--secondary-color); }
        .presences-empty {
            color: #888;
            font-style: italic;
            text-align: center;
            padding: 1.5rem 0;
        }
        .presences-filter-bar {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            margin-bottom: 0.9rem;
            flex-wrap: wrap;
            padding: 0 0.9rem;
        }
        .presences-filter-bar label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--bg-dark);
            white-space: nowrap;
        }
        .presences-filter-select {
            padding: 0.4rem 0.75rem;
            border: 1.5px solid #ccc;
            border-radius: 7px;
            font-family: inherit;
            font-size: 0.82rem;
            color: var(--bg-dark);
            background: #fafafa;
            cursor: pointer;
            transition: border-color 0.2s;
            min-width: 180px;
        }
        .presences-filter-select:focus {
            outline: none;
            border-color: var(--secondary-color);
            background: #fff;
        }
        .presences-filter-reset {
            background: none;
            border: none;
            font-size: 0.8rem;
            color: #aaa;
            cursor: pointer;
            padding: 0.3rem 0.5rem;
            border-radius: 5px;
            transition: color 0.15s;
            display: none;
        }
        .presences-filter-reset.visible { display: inline-block; }
        .presences-filter-reset:hover { color: var(--bg-dark); }
        /* Modal : ne dépasse pas l'écran */
        .url-modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }
        @media (max-width: 600px) {
            .url-modal-content { padding: 1.25rem; }
        }
        .url-modal-steps {
            margin: 0.5rem 0 0.75rem 1.1rem;
            padding: 0;
            font-size: .83rem;
            color: #444;
            line-height: 1.8;
        }
        .url-modal-steps li { padding-left: 0.2rem; }
        .url-modal-format {
            margin: 0;
            font-size: .8rem;
            color: #666;
            line-height: 1.6;
        }
        .url-modal-format code {
            display: inline-block;
            margin-top: 0.2rem;
            background: #eef2ee;
            border: 1px solid #d0dcd0;
            border-radius: 5px;
            padding: 0.2rem 0.5rem;
            font-family: monospace;
            font-size: .78rem;
            color: var(--secondary-color);
            word-break: break-all;
        }
    </style>
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

        <?php if (!empty($header)): ?>
        <div class="sheet-card">
            <p class="presences-meta">
                <?php if ($isEntraineur && !$isPrivileged): ?>
                    Affichage des interventions de <strong><?= htmlspecialchars($myFullName) ?></strong> —
                <?php endif; ?>
                <span id="presences-row-count"><?= count($rows) ?></span> ligne<?= count($rows) !== 1 ? 's' : '' ?>
            </p>
            <?php if ($isPrivileged && !empty($trainerNames)): ?>
            <div class="presences-filter-bar">
                <label for="presences-filter">Entraîneur :</label>
                <select id="presences-filter" class="presences-filter-select">
                    <option value="">Tous</option>
                    <?php foreach ($trainerNames as $n): ?>
                    <option value="<?= htmlspecialchars($n) ?>"><?= htmlspecialchars($n) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="presences-filter-reset" id="presences-filter-reset" title="Réinitialiser">✕ Tous</button>
            </div>
            <?php endif; ?>
            <?php if (empty($rows)): ?>
            <p class="presences-empty">
                <?= ($isEntraineur && !$isPrivileged)
                    ? 'Aucune intervention enregistrée pour votre nom.'
                    : 'Aucune donnée dans ce tableau.' ?>
            </p>
            <?php else: ?>
            <div class="presences-table-wrap">
                <table class="presences-table">
                    <thead>
                        <tr>
                            <?php foreach ($header as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($header as $i => $_): ?>
                            <td><?= htmlspecialchars($row[$i] ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($fetchError): ?>
        <div class="sheet-info-card" style="text-align:center; color:#c0392b;">
            <p><?= htmlspecialchars($fetchError) ?></p>
        </div>

        <?php else: ?>
        <div class="sheet-info-card" style="text-align:center; color:var(--text-muted,#888);">
            <?php if ($isAdmin): ?>
                <p>Aucun lien configuré. Cliquez sur <strong>✎ Modifier le lien</strong> pour ajouter le Google Sheet.</p>
            <?php else: ?>
                <p>Le tableau de présences n'est pas encore configuré.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>

    <?php if ($isPrivileged && !empty($trainerNames)): ?>
    <script>
    (function () {
        var sel   = document.getElementById('presences-filter');
        var reset = document.getElementById('presences-filter-reset');
        var tbody = document.querySelector('.presences-table tbody');
        var counter = document.getElementById('presences-row-count');
        if (!sel || !tbody) return;

        function applyFilter() {
            var val = sel.value.trim().toLowerCase();
            var count = 0;
            Array.from(tbody.rows).forEach(function (tr) {
                var cell = tr.cells[1] ? tr.cells[1].textContent.trim().toLowerCase() : '';
                var show = !val || cell === val;
                tr.style.display = show ? '' : 'none';
                if (show) count++;
            });
            if (counter) counter.textContent = count;
            reset.classList.toggle('visible', val !== '');
        }

        sel.addEventListener('change', applyFilter);
        reset.addEventListener('click', function () {
            sel.value = '';
            applyFilter();
        });
    })();
    </script>
    <?php endif; ?>

    <script>
    (function () {
        var wrap = document.querySelector('.presences-table-wrap');
        var tbl  = document.querySelector('.presences-table');
        if (!wrap || !tbl || !('ontouchstart' in window)) return;

        var curScale = 1, startScale = 1, startDist = 0;

        // Taille réduite par défaut sur mobile
        if (window.matchMedia('(max-width: 700px)').matches) {
            curScale = 0.75;
            tbl.style.zoom = curScale;
        }

        function pinchDist(t) {
            return Math.hypot(t[1].clientX - t[0].clientX, t[1].clientY - t[0].clientY);
        }

        wrap.addEventListener('touchstart', function (e) {
            if (e.touches.length === 2) {
                startDist  = pinchDist(e.touches);
                startScale = curScale;
            }
        }, { passive: true });

        wrap.addEventListener('touchmove', function (e) {
            if (e.touches.length !== 2) return;
            e.preventDefault();
            curScale = Math.min(3, Math.max(0.4, startScale * pinchDist(e.touches) / startDist));
            tbl.style.zoom = curScale;
        }, { passive: false });
    })();
    </script>

    <?php if ($isAdmin): ?>
    <div id="urlModal" class="url-modal" onclick="if(event.target===this)closeUrlModal()">
        <div class="url-modal-content">
            <h2 class="url-modal-title">Modifier le lien Google Sheet</h2>
            <div class="url-modal-desc">
                <strong>Comment récupérer le lien ?</strong>
                <ol class="url-modal-steps">
                    <li>Ouvrez le Google Sheet des présences.</li>
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
            fd.set('cle',    'presences_sheet_url');
            fd.set('valeur', url);
            const res  = await fetch('/php/parametres/update.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Erreur inconnue');
            statusEl.textContent = '✓ Lien mis à jour !';
            statusEl.className   = 'url-status ok';
            setTimeout(() => location.reload(), 1000);
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
