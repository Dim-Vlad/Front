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
    } catch (Exception) { return $default; }
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$adresse1     = gs_param($pdo, 'club_adresse_ligne1', '34 Allée des Bleuets');
$adresse2     = gs_param($pdo, 'club_adresse_ligne2', '83 190 Ollioules');
$email        = gs_param($pdo, 'club_email',          'dimitrigarrigues@gmail.com');
$logoClub     = gs_param($pdo, 'logo_club',           'images/logo-club/LogoVBO.png');
$logoMenu     = gs_param($pdo, 'logo_menu',           'images/logo-club/Logo-VBO-blanc.png');
$logoFavicon  = gs_param($pdo, 'logo_favicon',        'images/favicon-36x36.png');
$tarifKm      = gs_param($pdo, 'remboursement_tarif_km', '0.14');

try {
    $villes = $pdo->query('SELECT * FROM remboursement_villes ORDER BY nom ASC')->fetchAll();
} catch (Exception) {
    $villes = [];
}

try {
    $villesAttente = $pdo->query(
        "SELECT nom,
                COUNT(*) AS nb,
                GROUP_CONCAT(DISTINCT distance_declaree ORDER BY created_at DESC SEPARATOR ', ') AS distances,
                GROUP_CONCAT(DISTINCT demandeur ORDER BY created_at DESC SEPARATOR ', ') AS demandeurs,
                MAX(created_at) AS derniere_demande
         FROM remboursement_villes_attente
         GROUP BY nom
         ORDER BY derniere_demande DESC"
    )->fetchAll();
    // Distance déclarée la plus récente, pour pré-remplir le formulaire d'ajout
    // (le GROUP_CONCAT ci-dessus est déjà trié de la plus récente à la plus ancienne).
    foreach ($villesAttente as &$va) {
        $va['derniere_distance'] = explode(', ', $va['distances'])[0];
    }
    unset($va);
} catch (Exception) {
    $villesAttente = [];
}

