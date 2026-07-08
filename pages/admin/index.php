<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('admin')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$user      = current_user();
$pdo       = get_pdo();
$nbAttente = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE actif = 0')->fetchColumn();

$dernierePurge  = $pdo->query("SELECT valeur FROM parametres WHERE cle = 'derniere_purge_logs'")->fetchColumn() ?: null;
$dernierVidage  = $pdo->query("SELECT valeur FROM parametres WHERE cle = 'dernier_vidage_logs'")->fetchColumn() ?: null;

$nbConnexions = (int)$pdo->query('SELECT COUNT(*) FROM journal_connexions')->fetchColumn();
$nbActivites  = (int)$pdo->query('SELECT COUNT(*) FROM journal_activites')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260703" rel="stylesheet">
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
                <h1>Administration</h1>
                <p>Connecté en tant que <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong></p>
            </div>
        </div>
    </div>

    <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    <div class="dashboard-container">

        <div class="dashboard-two-col">

            <!-- Ligne 1 gauche : Utilisateurs -->
            <div class="dashboard-section">
                <h2 class="dashboard-section-title">👥 Utilisateurs</h2>
                <div class="dashboard-cards">

                    <a href="/pages/admin/gestion-utilisateurs.php" class="dashboard-card card-admin">
                        <div class="card-icon">👤</div>
                        <h2>Gestion des utilisateurs</h2>
                        <p>Créer, consulter et supprimer les comptes membres.</p>
                    </a>

                    <a href="/pages/admin/comptes-attente.php" class="dashboard-card card-admin">
                        <div class="card-icon">
                            ⏳<?php if ($nbAttente > 0): ?>
                            <span class="badge-attente"><?= $nbAttente ?></span>
                            <?php endif; ?>
                        </div>
                        <h2>Comptes en attente</h2>
                        <p>Valider ou refuser les demandes d'inscription des adhérents.</p>
                    </a>

                </div>
            </div>

            <!-- Ligne 1 droite : Jeux -->
            <div class="dashboard-section">
                <h2 class="dashboard-section-title">🎮 Jeux</h2>
                <div class="dashboard-cards">

                    <a href="/pages/admin/pronostics.php" class="dashboard-card card-admin">
                        <div class="card-icon">🎯</div>
                        <h2>Pronostics</h2>
                        <p>Créer des matchs et saisir les résultats pour les pronostics des membres.</p>
                    </a>

                    <a href="/pages/admin/quiz.php" class="dashboard-card card-admin">
                        <div class="card-icon">🧠</div>
                        <h2>Quiz</h2>
                        <p>Créer et gérer les questions du quiz pour les membres.</p>
                    </a>

                </div>
            </div>

            <!-- Ligne 2 gauche : Journaux -->
            <div class="dashboard-section">
                <h2 class="dashboard-section-title">📋 Journaux</h2>
                <div class="dashboard-cards">

                    <a href="/pages/admin/journal.php" class="dashboard-card card-admin">
                        <div class="card-icon">📝</div>
                        <h2>Journal des activités</h2>
                        <p>Historique des modifications effectuées par les modérateurs et administrateurs.</p>
                    </a>

                    <a href="/pages/admin/journal-connexions.php" class="dashboard-card card-admin">
                        <div class="card-icon">🔑</div>
                        <h2>Journal des connexions</h2>
                        <p>Historique des connexions à l'espace membres.</p>
                    </a>

                </div>
            </div>

            <!-- Ligne 2 droite : Agenda -->
            <div class="dashboard-section">
                <h2 class="dashboard-section-title">📅 Agenda</h2>
                <div class="dashboard-cards">

                    <a href="/pages/admin/evenements.php" class="dashboard-card card-admin">
                        <div class="card-icon">📅</div>
                        <h2>Agenda du club</h2>
                        <p>Gérer les événements (matchs, tournois, stages, loto…) et les pages de tournois.</p>
                    </a>

                </div>
            </div>

        </div>

        <!-- Section RGPD — pleine largeur -->
        <div class="dashboard-section">
            <h2 class="dashboard-section-title">🔒 RGPD — Purge des journaux</h2>
            <div class="rgpd-card">
                <div class="rgpd-retention">
                    <div class="rgpd-row">
                        <span class="rgpd-label">Journal des connexions</span>
                        <span class="rgpd-meta">Rétention <strong>90 jours</strong> · <span id="rgpd-nb-connexions"><?= $nbConnexions ?> entrée<?= $nbConnexions > 1 ? 's' : '' ?></span></span>
                    </div>
                    <div class="rgpd-row">
                        <span class="rgpd-label">Journal des activités</span>
                        <span class="rgpd-meta">Rétention <strong>365 jours</strong> · <span id="rgpd-nb-activites"><?= $nbActivites ?> entrée<?= $nbActivites > 1 ? 's' : '' ?></span></span>
                    </div>
                    <div class="rgpd-row">
                        <span class="rgpd-label">Dernière purge RGPD</span>
                        <span class="rgpd-meta" id="rgpd-last-purge">
                            <?php if ($dernierePurge): ?>
                                <?= (new DateTime($dernierePurge))->format('d/m/Y à H\hi') ?>
                            <?php else: ?>
                                <em>Jamais effectuée</em>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="rgpd-row rgpd-row--last">
                        <span class="rgpd-label">Dernier vidage manuel</span>
                        <span class="rgpd-meta" id="rgpd-last-vidage">
                            <?php if ($dernierVidage): ?>
                                <?= (new DateTime($dernierVidage))->format('d/m/Y à H\hi') ?>
                            <?php else: ?>
                                <em>Jamais effectué</em>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="rgpd-actions">
                    <button id="btn-purge" class="btn-purge" onclick="purgerLogs()">🗑 Purger maintenant</button>
                    <button id="btn-clear" class="btn-clear-logs" onclick="viderLogs()">✕ Tout vider</button>
                    <p id="purge-status" class="purge-status"></p>
                </div>
            </div>
        </div>

        <!-- Section Paramètres — pleine largeur -->
        <div class="dashboard-section">
            <h2 class="dashboard-section-title">⚙️ Paramètres</h2>
            <div class="dashboard-cards">

                <a href="/pages/admin/gestion-site.php" class="dashboard-card card-admin">
                    <div class="card-icon">🌐</div>
                    <h2>Gestion du site</h2>
                    <p>Modifier l'adresse, l'email de contact, les réseaux sociaux et les informations du club.</p>
                </a>

            </div>
        </div>

        <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    </div>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script>
    async function viderLogs() {
        if (!confirm('⚠️ Supprimer TOUTES les entrées des deux journaux, sans condition de date ?\n\nCette action est irréversible.')) return;
        const btn    = document.getElementById('btn-clear');
        const status = document.getElementById('purge-status');
        btn.disabled     = true;
        btn.textContent  = 'Suppression…';
        status.textContent = '';
        status.className   = 'purge-status';
        try {
            const fd = new FormData();
            fd.append('_csrf', '<?= csrf_token() ?>');
            const res  = await fetch('/php/rgpd/clear_logs.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                status.textContent = '✅ Journaux entièrement vidés.';
                status.className   = 'purge-status purge-ok';
                document.getElementById('rgpd-nb-connexions').textContent = '0 entrée';
                document.getElementById('rgpd-nb-activites').textContent  = '0 entrée';
                if (data.date_vidage) {
                    const d = new Date(data.date_vidage.replace(' ', 'T'));
                    document.getElementById('rgpd-last-vidage').textContent =
                        d.toLocaleDateString('fr-FR') + ' à ' +
                        String(d.getHours()).padStart(2,'0') + 'h' + String(d.getMinutes()).padStart(2,'0');
                }
            } else {
                status.textContent = '❌ ' + (data.error || 'Erreur inconnue');
                status.className   = 'purge-status purge-err';
            }
        } catch {
            status.textContent = '❌ Erreur réseau.';
            status.className   = 'purge-status purge-err';
        } finally {
            btn.disabled    = false;
            btn.textContent = '✕ Tout vider';
        }
    }

    async function purgerLogs() {
        if (!confirm('Supprimer les entrées anciennes ?\n\n• Connexions de plus de 90 jours\n• Activités de plus de 365 jours')) return;
        const btn    = document.getElementById('btn-purge');
        const status = document.getElementById('purge-status');
        btn.disabled     = true;
        btn.textContent  = 'Purge en cours…';
        status.textContent = '';
        status.className   = 'purge-status';
        try {
            const fd = new FormData();
            fd.append('_csrf', '<?= csrf_token() ?>');
            const res = await fetch('/php/rgpd/purge_logs.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                status.textContent = '✅ ' + data.connexions_supprimees + ' connexion(s) et ' + data.activites_supprimees + ' activité(s) supprimée(s).';
                status.className   = 'purge-status purge-ok';
                const fmt = n => n + ' entrée' + (n > 1 ? 's' : '');
                document.getElementById('rgpd-nb-connexions').textContent = fmt(data.connexions_restantes);
                document.getElementById('rgpd-nb-activites').textContent  = fmt(data.activites_restantes);
                if (data.date_purge) {
                    const d = new Date(data.date_purge.replace(' ', 'T'));
                    document.getElementById('rgpd-last-purge').textContent =
                        d.toLocaleDateString('fr-FR') + ' à ' +
                        String(d.getHours()).padStart(2,'0') + 'h' + String(d.getMinutes()).padStart(2,'0');
                }
            } else {
                status.textContent = '❌ ' + (data.error || 'Erreur inconnue');
                status.className   = 'purge-status purge-err';
            }
        } catch {
            status.textContent = '❌ Erreur réseau.';
            status.className   = 'purge-status purge-err';
        } finally {
            btn.disabled    = false;
            btn.textContent = '🗑 Purger maintenant';
        }
    }
    </script>
</body>
</html>
