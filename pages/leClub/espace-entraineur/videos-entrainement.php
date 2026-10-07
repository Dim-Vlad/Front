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

$videos = [];
try {
    $videos = $pdo->query(
        "SELECT v.*, u.prenom, u.nom
           FROM videos_entrainement v
      LEFT JOIN users u ON u.id = v.user_id
       ORDER BY v.created_at DESC, v.id DESC"
    )->fetchAll();
} catch (Exception) {}

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/css/styles.css?v=20261005" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur.css?v=20260928" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur/videos.css?v=20261007" rel="stylesheet">
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

        <div class="vid-grid" id="vid-grid">
            <?php foreach ($videos as $v): ?>
            <?php
                $auteur = trim(($v['prenom'] ?? '') . ' ' . ($v['nom'] ?? '')) ?: 'Un adhérent';
                $peutSupprimer = $isMod || (int)$v['user_id'] === (int)$me['id'];
            ?>
            <div class="vid-card" data-id="<?= (int)$v['id'] ?>">
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
                <?php if ($peutSupprimer): ?>
                <button type="button" class="vid-delete-btn" onclick='deleteVideo(<?= (int)$v['id'] ?>, <?= json_encode($v['titre'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Supprimer">🗑</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($videos)): ?>
            <p class="ps-docs-empty">Aucune ressource partagée pour le moment. Soyez le premier à en proposer une !</p>
            <?php endif; ?>
        </div>
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

    <div id="footer"></div>

    <script src="/js/pdf-modal.js?v=20260721"></script>
    <script src="/js/espace-entraineur/videos.js?v=20261008"></script>
    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
