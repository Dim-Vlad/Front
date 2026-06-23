<?php
require_once __DIR__ . '/../../php/auth.php';

$canEdit = is_logged_in() && has_any_role(['moderateur', 'admin']);

$docs   = [];
$liens  = [];
$saison = '2026-2027';
$videoUrl = '';

try {
    $pdo = get_pdo();
    $docs  = $pdo->query("SELECT * FROM licence_documents ORDER BY section, ordre")->fetchAll();
    $liens = $pdo->query("SELECT * FROM licence_liens ORDER BY id")->fetchAll();
    $cfg   = $pdo->query("SELECT cle, valeur FROM licence_config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $saison   = $cfg['saison']    ?? '2026-2027';
    $videoUrl = $cfg['video_url'] ?? '';
} catch (Exception $e) {}

// Grouper les documents par section
$docsBySection = ['licence' => [], 'medical' => []];
foreach ($docs as $d) $docsBySection[$d['section']][] = $d;

// Indexer les liens par slug
$liensBySlug = [];
foreach ($liens as $l) $liensBySlug[$l['slug']] = $l;

// Transformer URL YouTube → embed
function youtube_embed(string $url): string {
    if ($url === '') return '';
    if (str_contains($url, '/embed/')) return $url;
    if (preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{11})/', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0';
    }
    return $url;
}
$embedUrl = youtube_embed($videoUrl);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licences - VBO</title>
    <link href="/css/styles.css?v=20260623" rel="stylesheet">
    <link href="/css/leClub/licence.css?v=20260623" rel="stylesheet">
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

        <!-- ── Section vidéo tuto ── -->
        <div class="video-section" id="video-section">
            <div class="video-section-header">
                <h2>Tutoriel myFFvolley App</h2>
                <?php if ($canEdit): ?>
                <button class="btn-edit-video" onclick="openVideoModal()" title="Modifier la vidéo">✏️ <?= $videoUrl ? 'Changer la vidéo' : 'Ajouter une vidéo' ?></button>
                <?php endif; ?>
            </div>
            <div id="video-wrap" class="video-wrap">
                <?php if ($embedUrl): ?>
                <iframe id="video-iframe"
                    src="<?= htmlspecialchars($embedUrl) ?>"
                    allowfullscreen
                    loading="lazy"
                    title="Tutoriel myFFvolley"></iframe>
                <?php else: ?>
                <div class="video-placeholder" id="video-placeholder">
                    <span>📹</span>
                    <p>La vidéo tutoriel sera disponible prochainement.</p>
                </div>
                <?php endif; ?>
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

    <!-- ── Modale vidéo ── -->
    <div id="videoModal" class="licence-modal">
        <div class="licence-modal-content">
            <div class="licence-modal-header">
                <h3>Vidéo tutoriel myFFvolley</h3>
                <span class="close" onclick="closeVideoModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="licence-modal-body">
                <form id="video-form">
                    <div class="modal-form-group">
                        <label for="video-url-input">URL YouTube</label>
                        <input type="text" name="valeur" id="video-url-input"
                            placeholder="https://www.youtube.com/watch?v=..."
                            value="<?= htmlspecialchars($videoUrl, ENT_QUOTES) ?>">
                        <input type="hidden" name="cle" value="video_url">
                        <small class="modal-hint">Accepte les liens YouTube standards (youtube.com/watch?v=... ou youtu.be/...)</small>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeVideoModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="video-status"></p>
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

    <script src="/js/main.js"></script>
    <script src="/js/licence.js"></script>
</body>
</html>
