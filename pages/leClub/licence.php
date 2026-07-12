<?php
require_once __DIR__ . '/../../php/auth.php';

$canEdit = is_logged_in() && has_any_role(['moderateur', 'admin']);

$docs   = [];
$liens  = [];
$saison = '2026-2027';

$tarifsNote = '';

$defaultTarifs = [
    ['label' => 'Baby / M7',            'prix' => 140,  'supplement' => false],
    ['label' => 'M9',                   'prix' => 160, 'supplement' => false],
    ['label' => 'M11',                  'prix' => 160, 'supplement' => false],
    ['label' => 'M13',                  'prix' => 160, 'supplement' => false],
    ['label' => 'M15',                  'prix' => 160, 'supplement' => false],
    ['label' => 'M18',                  'prix' => 190, 'supplement' => false],
    ['label' => 'M21',                  'prix' => 190, 'supplement' => false],
    ['label' => 'Séniors — Compétition','prix' => 200, 'supplement' => false],
    ['label' => 'Séniors — Loisirs',   'prix' => 100, 'supplement' => false],
    ['label' => 'Séniors — Compet Lib',   'prix' => 130, 'supplement' => false],
];
$tarifs = $defaultTarifs;

$defaultTutos = [
    1 => ['titre' => '01 – Création de compte et connexion',      'url' => 'https://youtu.be/QqA1F569QJU'],
    2 => ['titre' => '02 – Création d\'un profil adulte',          'url' => 'https://youtu.be/Bh_K_WiDpsc'],
    3 => ['titre' => '03 – Création d\'une inscription adulte',    'url' => 'https://youtu.be/e-fR_nYjkg4'],
    4 => ['titre' => '04 – Création d\'un profil enfant',          'url' => 'https://youtu.be/qQRHJQZBN-o'],
    5 => ['titre' => '05 – Création d\'une inscription enfant',    'url' => 'https://youtu.be/_RGtEiADvLo'],
    6 => ['titre' => '06 – Paiement du dossier (HelloAsso)',       'url' => 'https://youtu.be/m4zzgywP_ro'],
];
$tutos = $defaultTutos;

