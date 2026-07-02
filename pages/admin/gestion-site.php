<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_role('admin')) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$user = current_user();
$pdo  = get_pdo();

function gs_param(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['valeur'] : $default;
    } catch (Exception $e) { return $default; }
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$adresse1 = gs_param($pdo, 'club_adresse_ligne1', '34 Allée des Bleuets');
$adresse2 = gs_param($pdo, 'club_adresse_ligne2', '83 190 Ollioules');
$email    = gs_param($pdo, 'club_email',           'dimitrigarrigues@gmail.com');
$facebook = gs_param($pdo, 'social_facebook',      'https://www.facebook.com/volley.vbollioulais');
$instagram= gs_param($pdo, 'social_instagram',     'https://www.instagram.com/volley.ball.ollioulais/');
$youtube  = gs_param($pdo, 'social_youtube',       'https://www.youtube.com/@VolleyBallOllioulais');
$arbSheet = gs_param($pdo, 'arbitres_sheet_url',   '');
$minibus  = gs_param($pdo, 'minibus_sheet_url',    '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du site - VBO</title>
    <link href="/css/styles.css?v=20260702" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link href="/css/admin.css?v=20260702" rel="stylesheet">
    <link href="/css/admin/gestion-site.css?v=20260702" rel="stylesheet">
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
                <h1>Gestion du site</h1>
                <p>Paramètres du site modifiables par l'administrateur</p>
            </div>
        </div>
    </div>

    <div class="admin-container">

        <div id="gs-alert" class="admin-alert" style="display:none"></div>

        <!-- ── Pied de page ── -->
        <div class="admin-card">
            <h2>Pied de page</h2>
            <p class="gs-desc">Ces informations apparaissent dans le bas de chaque page du site.</p>
            <form class="admin-form gs-form" data-section="footer">
                <div class="form-row">
                    <div class="form-group">
                        <label for="club_adresse_ligne1">Adresse (ligne 1)</label>
                        <input type="text" id="club_adresse_ligne1" name="club_adresse_ligne1"
                               value="<?= h($adresse1) ?>" placeholder="34 Allée des Bleuets">
                    </div>
                    <div class="form-group">
                        <label for="club_adresse_ligne2">Adresse (ligne 2)</label>
                        <input type="text" id="club_adresse_ligne2" name="club_adresse_ligne2"
                               value="<?= h($adresse2) ?>" placeholder="83 190 Ollioules">
                    </div>
                </div>
                <div class="gs-form-footer">
                    <button type="submit" class="btn-admin">Enregistrer</button>
                </div>
            </form>
        </div>

        <!-- ── Email de contact ── -->
        <div class="admin-card">
            <h2>Email de contact</h2>
            <p class="gs-desc">Les messages envoyés via le formulaire de contact seront transmis à cette adresse.</p>
            <form class="admin-form gs-form" data-section="email">
                <div class="form-row">
                    <div class="form-group" style="max-width:440px">
                        <label for="club_email">Adresse email de réception</label>
                        <input type="email" id="club_email" name="club_email"
                               value="<?= h($email) ?>" placeholder="contact@votreclub.fr">
                    </div>
                </div>
                <div class="gs-form-footer">
                    <button type="submit" class="btn-admin">Enregistrer</button>
                </div>
            </form>
        </div>

        <!-- ── Réseaux sociaux ── -->
        <div class="admin-card">
            <h2>Réseaux sociaux</h2>
            <p class="gs-desc">Les liens vers vos pages Facebook, Instagram et YouTube apparaissent dans le pied de page.</p>
            <form class="admin-form gs-form" data-section="reseaux">
                <div class="form-row">
                    <div class="form-group">
                        <label for="social_facebook">
                            <span class="gs-social-icon">📘</span> Facebook
                        </label>
                        <input type="url" id="social_facebook" name="social_facebook"
                               value="<?= h($facebook) ?>" placeholder="https://www.facebook.com/votreclub">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="social_instagram">
                            <span class="gs-social-icon">📷</span> Instagram
                        </label>
                        <input type="url" id="social_instagram" name="social_instagram"
                               value="<?= h($instagram) ?>" placeholder="https://www.instagram.com/votreclub/">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="social_youtube">
                            <span class="gs-social-icon">▶️</span> YouTube
                        </label>
                        <input type="url" id="social_youtube" name="social_youtube"
                               value="<?= h($youtube) ?>" placeholder="https://www.youtube.com/@votreclub">
                    </div>
                </div>
                <div class="gs-form-footer">
                    <button type="submit" class="btn-admin">Enregistrer</button>
                </div>
            </form>
        </div>

        <!-- ── Liens externes ── -->
        <div class="admin-card">
            <h2>Liens externes</h2>
            <p class="gs-desc">Liens vers les Google Sheets utilisés dans les pages Arbitres et Minibus.</p>
            <form class="admin-form gs-form" data-section="liens">
                <div class="form-row">
                    <div class="form-group">
                        <label for="arbitres_sheet_url">URL Google Sheets — Arbitres</label>
                        <input type="url" id="arbitres_sheet_url" name="arbitres_sheet_url"
                               value="<?= h($arbSheet) ?>" placeholder="https://docs.google.com/spreadsheets/d/…">
                        <span class="hint">Lien de partage public du tableau des désignations arbitres</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="minibus_sheet_url">URL Google Sheets — Minibus</label>
                        <input type="url" id="minibus_sheet_url" name="minibus_sheet_url"
                               value="<?= h($minibus) ?>" placeholder="https://docs.google.com/spreadsheets/d/…">
                        <span class="hint">Lien de partage public du planning minibus</span>
                    </div>
                </div>
                <div class="gs-form-footer">
                    <button type="submit" class="btn-admin">Enregistrer</button>
                </div>
            </form>
        </div>

        <!-- ── Logo du club ── -->
        <div class="admin-card">
            <h2>Logo du club</h2>
            <p class="gs-desc">Le logo s'affiche dans la barre de navigation et sur chaque page. Cliquez sur le logo pour le modifier.</p>
            <div class="gs-logo-preview">
                <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo actuel du club">
                <p class="hint">Survolez le logo et cliquez sur "Changer le logo"<br>(disponible pour les administrateurs et modérateurs)</p>
            </div>
        </div>

        <!-- ── Informations techniques ── -->
        <div class="admin-card gs-tech-card">
            <h2>Informations techniques</h2>
            <p class="gs-desc">Ces informations sont utiles pour la maintenance et la transmission du site à un nouveau responsable.</p>
            <div class="gs-tech-grid">
                <div class="gs-tech-item">
                    <span class="gs-tech-label">Code source (GitHub)</span>
                    <a href="https://github.com/Dim-Vlad/Front" target="_blank" rel="noopener" class="gs-tech-value gs-tech-link">
                        github.com/Dim-Vlad/Front
                    </a>
                </div>
                <div class="gs-tech-item">
                    <span class="gs-tech-label">Hébergeur</span>
                    <span class="gs-tech-value">OVH — <a href="https://www.ovhcloud.com/fr/" target="_blank" rel="noopener" class="gs-tech-link">ovhcloud.com</a></span>
                </div>
                <div class="gs-tech-item">
                    <span class="gs-tech-label">Base de données</span>
                    <span class="gs-tech-value">MySQL · phpMyAdmin disponible sur le panneau OVH</span>
                </div>
                <div class="gs-tech-item">
                    <span class="gs-tech-label">Accès FTP</span>
                    <span class="gs-tech-value">FileZilla ou client FTP · hôte : <code>ftp.votredomaine.fr</code></span>
                </div>
                <div class="gs-tech-item">
                    <span class="gs-tech-label">Développeur d'origine</span>
                    <span class="gs-tech-value">Dimitri Garrigues — <a href="mailto:dimitrigarrigues@gmail.com" class="gs-tech-link">dimitrigarrigues@gmail.com</a></span>
                </div>
            </div>
            <div class="gs-tech-notice">
                <strong>Pour transmettre le site :</strong> communiquer les identifiants OVH (hébergement + domaine), les accès FTP, les accès base de données et l'accès GitHub au nouveau responsable.
            </div>
        </div>

        <a href="/pages/admin/index.php" class="btn-back">← Retour à l'administration</a>

    </div>

    <div id="footer"></div>

    <script src="/js/main.js"></script>
    <script>
        loadHTML('/commun/menu.html', 'menu');
        loadHTML('/commun/footer.php', 'footer');

        const alert = document.getElementById('gs-alert');

        function showAlert(msg, ok) {
            alert.textContent = msg;
            alert.className   = 'admin-alert ' + (ok ? 'admin-alert--success' : 'admin-alert--error');
            alert.style.display = 'block';
            alert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => { alert.style.display = 'none'; }, 4000);
        }

        document.querySelectorAll('.gs-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                btn.disabled    = true;
                btn.textContent = 'Enregistrement…';

                try {
                    const res  = await fetch('/php/parametres/update_site.php', {
                        method: 'POST',
                        body: new FormData(this)
                    });
                    const data = await res.json();
                    if (data.success) {
                        showAlert('✓ Modifications enregistrées.', true);
                    } else {
                        showAlert('✗ ' + (data.error || 'Erreur inconnue.'), false);
                    }
                } catch {
                    showAlert('✗ Erreur réseau.', false);
                } finally {
                    btn.disabled    = false;
                    btn.textContent = 'Enregistrer';
                }
            });
        });
    </script>
</body>
</html>
