<?php
require_once __DIR__ . '/../../../php/auth.php';
require_login();
if (!has_any_role(['admin', 'moderateur', 'bureau', 'entraineur', 'arbitre'])) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}

$isMod = has_any_role(['admin', 'moderateur']);
$me    = current_user();
$pdo   = get_pdo();

function h(string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$themes = [
    'echauffement' => ['🔥', 'Échauffement'],
    'attaque'      => ['💥', 'Attaque'],
    'contre'       => ['🧱', 'Contre/Bloc'],
    'service'      => ['🎯', 'Service'],
    'reception'    => ['🙌', 'Réception'],
    'passe'        => ['🤲', 'Passe'],
    'physique'     => ['💪', 'Physique/Muscu'],
    'jeu'          => ['🏐', 'Jeu'],
];

$videos = [];
try {
    $videos = $pdo->query(
        "SELECT v.*, u.prenom, u.nom
            FROM videos_entrainement v
        LEFT JOIN users u ON u.id = v.user_id
        ORDER BY v.created_at DESC, v.id DESC"
    )->fetchAll();
} catch (Exception) {}

$videosByTheme = array_fill_keys(array_keys($themes), []);
foreach ($videos as $v) {
    $t = $v['theme'] ?? 'jeu';
    if (!isset($videosByTheme[$t])) $t = 'jeu';
    $videosByTheme[$t][] = $v;
}

// Logo de la plateforme pour une vidéo, d'après son nom de domaine.
function video_source_badge(string $url): array {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $host = preg_replace('/^www\./', '', $host);
    if ($host === 'youtube.com' || $host === 'm.youtube.com' || $host === 'youtu.be') {
        return ['/images/social/Youtube.png', 'YouTube'];
    }
    if ($host === 'facebook.com' || $host === 'm.facebook.com' || $host === 'fb.watch') {
        return ['/images/social/Facebook-mini.png', 'Facebook'];
    }
    if ($host === 'instagram.com') {
        return ['/images/social/Instagram-mini.png', 'Instagram'];
    }
    if ($host === 'tiktok.com' || $host === 'vm.tiktok.com') {
        return ['/images/social/tiktok.svg', 'TikTok'];
    }
    return ['/images/icons/link.svg', 'Autre plateforme'];
}

function render_video_card(array $v, bool $isMod, array $me): void {
    $auteur = trim(($v['prenom'] ?? '') . ' ' . ($v['nom'] ?? '')) ?: 'Un adhérent';
    $peutGerer = $isMod || (int)$v['user_id'] === (int)$me['id'];
    ?>
    <div class="vid-card"
        data-id="<?= (int)$v['id'] ?>"
        data-type="<?= h($v['type']) ?>"
        data-theme="<?= h($v['theme']) ?>"
        data-titre="<?= h($v['titre']) ?>"
        data-description="<?= h($v['description'] ?? '') ?>"
        data-url="<?= h($v['url']) ?>">
        <?php if ($v['type'] === 'image'): ?>
        <a class="vid-thumb" href="<?= h($v['url']) ?>" onclick="return openImageLightbox('<?= h($v['url']) ?>', event)">
            <img src="<?= h($v['url']) ?>" alt="<?= h($v['titre']) ?>" loading="lazy">
        </a>
        <?php elseif ($v['type'] === 'video'): ?>
        <?php [$badgeSrc, $badgeAlt] = video_source_badge($v['url']); ?>
        <img class="vid-type-badge" src="<?= h($badgeSrc) ?>" alt="<?= h($badgeAlt) ?>" title="<?= h($badgeAlt) ?>">
        <?php else: ?>
        <img class="vid-type-badge" src="/images/icons/pdf.svg" alt="PDF" title="Document PDF">
        <?php endif; ?>
        <h3><?= h($v['titre']) ?></h3>
        <?php if ($v['description']): ?><p><?= h($v['description']) ?></p><?php endif; ?>
        <div class="vid-card-footer">
            <?php if ($v['type'] === 'video'): ?>
            <a class="vid-link" href="<?= h($v['url']) ?>" target="_blank" rel="noopener">▶ Voir la vidéo</a>
            <?php elseif ($v['type'] === 'image'): ?>
            <button type="button" class="vid-link" onclick="openImageLightbox('<?= h($v['url']) ?>')">🖼 Voir en grand</button>
            <?php else: ?>
            <button type="button" class="vid-link" onclick="openPdfModal('<?= h($v['url']) ?>','<?= h($v['titre']) ?>')">📄 Consulter</button>
            <?php endif; ?>
            <span class="vid-meta"><?= h($auteur) ?> · <?= h((new DateTime($v['created_at']))->format('d/m/Y')) ?></span>
        </div>
        <?php if ($peutGerer): ?>
        <div class="vid-card-actions">
            <button type="button" class="vid-edit-btn" onclick="openVideoEditModal(this.closest('.vid-card'))" title="Modifier">✏️</button>
            <button type="button" class="vid-delete-btn" onclick='deleteVideo(<?= (int)$v['id'] ?>, <?= json_encode($v['titre'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Supprimer">🗑</button>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/css/styles.css?v=20261005" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur.css?v=20260928" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur/videos.css?v=20261021" rel="stylesheet">
    <title>Vidéos d'entraînement - VBO</title>
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
                <h1>Vidéos d'entraînement</h1>
                <p class="explication">Partagez des vidéos, images ou PDF pour donner des idées d'exercices aux autres entraîneurs.</p>
            </div>
        </div>
    </div>

    <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    <main class="ent-main">
        <div class="ps-formations-toolbar">
            <button type="button" class="btn-save" onclick="openVideoModal()">＋ Partager une ressource</button>
        </div>

        <button type="button" class="vid-fab" onclick="openVideoModal()" aria-label="Partager une ressource">＋</button>

        <div class="vid-theme-filter" id="vid-theme-filter">
            <button type="button" class="vid-filter-btn active" data-theme="all">Tous</button>
            <?php foreach ($themes as $themeKey => [$themeIcon, $themeLabel]): ?>
            <button type="button" class="vid-filter-btn" data-theme="<?= h($themeKey) ?>"><?= $themeIcon ?> <?= h($themeLabel) ?></button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($themes as $themeKey => [$themeIcon, $themeLabel]): ?>
        <?php $themeCount = count($videosByTheme[$themeKey]); ?>
        <section class="vid-theme-section collapsed" data-theme="<?= h($themeKey) ?>">
            <div class="vid-theme-header">
                <h2 class="vid-theme-title"><?= $themeIcon ?> <?= h($themeLabel) ?> <span class="vid-theme-count"><?= $themeCount ?></span></h2>
                <button type="button" class="vid-theme-toggle" onclick="toggleThemeSection(this)" aria-expanded="false">
                    <span class="vid-theme-toggle-text">Afficher</span>
                    <span class="vid-theme-toggle-icon">▾</span>
                </button>
            </div>
            <div class="vid-grid">
                <?php foreach ($videosByTheme[$themeKey] as $v) render_video_card($v, $isMod, $me); ?>
                <?php if (empty($videosByTheme[$themeKey])): ?>
                <p class="ps-docs-empty">Aucune ressource dans ce thème. Soyez le premier à en proposer une !</p>
                <?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </main>

    <div id="pdf-modal-slot"></div>

    <div id="videoModal" class="ent-modal" onclick="if(event.target===this)closeVideoModal()">
        <div class="ent-modal-content">
            <h2 class="ent-modal-title">Partager une ressource</h2>
            <form id="videoForm" onsubmit="submitVideo(event)" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Type de ressource</label>
                    <div class="ps-radio-group">
                        <label><input type="radio" name="v-kind" value="video" checked onchange="toggleVideoKind()"> 🎥 Lien vidéo</label>
                        <label><input type="radio" name="v-kind" value="image" onchange="toggleVideoKind()"> 🖼 Image</label>
                        <label><input type="radio" name="v-kind" value="pdf" onchange="toggleVideoKind()"> 📄 PDF</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="v-theme">Thème</label>
                    <select id="v-theme" required>
                        <?php foreach ($themes as $themeKey => [$themeIcon, $themeLabel]): ?>
                        <option value="<?= h($themeKey) ?>"><?= $themeIcon ?> <?= h($themeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="v-titre">Titre</label>
                    <input type="text" id="v-titre" required maxlength="150" placeholder="Ex : Exercice de réception en 3 contre 3">
                </div>
                <div class="form-group" id="vUrlGroup">
                    <label for="v-url">Lien de la vidéo</label>
                    <input type="url" id="v-url" placeholder="https://…">
                </div>
                <div class="form-group" id="vFileGroup" style="display:none">
                    <label id="v-file-text">Fichier</label>
                    <img id="v-file-preview" class="v-file-preview" alt="" style="display:none">
                    <div class="ps-upload-zone" onclick="document.getElementById('v-file').click()">
                        <div class="ps-upload-label" id="v-file-label">📁 Cliquer pour choisir un fichier</div>
                        <p class="ps-upload-hint" id="v-file-hint">Image : jpg, png, webp ou gif (5 Mo max)</p>
                        <input type="file" id="v-file" onchange="updateVideoFileLabel()">
                    </div>
                </div>
                <div class="form-group">
                    <label for="v-description">Description</label>
                    <textarea id="v-description" rows="3" placeholder="Ce que l'exercice apporte, pour qui… (facultatif)"></textarea>
                </div>
                <p class="form-status" id="v-status"></p>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeVideoModal()">Annuler</button>
                    <button type="submit" class="btn-save">Partager</button>
                </div>
            </form>
        </div>
    </div>

    <div id="videoEditModal" class="ent-modal" onclick="if(event.target===this)closeVideoEditModal()">
        <div class="ent-modal-content">
            <h2 class="ent-modal-title">Modifier la ressource</h2>
            <form id="videoEditForm" onsubmit="submitVideoEdit(event)" enctype="multipart/form-data">
                <input type="hidden" id="ve-id">
                <input type="hidden" id="ve-type">
                <div class="form-group">
                    <label for="ve-theme">Thème</label>
                    <select id="ve-theme" required>
                        <?php foreach ($themes as $themeKey => [$themeIcon, $themeLabel]): ?>
                        <option value="<?= h($themeKey) ?>"><?= $themeIcon ?> <?= h($themeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ve-titre">Titre</label>
                    <input type="text" id="ve-titre" required maxlength="150">
                </div>
                <div class="form-group" id="veUrlGroup">
                    <label for="ve-url">Lien de la vidéo</label>
                    <input type="url" id="ve-url" placeholder="https://…">
                </div>
                <div class="form-group" id="veFileGroup" style="display:none">
                    <label id="ve-file-text">Fichier</label>
                    <img id="ve-file-preview" class="v-file-preview" alt="" style="display:none">
                    <div class="ps-upload-zone" onclick="document.getElementById('ve-file').click()">
                        <div class="ps-upload-label" id="ve-file-label">📁 Cliquer pour remplacer le fichier</div>
                        <p class="ps-upload-hint">Laissez vide pour conserver le fichier actuel</p>
                        <input type="file" id="ve-file" onchange="updateVideoEditFileLabel()">
                    </div>
                </div>
                <div class="form-group">
                    <label for="ve-description">Description</label>
                    <textarea id="ve-description" rows="3" placeholder="Ce que l'exercice apporte, pour qui… (facultatif)"></textarea>
                </div>
                <p class="form-status" id="ve-status"></p>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeVideoEditModal()">Annuler</button>
                    <button type="submit" class="btn-save">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div id="footer"></div>

    <script src="/js/pdf-modal.js?v=20260721"></script>
    <script src="/js/espace-entraineur/videos.js?v=20261020"></script>
    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