try {
    $pdo = get_pdo();
    $docs  = $pdo->query("SELECT * FROM licence_documents ORDER BY section, ordre")->fetchAll();
    $liens = $pdo->query("SELECT * FROM licence_liens ORDER BY id")->fetchAll();
    $cfg   = $pdo->query("SELECT cle, valeur FROM licence_config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $saison = $cfg['saison'] ?? '2026-2027';
    for ($i = 1; $i <= 6; $i++) {
        if (isset($cfg["tuto_{$i}_titre"])) $tutos[$i]['titre'] = $cfg["tuto_{$i}_titre"];
        if (isset($cfg["tuto_{$i}_url"]))   $tutos[$i]['url']   = $cfg["tuto_{$i}_url"];
    }
    $cfgTarifs = $cfg['tarifs_json'] ?? '';
    if ($cfgTarifs !== '') {
        $decoded = json_decode($cfgTarifs, true);
        if (is_array($decoded) && count($decoded) > 0) $tarifs = $decoded;
    }
    $tarifsNote = $cfg['tarifs_note'] ?? '';
} catch (Exception $e) {}

// Grouper les documents par section
$docsBySection = ['licence' => [], 'medical' => []];
foreach ($docs as $d) $docsBySection[$d['section']][] = $d;

// Indexer les liens par slug
$liensBySlug = [];
foreach ($liens as $l) $liensBySlug[$l['slug']] = $l;

function youtube_id(string $url): string {
    if (preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{11})/', $url, $m)) return $m[1];
    return '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licences - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/leClub/licence.css?v=20260702" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
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
                <h1>Dossier licences</h1>
                <p>Tous les documents nécessaires pour établir votre licence.</p>
            </div>
        </div>
    </div>

    <main class="licence-main">
        <div class="licence-layout">

            <!-- ── Colonne documents ── -->
            <div class="documents-column">

                <!-- Documents licence -->
                <div class="doc-section">
                    <div class="doc-section-header">
                        <h2>Documents licence <?= htmlspecialchars($saison) ?></h2>
                        <?php if ($canEdit): ?>
                        <button class="btn-edit-saison" onclick="openSaisonModal()" title="Modifier la saison">✏️</button>
                        <?php endif; ?>
                    </div>
                    <ul class="doc-list">
                        <?php foreach ($docsBySection['licence'] as $d): ?>
                        <li class="doc-item" data-id="<?= $d['id'] ?>" data-label="<?= htmlspecialchars($d['label'], ENT_QUOTES) ?>" data-path="<?= htmlspecialchars($d['path'], ENT_QUOTES) ?>">
                            <span class="doc-num"><?= $d['numero'] ?></span>
                            <span class="doc-label"><?= htmlspecialchars($d['label']) ?></span>
                            <div class="doc-actions">
                                <?php if ($d['path']): ?>
                                <a class="doc-dl" href="<?= htmlspecialchars($d['path']) ?>" target="_blank" title="Télécharger">⬇ PDF</a>
                                <?php endif; ?>
                                <?php if ($canEdit): ?>
                                <button class="doc-edit-btn" onclick="openDocModal(this.closest('.doc-item'))" title="Modifier">✏️</button>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Fiches médicales -->
                <div class="doc-section">
                    <div class="doc-section-header">
                        <h2>Fiches médicales</h2>
                    </div>
                    <ul class="doc-list">
                        <?php foreach ($docsBySection['medical'] as $d): ?>
                        <li class="doc-item" data-id="<?= $d['id'] ?>" data-label="<?= htmlspecialchars($d['label'], ENT_QUOTES) ?>" data-path="<?= htmlspecialchars($d['path'], ENT_QUOTES) ?>">
                            <span class="doc-num"><?= $d['numero'] ?></span>
                            <span class="doc-label"><?= htmlspecialchars($d['label']) ?></span>
                            <div class="doc-actions">
                                <?php if ($d['path']): ?>
                                <a class="doc-dl" href="<?= htmlspecialchars($d['path']) ?>" target="_blank" title="Télécharger">⬇ PDF</a>
                                <?php endif; ?>
                                <?php if ($canEdit): ?>
                                <button class="doc-edit-btn" onclick="openDocModal(this.closest('.doc-item'))" title="Modifier">✏️</button>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </div><!-- /documents-column -->

            <!-- ── Colonne liens ── -->
            <div class="actions-column">

                <?php foreach (['helloasso' => '💳', 'myffvolley' => '📱', 'inscription' => '📝'] as $slug => $icon):
                    if (!isset($liensBySlug[$slug])) continue;
                    $l = $liensBySlug[$slug];
                ?>
                <div class="action-card" data-id="<?= $l['id'] ?>" data-slug="<?= $slug ?>" data-label="<?= htmlspecialchars($l['label'], ENT_QUOTES) ?>" data-url="<?= htmlspecialchars($l['url'], ENT_QUOTES) ?>">
                    <div class="action-card-icon"><?= $icon ?></div>
                    <div class="action-card-body">
                        <span class="action-card-title"><?= htmlspecialchars($l['label']) ?></span>
                        <?php if ($l['description']): ?>
                        <span class="action-card-desc"><?= htmlspecialchars($l['description']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($l['url']): ?>
                    <a class="action-card-btn" href="<?= htmlspecialchars($l['url']) ?>" target="<?= str_starts_with($l['url'], 'http') ? '_blank' : '_self' ?>" rel="noopener">Accéder →</a>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                    <button class="action-edit-btn" onclick="openLienModal(this.closest('.action-card'))" title="Modifier">✏️</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

            </div><!-- /actions-column -->

        </div><!-- /licence-layout -->

        <!-- ── Section tarifs ── -->
        <div class="tarifs-section">
            <div class="tarifs-section-header">
                <h2 id="tarifs-saison-title">Tarifs saison <?= htmlspecialchars($saison) ?></h2>
                <?php if ($canEdit): ?>
                <button class="btn-edit-saison" onclick="openTarifsModal()" title="Modifier les tarifs">✏️ Tarifs</button>
                <?php endif; ?>
            </div>
            <div class="tarifs-body">

                <div class="tarifs-left">
                    <div class="tarifs-grid">
                        <?php foreach ($tarifs as $t): ?>
                        <div class="tarif-row<?= $t['supplement'] ? ' tarif-row--supplement' : '' ?>">
                            <span class="tarif-price"><?= $t['supplement'] ? '+' : '' ?><?= (int)$t['prix'] ?>€</span>
                            <div class="tarif-info">
                                <span class="tarif-label"><?= htmlspecialchars($t['label']) ?></span>
                                <?php if (!empty($t['comment'])): ?>
                                <span class="tarif-comment"><?= htmlspecialchars($t['comment']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="tarifs-global-note" id="tarifs-global-note"><?= htmlspecialchars($tarifsNote) ?></p>
                </div>

                <div class="tarifs-right">
                    <h3 class="tarifs-cout-title">Le coût d'une licence</h3>
                    <div class="tarifs-pie-wrap">
                        <svg viewBox="0 0 100 100" class="tarifs-pie-svg" aria-hidden="true">
                            <!-- FFVB 60% : css 0°→216° -->
                            <path d="M 50 50 L 50 8 A 42 42 0 1 1 25.3 84 Z" fill="#1a2e1a"/>
                            <text x="74" y="58" text-anchor="middle" dominant-baseline="central" fill="#fff" font-size="7.5" font-weight="700" font-family="Poppins,sans-serif">60%</text>
                            <!-- Club 30% : css 216°→324° -->
                            <path d="M 50 50 L 25.3 84 A 42 42 0 0 1 25.3 16 Z" fill="#4ecdc4"/>
                            <text x="25" y="50" text-anchor="middle" dominant-baseline="central" fill="#fff" font-size="7.5" font-weight="700" font-family="Poppins,sans-serif">30%</text>
                            <!-- Équipement 10% : css 324°→360° -->
                            <path d="M 50 50 L 25.3 16 A 42 42 0 0 1 50 8 Z" fill="#c8d8c4"/>
                            <text x="44" y="31" text-anchor="middle" dominant-baseline="central" fill="#444" font-size="5.5" font-weight="700" font-family="Poppins,sans-serif">10%</text>
                        </svg>
                    </div>
                    <ul class="tarifs-legend">
                        <li class="tarifs-legend-item">
                            <span class="tarifs-legend-dot tarifs-legend-dot--ffvb"></span>
                            <span><strong>60%</strong> reversés à la FF Volley, Ligue et Comité</span>
                        </li>
                        <li class="tarifs-legend-item">
                            <span class="tarifs-legend-dot tarifs-legend-dot--club"></span>
                            <span><strong>30%</strong> entraîneur, matériel, arbitrage, déplacements</span>
                        </li>
                        <li class="tarifs-legend-item">
                            <span class="tarifs-legend-dot tarifs-legend-dot--equip"></span>
                            <span><strong>10%</strong> équipement</span>
                        </li>
                    </ul>
                </div>

            </div>
        </div>

        <!-- ── Section tutoriels myFFvolley ── -->
        <div class="tuto-section">
            <div class="tuto-section-header">
                <h2>Tutoriels myFFvolley App</h2>
                <?php if ($canEdit): ?>
                <span class="tuto-admin-hint">Survolez une vidéo pour la modifier</span>
                <?php endif; ?>
            </div>
            <div class="tuto-grid">
                <?php foreach ($tutos as $num => $t):
                    $vid = youtube_id($t['url']);
                    $thumb = $vid ? 'https://img.youtube.com/vi/' . $vid . '/mqdefault.jpg' : '';
                ?>
                <div class="tuto-card"
                     data-tuto-id="<?= $num ?>"
                     data-titre="<?= htmlspecialchars($t['titre'], ENT_QUOTES) ?>"
                     data-url="<?= htmlspecialchars($t['url'], ENT_QUOTES) ?>">
                    <div class="tuto-thumb" onclick="playTuto(this,'<?= $vid ?>')">
                        <?php if ($thumb): ?>
                        <img src="<?= $thumb ?>" alt="<?= htmlspecialchars($t['titre']) ?>" loading="lazy">
                        <button class="tuto-play-btn" type="button" aria-hidden="true">▶</button>
                        <?php else: ?>
                        <div class="tuto-no-video">📹 Vidéo à configurer</div>
                        <?php endif; ?>
                    </div>
                    <a class="tuto-info" href="<?= htmlspecialchars($t['url']) ?>" target="_blank" rel="noopener" title="Voir sur YouTube">
                        <span class="tuto-num"><?= str_pad((string)$num, 2, '0', STR_PAD_LEFT) ?></span>
                        <span class="tuto-titre"><?= htmlspecialchars($t['titre']) ?></span>
                    </a>
                    <?php if ($canEdit): ?>
                    <button class="tuto-edit-btn" onclick="openTutoModal(<?= $num ?>)" title="Modifier">✏️</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>

    <?php if ($canEdit): ?>
    <!-- ── Modale document ── -->
    <div id="docModal" class="licence-modal">
        <div class="licence-modal-content">
            <div class="licence-modal-header">
                <h3>Modifier le document</h3>
                <span class="close" onclick="closeDocModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="licence-modal-body">
                <form id="doc-form" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="doc-id">
                    <div class="modal-form-group">
                        <label for="doc-label">Libellé</label>
                        <input type="text" name="label" id="doc-label" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="doc-file">Nouveau fichier PDF <span class="optional">(optionnel)</span></label>
                        <div class="current-path" id="doc-current-path"></div>
                        <input type="file" name="fichier" id="doc-file" accept="application/pdf">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeDocModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="doc-status"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modale lien ── -->
    <div id="lienModal" class="licence-modal">
        <div class="licence-modal-content">
            <div class="licence-modal-header">
                <h3>Modifier le lien</h3>
                <span class="close" onclick="closeLienModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="licence-modal-body">
                <form id="lien-form">
                    <input type="hidden" name="id" id="lien-id">
                    <div class="modal-form-group">
                        <label for="lien-label">Libellé</label>
                        <input type="text" name="label" id="lien-label" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="lien-url">URL</label>
                        <input type="text" name="url" id="lien-url" placeholder="https://...">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeLienModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="lien-status"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modale tutoriel ── -->
    <div id="tutoModal" class="licence-modal">
        <div class="licence-modal-content">
            <div class="licence-modal-header">
                <h3>Modifier le tutoriel <span id="tuto-modal-num"></span></h3>
                <span class="close" onclick="closeTutoModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="licence-modal-body">
                <form id="tuto-form">
                    <input type="hidden" name="num" id="tuto-num-input">
                    <div class="modal-form-group">
                        <label for="tuto-titre-input">Titre</label>
                        <input type="text" name="titre" id="tuto-titre-input" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="tuto-url-input">URL YouTube</label>
                        <input type="text" name="url" id="tuto-url-input" placeholder="https://youtu.be/...">
                        <small class="modal-hint">Accepte youtube.com/watch?v=... ou youtu.be/...</small>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeTutoModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="tuto-status"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modale tarifs ── -->
    <div id="tarifsModal" class="licence-modal">
        <div class="licence-modal-content licence-modal-content--wide">
            <div class="licence-modal-header">
                <h3>Modifier les tarifs</h3>
                <span class="close" onclick="closeTarifsModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="licence-modal-body">
                <form id="tarifs-form">
                    <div id="tarifs-modal-rows" class="tarifs-modal-rows"></div>
                    <button type="button" class="btn-add-tarif" onclick="addTarifModalRow()">+ Ajouter une ligne</button>
                    <div class="modal-form-group" style="margin-top:.75rem">
                        <label for="tarifs-modal-note">Note générale <span class="optional">(optionnel)</span></label>
                        <textarea id="tarifs-modal-note" class="tarifs-modal-note-input"
                                  placeholder="Ex : Paiement échelonnable sur 2 ou 3 chèques…" rows="2"></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeTarifsModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="tarifs-status"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modale saison ── -->
    <div id="saisonModal" class="licence-modal">
        <div class="licence-modal-content">
            <div class="licence-modal-header">
                <h3>Saison en cours</h3>
                <span class="close" onclick="closeSaisonModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="licence-modal-body">
                <form id="saison-form">
                    <div class="modal-form-group">
                        <label for="saison-input">Saison (ex. 2026-2027)</label>
                        <input type="text" name="valeur" id="saison-input"
                            value="<?= htmlspecialchars($saison, ENT_QUOTES) ?>"
                            pattern="\d{4}-\d{4}" placeholder="AAAA-AAAA" required>
                        <input type="hidden" name="cle" value="saison">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeSaisonModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="saison-status"></p>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script src="/js/licence.js"></script>
</body>
</html>
