<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('admin')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$user = current_user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques du site - VBO</title>
    <link href="/css/styles.css?v=20261005" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260703" rel="stylesheet">
    <link href="/css/admin.css?v=20260623" rel="stylesheet">
    <link href="/css/statistiques.css?v=20261105" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content header-content--centre">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>📊 Statistiques du site</h1>
                <p>Fréquentation du site, basée sur le suivi interne des pages consultées. Les robots d'indexation sont automatiquement exclus.</p>
            </div>
        </div>
    </div>

    <a href="/pages/admin/index.php" class="back-btn">← Retour</a>

    <div class="stats-container">

        <div class="stats-toolbar">
            <div class="periode-switch" id="periode-switch">
                <button data-periode="7">7 jours</button>
                <button data-periode="30" class="active">30 jours</button>
                <button data-periode="90">90 jours</button>
                <button data-periode="tout">Depuis le début</button>
            </div>
            <p id="stats-loading" class="stats-loading">Chargement…</p>
        </div>

        <!-- KPIs -->
        <div class="stats-kpis" id="stats-kpis">
            <div class="kpi-tile">
                <div class="kpi-icon">👥</div>
                <div class="kpi-value" id="kpi-visites">—</div>
                <div class="kpi-label">Visites</div>
                <p class="kpi-help">Nombre de sessions de navigation distinctes (un visiteur qui revient plus tard dans la journée compte pour une nouvelle visite).</p>
            </div>
            <div class="kpi-tile">
                <div class="kpi-icon">📄</div>
                <div class="kpi-value" id="kpi-pages">—</div>
                <div class="kpi-label">Pages vues</div>
                <p class="kpi-help">Nombre total de pages chargées, toutes visites confondues.</p>
            </div>
            <div class="kpi-tile">
                <div class="kpi-icon">🔁</div>
                <div class="kpi-value" id="kpi-ratio">—</div>
                <div class="kpi-label">Pages vues / visite</div>
                <p class="kpi-help">En moyenne, combien de pages un visiteur consulte avant de quitter le site.</p>
            </div>
            <div class="kpi-tile">
                <div class="kpi-icon">⏱️</div>
                <div class="kpi-value" id="kpi-duree">—</div>
                <div class="kpi-label">Durée moyenne de session</div>
                <p class="kpi-help">Écart entre la première et la dernière page vue d'une même visite. Les visites d'une seule page comptent 0 seconde, ce qui tire cette moyenne vers le bas.</p>
            </div>
        </div>

        <!-- Évolution -->
        <div class="admin-card stats-card">
            <h2>Évolution de la fréquentation</h2>
            <p class="stats-explain">Nombre de pages vues et de visites, jour par jour, sur la période sélectionnée.</p>
            <div class="chart-wrap chart-wrap--tall">
                <canvas id="chart-evolution"></canvas>
            </div>
        </div>

        <div class="stats-two-col">
            <div class="admin-card stats-card">
                <h2>Navigateurs utilisés</h2>
                <p class="stats-explain">Logiciel de navigation utilisé par les visiteurs (Chrome, Safari, Firefox…).</p>
                <div class="chart-wrap">
                    <canvas id="chart-navigateurs"></canvas>
                </div>
            </div>
            <div class="admin-card stats-card">
                <h2>Plateformes</h2>
                <p class="stats-explain">Système d'exploitation des appareils utilisés pour consulter le site.</p>
                <div class="chart-wrap">
                    <canvas id="chart-plateformes"></canvas>
                </div>
            </div>
        </div>

        <!-- Pays -->
        <div class="admin-card stats-card">
            <h2>Origine géographique</h2>
            <p class="stats-explain">Pays d'origine des visiteurs, déduit de leur adresse IP au moment de la visite grâce à une base de géolocalisation installée localement — <strong>aucune adresse IP n'est jamais enregistrée</strong>, seul le pays obtenu est conservé.</p>
            <div id="stats-pays" class="pays-list"></div>
            <p class="stats-attribution">Géolocalisation IP par <a href="https://db-ip.com/" target="_blank" rel="noopener">DB-IP</a>.</p>
        </div>

        <div class="stats-two-col">
            <div class="admin-card stats-card">
                <h2>Pages les plus consultées</h2>
                <p class="stats-explain">Les adresses de page ayant reçu le plus de vues sur la période.</p>
                <table class="admin-table stats-table">
                    <thead><tr><th>Page</th><th>Vues</th></tr></thead>
                    <tbody id="stats-pages-top"></tbody>
                </table>
            </div>
            <div class="admin-card stats-card">
                <h2>Parcours les plus fréquents</h2>
                <p class="stats-explain">Les enchaînements de pages (« page précédente → page suivante ») les plus courants chez les visiteurs.</p>
                <table class="admin-table stats-table">
                    <thead><tr><th>Parcours</th><th>Occurrences</th></tr></thead>
                    <tbody id="stats-parcours"></tbody>
                </table>
            </div>
        </div>

        <a href="/pages/admin/index.php" class="back-btn">← Retour</a>

    </div>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script>
    let periodeActuelle = '30';
    let charts = {};

    function fmtDuree(sec) {
        if (sec < 60) return sec + ' s';
        const m = Math.floor(sec / 60), s = sec % 60;
        if (m < 60) return m + ' min ' + s + ' s';
        const h = Math.floor(m / 60);
        return h + ' h ' + (m % 60) + ' min';
    }

    function echapper(s) {
        const div = document.createElement('div');
        div.textContent = String(s ?? '');
        return div.innerHTML;
    }

    function creerChart(id, config) {
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(document.getElementById(id), config);
    }

    async function chargerStats(periode) {
        periodeActuelle = periode;
        document.getElementById('stats-loading').style.display = 'inline';
        try {
            const res  = await fetch('/php/analytics/stats_data.php?periode=' + encodeURIComponent(periode));
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Erreur');
            afficherStats(data);
        } catch (e) {
            document.getElementById('stats-loading').textContent = 'Erreur de chargement des statistiques.';
            return;
        }
        document.getElementById('stats-loading').style.display = 'none';
    }

    function afficherStats(data) {
        const pagesVues = data.kpis.pages_vues || 0;
        const visites   = data.kpis.visites || 0;
        document.getElementById('kpi-visites').textContent = visites.toLocaleString('fr-FR');
        document.getElementById('kpi-pages').textContent   = pagesVues.toLocaleString('fr-FR');
        document.getElementById('kpi-ratio').textContent   = visites > 0 ? (pagesVues / visites).toFixed(1) : '0';
        document.getElementById('kpi-duree').textContent   = fmtDuree(data.kpis.duree_moyenne || 0);

        creerChart('chart-evolution', {
            type: 'line',
            data: {
                labels: data.evolution.map(r => new Date(r.jour).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })),
                datasets: [
                    { label: 'Pages vues', data: data.evolution.map(r => r.pages_vues), borderColor: '#063E0B', backgroundColor: 'rgba(6,62,11,.08)', tension: .3, fill: true },
                    { label: 'Visites',    data: data.evolution.map(r => r.visites),    borderColor: '#2e7d32', backgroundColor: 'rgba(46,125,50,.06)', tension: .3, fill: true },
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });

        const palette = ['#063E0B', '#2e7d32', '#66bb6a', '#a5d6a7', '#ffb74d', '#4fc3f7', '#ba68c8', '#e57373'];

        creerChart('chart-navigateurs', {
            type: 'doughnut',
            data: {
                labels: data.navigateurs.map(r => r.navigateur || 'Inconnu'),
                datasets: [{ data: data.navigateurs.map(r => r.total), backgroundColor: palette }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        creerChart('chart-plateformes', {
            type: 'doughnut',
            data: {
                labels: data.plateformes.map(r => r.plateforme || 'Inconnu'),
                datasets: [{ data: data.plateformes.map(r => r.total), backgroundColor: palette }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        const totalPays = data.pays.reduce((s, r) => s + r.total, 0) || 1;
        document.getElementById('stats-pays').innerHTML = data.pays.length ? data.pays.map(r => {
            const pct = (r.total / totalPays * 100);
            return `<div class="pays-row">
                        <span class="pays-nom">${echapper(r.pays_nom || 'Inconnu')}</span>
                        <div class="pays-bar-track"><div class="pays-bar" style="width:${pct.toFixed(1)}%"></div></div>
                        <span class="pays-total">${r.total.toLocaleString('fr-FR')}</span>
                    </div>`;
        }).join('') : '<p class="stats-empty">Aucune donnée pour cette période.</p>';

        document.getElementById('stats-pages-top').innerHTML = data.pages_top.length ? data.pages_top.map(r =>
            `<tr><td data-label="Page">${echapper(r.url)}</td><td data-label="Vues">${r.total.toLocaleString('fr-FR')}</td></tr>`
        ).join('') : '<tr><td colspan="2" class="stats-empty">Aucune donnée pour cette période.</td></tr>';

        document.getElementById('stats-parcours').innerHTML = data.parcours.length ? data.parcours.map(r =>
            `<tr><td data-label="Parcours">${echapper(r.page_precedente)} → ${echapper(r.url)}</td><td data-label="Occurrences">${r.total.toLocaleString('fr-FR')}</td></tr>`
        ).join('') : '<tr><td colspan="2" class="stats-empty">Aucune donnée pour cette période.</td></tr>';
    }

    document.getElementById('periode-switch').addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        document.querySelectorAll('#periode-switch button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        chargerStats(btn.dataset.periode);
    });

    chargerStats(periodeActuelle);
    </script>
</body>
</html>
