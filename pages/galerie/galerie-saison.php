<?php
require_once '../../php/auth.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['slug'] ?? ''));
if (!$slug) {
    header('Location: /pages/galerie/galerie.html');
    exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT * FROM saisons_galerie WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $saison = $stmt->fetch();

    if (!$saison) {
        header('Location: /pages/galerie/galerie.html');
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM photos_galerie WHERE saison_id = ? AND statut = 'publiee' ORDER BY ordre ASC, id ASC"
    );
    $stmt->execute([$saison['id']]);
    $photos = $stmt->fetchAll();

} catch (Exception $e) {
    header('Location: /pages/galerie/galerie.html');
    exit;
}

function he(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galerie <?= he($saison['label']) ?> - VBO</title>
    <link rel="stylesheet" href="/css/styles.css?v=20260705">
    <link rel="stylesheet" href="/css/galerie/galerie-archive.css?v=20260623">
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
            <h1>Saison <?= he($saison['label']) ?></h1>
            <p>Retrouvez les moments marquants de la saison <?= he($saison['label']) ?>.</p>
        </div>
    </div>
</div>

<section class="archive-section">
    <div class="archive-section-header">
        <h2>Photos de la saison</h2>
        <a href="/pages/galerie/galerie.html" class="back-link">← Retour à la galerie</a>
    </div>

    <div class="archive-gallery">
        <?php foreach ($photos as $photo): ?>
        <a href="/<?= he($photo['filepath']) ?>" target="_blank">
            <img src="/<?= he($photo['filepath']) ?>" alt="<?= he($photo['alt_text'] ?? '') ?>">
        </a>
        <?php endforeach; ?>

        <?php if ($saison['flickr_url']): ?>
        <a href="<?= he($saison['flickr_url']) ?>" target="_blank" class="more">
            <?php if (!empty($photos)): ?>
            <img src="/<?= he($photos[count($photos)-1]['filepath']) ?>" alt="">
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($photos) && !$saison['flickr_url']): ?>
    <p class="gal-empty" style="text-align:center;color:#888;padding:2rem 0;">Aucune photo pour cette saison.</p>
    <?php endif; ?>

    <a href="/pages/galerie/galerie.html" class="back-link">← Retour à la galerie</a>
</section>

<div id="footer"></div>
<script src="/js/main.js?v=20260705"></script>
</body>
</html>