try {
    $reseaux = $pdo->query('SELECT * FROM reseaux_sociaux ORDER BY ordre ASC, id ASC')->fetchAll();
} catch (Exception) {
    $reseaux = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du site - VBO</title>
    <link href="/css/styles.css?v=20261005" rel="stylesheet">
    <link href="/css/tableau-de-bord.css?v=20260623" rel="stylesheet">
    <link href="/css/admin.css?v=20261022" rel="stylesheet">
    <link href="/css/admin/gestion-site.css?v=20261021" rel="stylesheet">
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

        <!-- ── Logos du site ── -->
        <div class="admin-card">
            <h2>Logos du site</h2>
            <p class="gs-desc">Ces logos apparaissent respectivement dans l'en-tête des pages, la barre de menu et l'onglet du navigateur.</p>
            <div class="gs-logo-grid">
                <div class="gs-logo-item">
                    <div class="gs-logo-preview">
                        <img id="gs-preview-logo_club" src="/<?= h($logoClub) ?>" alt="Logo des pages">
                    </div>
                    <p class="gs-logo-item-title">Logo des pages</p>
                    <button type="button" class="btn-admin btn-admin--sm" onclick="openLogoPicker('logo_club')">Changer</button>
                </div>
                <div class="gs-logo-item">
                    <div class="gs-logo-preview">
                        <img id="gs-preview-logo_menu" src="/<?= h($logoMenu) ?>" alt="Logo du menu">
                    </div>
                    <p class="gs-logo-item-title">Logo du menu</p>
                    <button type="button" class="btn-admin btn-admin--sm" onclick="openLogoPicker('logo_menu')">Changer</button>
                </div>
                <div class="gs-logo-item">
                    <div class="gs-logo-preview">
                        <img id="gs-preview-logo_favicon" src="/<?= h($logoFavicon) ?>" alt="Favicon">
                    </div>
                    <p class="gs-logo-item-title">Favicon</p>
                    <button type="button" class="btn-admin btn-admin--sm" onclick="openLogoPicker('logo_favicon')">Changer</button>
                </div>
            </div>
        </div>

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
                    <div class="form-group gs-email-group">
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
            <p class="gs-desc">Ajoutez, modifiez ou supprimez les réseaux sociaux affichés dans le pied de page.</p>

            <div class="gs-reseaux-list" id="gs-reseaux-list">
                <?php foreach ($reseaux as $r): ?>
                <div class="gs-reseau-row" data-id="<?= (int)$r['id'] ?>">
                    <?php if ($r['logo']): ?>
                    <img src="<?= h($r['logo']) ?>" alt="<?= h($r['nom']) ?>" class="gs-reseau-logo">
                    <?php else: ?>
                    <span class="gs-reseau-logo gs-reseau-logo--fallback"><?= h(strtoupper(substr($r['nom'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <div class="gs-reseau-info">
                        <span class="gs-reseau-nom"><?= h($r['nom']) ?></span>
                        <span class="gs-reseau-url"><?= h($r['url']) ?></span>
                    </div>
                    <div class="gs-reseau-actions">
                        <button type="button" class="btn-icon" onclick='openReseauModal(<?= h(json_encode($r)) ?>)' title="Modifier">✏️</button>
                        <button type="button" class="btn-icon" onclick="deleteReseau(<?= (int)$r['id'] ?>, <?= h(json_encode($r['nom'])) ?>)" title="Supprimer">🗑️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="gs-form-footer">
                <button type="button" class="btn-admin" onclick="openReseauModal()">+ Ajouter un réseau</button>
            </div>
        </div>

        <!-- ── Tarif kilométrique (remboursement) ── -->
        <div class="admin-card">
            <h2>Tarif kilométrique (remboursement)</h2>
            <p class="gs-desc">Montant appliqué par kilomètre pour les demandes de remboursement de frais de déplacement.</p>
            <form class="admin-form gs-form" data-section="tarif-km">
                <div class="form-row">
                    <div class="form-group">
                        <label for="remboursement_tarif_km">Tarif par km (€)</label>
                        <input type="number" id="remboursement_tarif_km" name="remboursement_tarif_km"
                               value="<?= h($tarifKm) ?>" min="0.01" max="10" step="0.01" required>
                    </div>
                </div>
                <div class="gs-form-footer">
                    <button type="submit" class="btn-admin">Enregistrer</button>
                </div>
            </form>
        </div>

        <!-- ── Villes & distances (remboursement kilométrique) ── -->
        <div class="admin-card">
            <h2>Villes &amp; distances (remboursement)</h2>
            <p class="gs-desc">Villes proposées sur le formulaire de remboursement kilométrique, avec leur distance depuis Ollioules (aller simple).</p>

            <?php if (!empty($villesAttente)): ?>
            <div class="gs-sub-section" id="gs-villes-attente">
                <h3 class="gs-subheading gs-subheading--warning">⚠️ À vérifier</h3>
                <p class="gs-desc gs-desc--sub">Ces villes ont été saisies sur une demande de remboursement sans figurer dans la liste ci-dessous : vérifiez la distance déclarée par le demandeur, corrigez-la si besoin, puis ajoutez-la à la liste officielle.</p>

                <div class="gs-villes-list" id="gs-villes-attente-list">
                    <?php foreach ($villesAttente as $va): ?>
                    <div class="gs-ville-attente-row" data-nom="<?= h($va['nom']) ?>">
                        <div class="gs-ville-info">
                            <span class="gs-ville-nom"><?= h($va['nom']) ?></span>
                            <span class="gs-ville-distance">
                                <?= $va['nb'] > 1 ? (int)$va['nb'] . '× demandée' : '1× demandée' ?>
                                — distance<?= strpos($va['distances'], ',') !== false ? 's' : '' ?> déclarée<?= strpos($va['distances'], ',') !== false ? 's' : '' ?> : <?= h($va['distances']) ?> km
                            </span>
                            <span class="gs-ville-attente-demandeurs">Par : <?= h($va['demandeurs']) ?></span>
                        </div>
                        <div class="gs-reseau-actions">
                            <button type="button" class="btn-icon" onclick="openVilleAttenteModal(<?= h(json_encode($va['nom'])) ?>, <?= h(json_encode($va['derniere_distance'])) ?>)" title="Modifier la demande">✏️</button>
                            <button type="button" class="btn-icon" onclick="openVilleModal({ nom: <?= h(json_encode($va['nom'])) ?>, distance_km: <?= h(json_encode($va['derniere_distance'])) ?> })" title="Ajouter à la liste officielle">➕</button>
                            <button type="button" class="btn-icon" onclick="dismissVilleAttente(<?= h(json_encode($va['nom'])) ?>)" title="Ignorer">🗑️</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <hr class="gs-divider">
            <h3 class="gs-subheading">Liste officielle</h3>
            <?php endif; ?>

            <div class="gs-villes-list" id="gs-villes-list">
                <?php foreach ($villes as $v): ?>
                <div class="gs-ville-row" data-id="<?= (int)$v['id'] ?>">
                    <div class="gs-ville-info">
                        <span class="gs-ville-nom"><?= h($v['nom']) ?></span>
                        <span class="gs-ville-distance"><?= h(rtrim(rtrim(number_format((float)$v['distance_km'], 1, ',', ''), '0'), ',')) ?> km</span>
                    </div>
                    <div class="gs-reseau-actions">
                        <button type="button" class="btn-icon" onclick='openVilleModal(<?= h(json_encode($v)) ?>)' title="Modifier">✏️</button>
                        <button type="button" class="btn-icon" onclick="deleteVille(<?= (int)$v['id'] ?>, <?= h(json_encode($v['nom'])) ?>)" title="Supprimer">🗑️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="gs-form-footer">
                <button type="button" class="btn-admin" onclick="openVilleModal()">+ Ajouter une ville</button>
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
                    <span class="gs-tech-value">FileZilla ou client FTP · hôte : <code>ftp.volleyballollioulais.fr</code></span>
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

    <!-- ── Modale : sélection de logo ── -->
    <div class="modal-overlay" id="logo-picker-modal" style="display:none" onclick="if (event.target === this) closeLogoPicker()">
        <div class="modal-card gs-logo-modal">
            <h3 id="lp-title">Changer le logo</h3>
            <p class="gs-desc">Choisissez un logo existant ou importez une nouvelle image.</p>
            <div class="lm-grid" id="lp-grid"><p class="lm-empty">Chargement…</p></div>
            <p class="lm-section-title">Importer un nouveau logo</p>
            <div class="lm-upload-zone" onclick="document.getElementById('lp-file-input').click()">
                <div class="lm-upload-label">📁 Cliquer pour choisir une image</div>
                <p>JPG · PNG · WEBP · GIF — max 3 Mo</p>
                <img id="lp-upload-preview" alt="Aperçu du logo">
                <input type="file" id="lp-file-input" accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
            <div class="modal-actions">
                <span class="lm-status" id="lp-status"></span>
                <button type="button" class="btn-cancel" onclick="closeLogoPicker()">Annuler</button>
                <button type="button" class="btn-admin" onclick="saveLogoPicker()">Appliquer</button>
            </div>
        </div>
    </div>

    <!-- ── Modale : ajouter / modifier un réseau ── -->
    <div class="modal-overlay" id="reseau-modal" style="display:none" onclick="if (event.target === this) closeReseauModal()">
        <div class="modal-card">
            <h3 id="reseau-modal-title">Ajouter un réseau</h3>
            <form id="reseau-form" class="admin-form">
                <input type="hidden" id="reseau-id" name="id" value="">
                <div class="form-group">
                    <label for="reseau-nom">Nom</label>
                    <input type="text" id="reseau-nom" name="nom" placeholder="ex : TikTok" required>
                </div>
                <div class="form-group">
                    <label for="reseau-url">Lien</label>
                    <input type="url" id="reseau-url" name="url" placeholder="https://..." required>
                </div>
                <div class="form-group">
                    <label>Logo</label>
                    <div class="lm-upload-zone" onclick="document.getElementById('reseau-logo-file').click()">
                        <div class="lm-upload-label">📁 Cliquer pour choisir une image</div>
                        <p>JPG · PNG · WEBP · GIF</p>
                        <img id="reseau-logo-preview" alt="Aperçu du logo">
                        <input type="file" id="reseau-logo-file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>
                </div>
                <div class="modal-actions">
                    <span class="lm-status" id="reseau-status"></span>
                    <button type="button" class="btn-cancel" onclick="closeReseauModal()">Annuler</button>
                    <button type="submit" class="btn-admin">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Modale : ajouter / modifier une ville (remboursement) ── -->
    <div class="modal-overlay" id="ville-modal" style="display:none" onclick="if (event.target === this) closeVilleModal()">
        <div class="modal-card">
            <h3 id="ville-modal-title">Ajouter une ville</h3>
            <form id="ville-form" class="admin-form">
                <input type="hidden" id="ville-id" name="id" value="">
                <div class="form-group">
                    <label for="ville-nom">Ville</label>
                    <input type="text" id="ville-nom" name="nom" placeholder="ex : Toulon" required>
                </div>
                <div class="form-group">
                    <label for="ville-distance">Distance aller depuis Ollioules (km)</label>
                    <input type="number" id="ville-distance" name="distance_km" min="0.1" step="0.1" required>
                </div>
                <div class="modal-actions">
                    <span class="lm-status" id="ville-status"></span>
                    <button type="button" class="btn-cancel" onclick="closeVilleModal()">Annuler</button>
                    <button type="submit" class="btn-admin">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Modale : corriger une demande en attente (nom / distance déclarée) ── -->
    <div class="modal-overlay" id="ville-attente-modal" style="display:none" onclick="if (event.target === this) closeVilleAttenteModal()">
        <div class="modal-card">
            <h3>Modifier la demande</h3>
            <form id="ville-attente-form" class="admin-form">
                <input type="hidden" id="va-nom-actuel" name="nom_actuel" value="">
                <div class="form-group">
                    <label for="va-nom">Ville</label>
                    <input type="text" id="va-nom" name="nom" required>
                </div>
                <div class="form-group">
                    <label for="va-distance">Distance aller déclarée (km)</label>
                    <input type="number" id="va-distance" name="distance_km" min="0.1" step="0.1" required>
                </div>
                <div class="modal-actions">
                    <span class="lm-status" id="va-status"></span>
                    <button type="button" class="btn-cancel" onclick="closeVilleAttenteModal()">Annuler</button>
                    <button type="submit" class="btn-admin">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script>

        const alert = document.getElementById('gs-alert');

        function showAlert(msg, ok) {
            alert.textContent = msg;
            alert.className   = 'admin-alert ' + (ok ? 'admin-alert--success' : 'admin-alert--error');
            alert.style.display = 'block';
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

        /* ── Fond du cadre d'aperçu adapté à la luminosité du logo ──
           Un logo clair (texte/trait blanc) devient invisible sur fond blanc :
           on calcule la luminosité moyenne des pixels opaques et on bascule
           automatiquement vers un fond sombre si besoin. */

        function analyzeLogoBrightness(img, callback) {
            try {
                const w = img.naturalWidth, h = img.naturalHeight;
                if (!w || !h) { callback(null); return; }
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                const data = ctx.getImageData(0, 0, w, h).data;
                let total = 0, count = 0;
                for (let i = 0; i < data.length; i += 4) {
                    if (data[i + 3] < 40) continue; // pixel quasi transparent : ignoré
                    total += 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];
                    count++;
                }
                callback(count ? total / count : null);
            } catch (e) {
                callback(null); // image illisible (erreur canvas) : on garde le fond par défaut
            }
        }

        // Bascule la classe `darkClass` sur `el` selon la luminosité de `img`,
        // une fois l'image chargée (ou immédiatement si elle l'est déjà).
        function autoBgForImage(img, el, darkClass) {
            const run = () => {
                analyzeLogoBrightness(img, avgLum => {
                    if (avgLum === null) return;
                    el.classList.toggle(darkClass, avgLum > 150);
                });
            };
            if (img.complete && img.naturalWidth) run();
            else img.addEventListener('load', run, { once: true });
        }

        function applyLogoPreviewBg(target) {
            const img = document.getElementById('gs-preview-' + target);
            if (!img) return;
            autoBgForImage(img, img.closest('.gs-logo-preview'), 'gs-logo-preview--dark');
        }

        ['logo_club', 'logo_menu', 'logo_favicon'].forEach(applyLogoPreviewBg);

        /* ── Sélecteur de logo (3 cibles : logo_club, logo_menu, logo_favicon) ── */

        const LOGO_TITLES = {
            logo_club:    'Logo des pages',
            logo_menu:    'Logo du menu',
            logo_favicon: 'Favicon',
        };
        let lpTarget         = null;
        let lpSelectedPath   = null;

        function openLogoPicker(target) {
            lpTarget       = target;
            lpSelectedPath = null;
            document.getElementById('lp-title').textContent = 'Changer : ' + LOGO_TITLES[target];
            const status = document.getElementById('lp-status');
            status.textContent = '';
            status.className   = 'lm-status';
            document.getElementById('lp-file-input').value = '';
            document.getElementById('lp-upload-preview').style.display = 'none';
            document.getElementById('logo-picker-modal').style.display = 'flex';

            const grid = document.getElementById('lp-grid');
            grid.innerHTML = '<p class="lm-empty">Chargement…</p>';

            const currentSrc = document.getElementById('gs-preview-' + target).getAttribute('src').replace(/^\//, '');

            fetch('/php/logo/list_logos.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.logos.length) {
                        grid.innerHTML = '<p class="lm-empty">Aucun logo trouvé</p>';
                        return;
                    }
                    grid.innerHTML = '';
                    data.logos.forEach(path => {
                        const isActive = (path === currentSrc);
                        const item = document.createElement('div');
                        item.className = 'lm-logo-item' + (isActive ? ' active' : '');
                        if (isActive) lpSelectedPath = path;

                        const name = path.split('/').pop();
                        item.innerHTML = `<img src="/${path}" alt="${name}" loading="lazy"><span>${name}</span>`;
                        item.addEventListener('click', () => {
                            document.querySelectorAll('#lp-grid .lm-logo-item').forEach(i => i.classList.remove('active'));
                            item.classList.add('active');
                            lpSelectedPath = path;
                            document.getElementById('lp-file-input').value = '';
                            document.getElementById('lp-upload-preview').style.display = 'none';
                        });
                        grid.appendChild(item);
                        autoBgForImage(item.querySelector('img'), item, 'lm-logo-item--dark');
                    });
                })
                .catch(() => { grid.innerHTML = '<p class="lm-empty">Erreur de chargement</p>'; });
        }

        function closeLogoPicker() {
            document.getElementById('logo-picker-modal').style.display = 'none';
        }

        document.getElementById('lp-file-input').addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const preview = document.getElementById('lp-upload-preview');
            preview.src           = URL.createObjectURL(file);
            preview.style.display = 'block';
            preview.classList.remove('lp-upload-preview--dark');
            autoBgForImage(preview, preview, 'lp-upload-preview--dark');
            document.querySelectorAll('#lp-grid .lm-logo-item').forEach(i => i.classList.remove('active'));
            lpSelectedPath = null;
        });

        async function saveLogoPicker() {
            const status    = document.getElementById('lp-status');
            const fileInput = document.getElementById('lp-file-input');
            const fd        = new FormData();
            fd.append('target', lpTarget);

            if (fileInput.files[0]) {
                fd.append('logo_upload', fileInput.files[0]);
            } else if (lpSelectedPath) {
                fd.append('logo_path', lpSelectedPath);
            } else {
                status.textContent = 'Sélectionnez un logo ou importez une image.';
                status.className   = 'lm-status error';
                return;
            }

            status.textContent = 'Enregistrement…';
            status.className   = 'lm-status';

            try {
                const res  = await fetch('/php/logo/change_logo.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('gs-preview-' + lpTarget).src = '/' + data.logo;
                    applyLogoPreviewBg(lpTarget);
                    status.textContent = 'Logo mis à jour !';
                    status.className   = 'lm-status success';
                    setTimeout(closeLogoPicker, 1200);
                } else {
                    status.textContent = data.error || 'Erreur inconnue';
                    status.className   = 'lm-status error';
                }
            } catch {
                status.textContent = 'Erreur réseau';
                status.className   = 'lm-status error';
            }
        }

        /* ── Réseaux sociaux (ajout / édition / suppression) ── */

        function openReseauModal(data) {
            document.getElementById('reseau-form').reset();
            document.getElementById('reseau-logo-preview').style.display = 'none';
            document.getElementById('reseau-status').textContent = '';
            document.getElementById('reseau-status').className   = 'lm-status';

            if (data) {
                document.getElementById('reseau-modal-title').textContent = 'Modifier un réseau';
                document.getElementById('reseau-id').value  = data.id;
                document.getElementById('reseau-nom').value = data.nom;
                document.getElementById('reseau-url').value = data.url;
                if (data.logo) {
                    const preview = document.getElementById('reseau-logo-preview');
                    preview.src           = data.logo;
                    preview.style.display = 'block';
                }
            } else {
                document.getElementById('reseau-modal-title').textContent = 'Ajouter un réseau';
                document.getElementById('reseau-id').value = '';
            }
            document.getElementById('reseau-modal').style.display = 'flex';
        }

        function closeReseauModal() {
            document.getElementById('reseau-modal').style.display = 'none';
        }

        document.getElementById('reseau-logo-file').addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const preview = document.getElementById('reseau-logo-preview');
            preview.src           = URL.createObjectURL(file);
            preview.style.display = 'block';
        });

        document.getElementById('reseau-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const status = document.getElementById('reseau-status');
            const btn    = this.querySelector('button[type="submit"]');
            const id     = document.getElementById('reseau-id').value;
            const url    = id ? '/php/reseaux/update_reseau.php' : '/php/reseaux/add_reseau.php';

            status.textContent = '';
            btn.disabled = true;

            try {
                const res  = await fetch(url, { method: 'POST', body: new FormData(this) });
                const data = await res.json();
                if (data.success) {
                    renderReseauRow(data.data, !id);
                    closeReseauModal();
                    showAlert('✓ Réseau enregistré.', true);
                } else {
                    status.textContent = data.error || 'Erreur inconnue';
                    status.className   = 'lm-status error';
                }
            } catch {
                status.textContent = 'Erreur réseau.';
                status.className   = 'lm-status error';
            } finally {
                btn.disabled = false;
            }
        });

        function renderReseauRow(data, isNew) {
            const list = document.getElementById('gs-reseaux-list');
            let row = list.querySelector(`.gs-reseau-row[data-id="${data.id}"]`);
            if (!row) {
                row = document.createElement('div');
                row.className = 'gs-reseau-row';
                list.appendChild(row);
            }
            row.dataset.id = data.id;
            const logoHtml = data.logo
                ? `<img src="${escHtml(data.logo)}" alt="${escHtml(data.nom)}" class="gs-reseau-logo">`
                : `<span class="gs-reseau-logo gs-reseau-logo--fallback">${escHtml((data.nom || '?').charAt(0).toUpperCase())}</span>`;
            row.innerHTML = `
                ${logoHtml}
                <div class="gs-reseau-info">
                    <span class="gs-reseau-nom">${escHtml(data.nom)}</span>
                    <span class="gs-reseau-url">${escHtml(data.url)}</span>
                </div>
                <div class="gs-reseau-actions">
                    <button type="button" class="btn-icon" title="Modifier">✏️</button>
                    <button type="button" class="btn-icon" title="Supprimer">🗑️</button>
                </div>
            `;
            row.querySelector('[title="Modifier"]').addEventListener('click', () => openReseauModal(data));
            row.querySelector('[title="Supprimer"]').addEventListener('click', () => deleteReseau(data.id, data.nom));
        }

        async function deleteReseau(id, nom) {
            if (!confirm(`Supprimer le réseau « ${nom} » ?`)) return;
            const fd = new FormData();
            fd.append('id', id);
            try {
                const res  = await fetch('/php/reseaux/delete_reseau.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    document.querySelector(`.gs-reseau-row[data-id="${id}"]`)?.remove();
                    showAlert('✓ Réseau supprimé.', true);
                } else {
                    showAlert('✗ ' + (data.error || 'Erreur inconnue.'), false);
                }
            } catch {
                showAlert('✗ Erreur réseau.', false);
            }
        }

        /* ── Villes & distances (remboursement kilométrique) ──
           data.id présent : modification d'une ville existante.
           data sans id (ou absent) : ajout, avec éventuellement nom/distance
           pré-remplis (ex : depuis une alerte "ville à vérifier"). */

        function openVilleModal(data) {
            document.getElementById('ville-form').reset();
            document.getElementById('ville-status').textContent = '';
            document.getElementById('ville-status').className   = 'lm-status';

            if (data && data.id) {
                document.getElementById('ville-modal-title').textContent = 'Modifier une ville';
                document.getElementById('ville-id').value       = data.id;
                document.getElementById('ville-nom').value      = data.nom;
                document.getElementById('ville-distance').value = data.distance_km;
            } else {
                document.getElementById('ville-modal-title').textContent = 'Ajouter une ville';
                document.getElementById('ville-id').value       = '';
                document.getElementById('ville-nom').value      = data ? data.nom : '';
                document.getElementById('ville-distance').value = data ? data.distance_km : '';
            }
            document.getElementById('ville-modal').style.display = 'flex';
        }

        function closeVilleModal() {
            document.getElementById('ville-modal').style.display = 'none';
        }

        document.getElementById('ville-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const status = document.getElementById('ville-status');
            const btn    = this.querySelector('button[type="submit"]');
            const id     = document.getElementById('ville-id').value;
            const url    = id ? '/php/remboursement/update_ville.php' : '/php/remboursement/add_ville.php';

            status.textContent = '';
            btn.disabled = true;

            try {
                const res  = await fetch(url, { method: 'POST', body: new FormData(this) });
                const data = await res.json();
                if (data.success) {
                    renderVilleRow(data.data);
                    if (!id) removeVilleAttenteRow(data.data.nom);
                    closeVilleModal();
                    showAlert('✓ Ville enregistrée.', true);
                } else {
                    status.textContent = data.error || 'Erreur inconnue';
                    status.className   = 'lm-status error';
                }
            } catch {
                status.textContent = 'Erreur réseau.';
                status.className   = 'lm-status error';
            } finally {
                btn.disabled = false;
            }
        });

        function renderVilleRow(data) {
            const list = document.getElementById('gs-villes-list');
            let row = list.querySelector(`.gs-ville-row[data-id="${data.id}"]`);
            if (!row) {
                row = document.createElement('div');
                row.className = 'gs-ville-row';
                list.appendChild(row);
            }
            row.dataset.id = data.id;
            const distanceAffichee = parseFloat(data.distance_km).toString().replace('.', ',');
            row.innerHTML = `
                <div class="gs-ville-info">
                    <span class="gs-ville-nom">${escHtml(data.nom)}</span>
                    <span class="gs-ville-distance">${escHtml(distanceAffichee)} km</span>
                </div>
                <div class="gs-reseau-actions">
                    <button type="button" class="btn-icon" title="Modifier">✏️</button>
                    <button type="button" class="btn-icon" title="Supprimer">🗑️</button>
                </div>
            `;
            row.querySelector('[title="Modifier"]').addEventListener('click', () => openVilleModal(data));
            row.querySelector('[title="Supprimer"]').addEventListener('click', () => deleteVille(data.id, data.nom));
            // Garde la liste triée alphabétiquement après un ajout.
            [...list.children].sort((a, b) => a.querySelector('.gs-ville-nom').textContent.localeCompare(b.querySelector('.gs-ville-nom').textContent))
                .forEach(el => list.appendChild(el));
        }

        async function deleteVille(id, nom) {
            if (!confirm(`Supprimer la ville « ${nom} » de la liste ?`)) return;
            const fd = new FormData();
            fd.append('id', id);
            try {
                const res  = await fetch('/php/remboursement/delete_ville.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    document.querySelector(`.gs-ville-row[data-id="${id}"]`)?.remove();
                    showAlert('✓ Ville supprimée.', true);
                } else {
                    showAlert('✗ ' + (data.error || 'Erreur inconnue.'), false);
                }
            } catch {
                showAlert('✗ Erreur réseau.', false);
            }
        }

        /* ── Villes à vérifier (demandées hors liste) ── */

        async function dismissVilleAttente(nom) {
            if (!confirm(`Ignorer l'alerte pour « ${nom} » ? Elle réapparaîtra si cette ville est redemandée.`)) return;
            const fd = new FormData();
            fd.append('nom', nom);
            try {
                const res  = await fetch('/php/remboursement/dismiss_ville_attente.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    removeVilleAttenteRow(nom);
                    showAlert('✓ Alerte ignorée.', true);
                } else {
                    showAlert('✗ ' + (data.error || 'Erreur inconnue.'), false);
                }
            } catch {
                showAlert('✗ Erreur réseau.', false);
            }
        }

        function removeVilleAttenteRow(nom) {
            const row = document.querySelector(`.gs-ville-attente-row[data-nom="${CSS.escape(nom)}"]`);
            row?.remove();
            const list = document.getElementById('gs-villes-attente-list');
            if (list && list.children.length === 0) {
                document.getElementById('gs-villes-attente')?.remove();
                document.querySelector('.gs-divider')?.remove();
                document.querySelectorAll('.gs-subheading').forEach(el => el.remove());
            }
        }

        function openVilleAttenteModal(nom, distance) {
            document.getElementById('ville-attente-form').reset();
            document.getElementById('va-status').textContent = '';
            document.getElementById('va-status').className   = 'lm-status';
            document.getElementById('va-nom-actuel').value = nom;
            document.getElementById('va-nom').value        = nom;
            document.getElementById('va-distance').value   = distance;
            document.getElementById('ville-attente-modal').style.display = 'flex';
        }

        function closeVilleAttenteModal() {
            document.getElementById('ville-attente-modal').style.display = 'none';
        }

        document.getElementById('ville-attente-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const status = document.getElementById('va-status');
            const btn    = this.querySelector('button[type="submit"]');

            status.textContent = '';
            btn.disabled = true;

            try {
                const res  = await fetch('/php/remboursement/update_ville_attente.php', { method: 'POST', body: new FormData(this) });
                const data = await res.json();
                if (data.success) {
                    renderVilleAttenteRow(data.data);
                    closeVilleAttenteModal();
                    showAlert('✓ Demande corrigée.', true);
                } else {
                    status.textContent = data.error || 'Erreur inconnue';
                    status.className   = 'lm-status error';
                }
            } catch {
                status.textContent = 'Erreur réseau.';
                status.className   = 'lm-status error';
            } finally {
                btn.disabled = false;
            }
        });

        function renderVilleAttenteRow(data) {
            const row = document.querySelector(`.gs-ville-attente-row[data-nom="${CSS.escape(data.nom_actuel)}"]`);
            if (!row) return;
            row.dataset.nom = data.nom;
            const distanceAffichee = parseFloat(data.distance_km).toString().replace('.', ',');
            row.querySelector('.gs-ville-nom').textContent = data.nom;
            row.querySelector('.gs-ville-distance').innerHTML =
                (data.nb > 1 ? `${data.nb}× demandée` : '1× demandée') + ` — distance déclarée : ${escHtml(distanceAffichee)} km`;
            row.querySelector('.gs-ville-attente-demandeurs').textContent = `Par : ${data.demandeurs}`;
            row.querySelector('[title="Modifier la demande"]').setAttribute('onclick', `openVilleAttenteModal(${JSON.stringify(data.nom)}, ${JSON.stringify(data.distance_km)})`);
            row.querySelector('[title="Ajouter à la liste officielle"]').setAttribute('onclick', `openVilleModal({ nom: ${JSON.stringify(data.nom)}, distance_km: ${JSON.stringify(data.distance_km)} })`);
            row.querySelector('[title="Ignorer"]').setAttribute('onclick', `dismissVilleAttente(${JSON.stringify(data.nom)})`);
        }

        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    </script>
</body>
</html>
