<?php
require_once __DIR__ . '/../php/auth.php';

$canEdit         = is_logged_in() && has_any_role(['moderateur', 'admin']);
$user            = is_logged_in() ? current_user() : null;
$showEvents      = false;
$aVenir          = [];
$showPronostics  = false;
$prochainsMatchs = [];
$topPronostics   = [];

$roleLabels   = [
    'admin'      => 'Admin',
    'moderateur' => 'Modérateur',
    'entraineur' => 'Entraîneur',
    'arbitre'    => 'Arbitre',
    'bureau'     => 'Bureau',
    'adherent'   => 'Adhérent',
];
$rolePriority = ['admin', 'moderateur', 'bureau', 'entraineur', 'arbitre', 'adherent'];
$primaryRole  = null;
if ($user) {
    foreach ($rolePriority as $r) {
        if (in_array($r, $user['roles'] ?? [], true)) { $primaryRole = $r; break; }
    }
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'home_show_events'");
    $stmt->execute();
    $row        = $stmt->fetch();
    $showEvents = ($row && $row['valeur'] === '1');

    if ($canEdit) {
        $aVenir = $pdo->query(
            "SELECT * FROM evenements WHERE termine = 0 ORDER BY ordre ASC, id ASC"
        )->fetchAll();
    } elseif ($showEvents) {
        $aVenir = $pdo->query(
            "SELECT * FROM evenements WHERE termine = 0 AND home_visible = 1 ORDER BY ordre ASC, id ASC"
        )->fetchAll();
    }

    $recentPhotos = $pdo->query(
        "SELECT filepath, alt_text FROM photos_galerie WHERE statut = 'publiee' ORDER BY uploaded_at DESC, id DESC LIMIT 4"
    )->fetchAll();

    $partenaires = $pdo->query(
        "SELECT nom, logo, url FROM partenaires ORDER BY ordre ASC"
    )->fetchAll();

    $stmtP = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'home_show_pronostics'");
    $stmtP->execute();
    $rowP           = $stmtP->fetch();
    $showPronostics = ($rowP && $rowP['valeur'] === '1');

    if ($canEdit || $showPronostics) {
        // Les prochains matchs à pronostiquer (les plus proches en premier).
        $prochainsMatchs = $pdo->query(
            "SELECT * FROM pronostics_matchs
             WHERE resultat_victoire IS NULL AND date_match > NOW()
             ORDER BY date_match ASC
             LIMIT 5"
        )->fetchAll();

        $topPronostics = $pdo->query(
            'SELECT * FROM (
                SELECT u.id, u.prenom, u.nom,
                       COALESCE(SUM(
                           CASE
                               WHEN m.resultat_sets IS NOT NULL AND v.choix_sets = m.resultat_sets THEN 3
                               WHEN m.resultat_victoire IS NOT NULL AND v.choix_victoire = m.resultat_victoire THEN 1
                               ELSE 0
                           END
                       ), 0) AS pts_prono,
                       COALESCE((
                           SELECT SUM(qr.points_obtenus) FROM quiz_reponses qr WHERE qr.user_id = u.id
                       ), 0) AS pts_quiz,
                       COUNT(DISTINCT v.id) AS nb_votes,
                       COALESCE((
                           SELECT COUNT(*) FROM quiz_reponses qr WHERE qr.user_id = u.id
                       ), 0) AS nb_quiz
                FROM users u
                LEFT JOIN pronostics_votes v ON v.user_id = u.id
                LEFT JOIN pronostics_matchs m ON m.id = v.match_id
                WHERE u.actif = 1
                GROUP BY u.id, u.prenom, u.nom
            ) AS sub
            WHERE nb_votes > 0 OR nb_quiz > 0
            ORDER BY (pts_prono + pts_quiz) DESC, pts_prono DESC
            LIMIT 3'
        )->fetchAll();
    }

} catch (Exception $e) {}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

function fmt_prono_date(string $d): string {
    $ts   = strtotime($d);
    $mois = ['','jan.','fév.','mar.','avr.','mai','juin','juil.','aoû.','sep.','oct.','nov.','déc.'];
    return date('d', $ts) . ' ' . $mois[(int)date('n', $ts)] . ' ' . date('Y', $ts) . ' · ' . date('H\hi', $ts);
}

function formatHomeDate(?string $debut, ?string $fin): string {
    if (!$debut) return 'Date à confirmer';
    $mois = ['','jan.','fév.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
    $d1   = new DateTime($debut);
    $s    = intval($d1->format('j')) . ' ' . $mois[(int)$d1->format('n')] . ' ' . $d1->format('Y');
    if ($fin && $fin !== $debut) {
        $d2 = new DateTime($fin);
        $s  = intval($d1->format('j')) . ' ' . $mois[(int)$d1->format('n')];
        if ($d1->format('Y') !== $d2->format('Y')) $s .= ' ' . $d1->format('Y');
        $s .= ' – ' . intval($d2->format('j')) . ' ' . $mois[(int)$d2->format('n')] . ' ' . $d2->format('Y');
    }
    return $s;
}
?>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">
    <img class="hero-logo" src="/images/logo-club/LogoVBO.png" alt="Logo du club VBO">
    <div class="hero-text">
      <h1>Volley Ball<br>Ollioulais</h1>
      <?php if ($user && !empty($user['prenom'])): ?>
      <p class="hero-sub">Bonjour <strong><?= h($user['prenom']) ?></strong>, bienvenue sur le site officiel du club.</p>
      <?php if (!empty($user['roles'])): ?>
      <div class="hero-roles">
        <?php foreach ($rolePriority as $r):
            if (!in_array($r, $user['roles'], true)) continue; ?>
        <span class="hero-role-badge hero-role-badge--<?= $r ?>"><?= $roleLabels[$r] ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <p class="hero-sub">Bienvenue sur le site officiel du club</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- À VENIR : ÉVÉNEMENTS + PRONOSTICS -->
<?php
$hasEventsCol = $canEdit || $showEvents;
$hasPronoCol  = $canEdit || $showPronostics;
?>
<?php if ($hasEventsCol || $hasPronoCol): ?>
<section class="home-avenir">
  <div class="home-avenir-grid<?= (!$hasEventsCol || !$hasPronoCol) ? ' home-avenir-grid--single' : '' ?>">

    <?php if ($hasEventsCol): ?>
    <div class="home-avenir-col<?= ($canEdit && !$showEvents) ? ' home-avenir-col--preview' : '' ?>">
      <div class="home-events-header">
        <h2>Événements à venir</h2>
        <?php if ($canEdit): ?>
        <button
          class="btn-home-events-toggle<?= $showEvents ? ' active' : '' ?>"
          onclick="toggleHomeEvents(this)"
          title="<?= $showEvents ? 'Masquer les événements sur l\'accueil' : 'Afficher les événements sur l\'accueil' ?>">
          <?= $showEvents ? '● Visible' : '○ Masqué' ?>
        </button>
        <?php endif; ?>
      </div>
      <?php if ($canEdit && !$showEvents): ?>
      <p class="home-events-hint">Cette section est masquée pour les visiteurs. Cliquez sur le bouton pour la rendre visible.</p>
      <?php endif; ?>
      <?php if (empty($aVenir)): ?>
      <p class="home-events-empty">Aucun événement à venir pour le moment.</p>
      <?php else: ?>
      <ul class="home-ev-compact-list">
        <?php foreach ($aVenir as $ev):
            $hasLink  = !empty($ev['lien_url']);
            $isHidden = $canEdit && !$ev['home_visible'];
            $extraClass = $isHidden ? ' home-ev-compact-item--hidden' : '';
        ?>
        <li class="home-ev-compact-item<?= $extraClass ?>">
          <?php if ($hasLink): ?>
          <a href="<?= h($ev['lien_url']) ?>" class="home-ev-compact-link">
          <?php else: ?>
          <div class="home-ev-compact-link">
          <?php endif; ?>
            <?php if (!empty($ev['image_url'])): ?>
            <img class="home-ev-compact-img" src="<?= h($ev['image_url']) ?>" alt="" loading="lazy">
            <?php endif; ?>
            <span class="home-ev-compact-body">
              <span class="home-ev-compact-date"><?= h(formatHomeDate($ev['date_debut'], $ev['date_fin'])) ?></span>
              <span class="home-ev-compact-title"><?= h($ev['titre']) ?></span>
              <?php if (!empty($ev['lieu'])): ?>
              <span class="home-ev-compact-lieu">📍 <?= h($ev['lieu']) ?></span>
              <?php endif; ?>
            </span>
            <?php if ($hasLink): ?><span class="home-ev-compact-arrow">→</span><?php endif; ?>
          <?php if ($hasLink): ?></a><?php else: ?></div><?php endif; ?>
          <?php if ($canEdit): ?>
          <button
            class="btn-home-ev-toggle<?= $ev['home_visible'] ? ' active' : '' ?>"
            onclick="toggleHomeEventVisible(<?= $ev['id'] ?>, this, event)"
            title="<?= $ev['home_visible'] ? 'Masquer sur l\'accueil' : 'Afficher sur l\'accueil' ?>">
            <?= $ev['home_visible'] ? '● Visible' : '○ Masqué' ?>
          </button>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($hasPronoCol): ?>
    <div class="home-avenir-col<?= ($canEdit && !$showPronostics) ? ' home-avenir-col--preview' : '' ?>">
      <div class="home-prono-header">
        <h2>🔮 Pronostics</h2>
        <?php if ($canEdit): ?>
        <button
          class="btn-home-events-toggle<?= $showPronostics ? ' active' : '' ?>"
          onclick="toggleHomePronostics(this)"
          title="<?= $showPronostics ? 'Masquer les pronostics sur l\'accueil' : 'Afficher les pronostics sur l\'accueil' ?>">
          <?= $showPronostics ? '● Visible' : '○ Masqué' ?>
        </button>
        <?php endif; ?>
      </div>
      <?php if ($canEdit && !$showPronostics): ?>
      <p class="home-events-hint">Cette section est masquée pour les visiteurs. Cliquez sur le bouton pour la rendre visible.</p>
      <?php endif; ?>

      <div class="home-prono-match-card">
        <?php if (!empty($prochainsMatchs)): ?>
        <span class="home-prono-eyebrow">Prochains matchs à pronostiquer</span>
        <ul class="home-prono-match-list">
          <?php foreach ($prochainsMatchs as $m): ?>
          <li class="home-prono-match-row">
            <span class="home-prono-match-row-title">
              <?= $m['domicile'] ? 'VBO vs ' . h($m['adversaire']) : h($m['adversaire']) . ' vs VBO' ?>
            </span>
            <span class="home-prono-match-row-meta">
              <?= h(fmt_prono_date($m['date_match'])) ?>
              <?php if ($m['competition']): ?> · <?= h($m['competition']) ?><?php endif; ?>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <span class="home-prono-eyebrow">Jouez avec nous</span>
        <h3 class="home-prono-match-title">Pronostiquez les matchs du VBO</h3>
        <p class="home-prono-match-meta">Prochains matchs bientôt annoncés — restez connectés !</p>
        <?php endif; ?>
        <p class="home-prono-pitch">Tentez de prédire les résultats des matchs du club et grimpez au classement général.</p>
        <a href="<?= $user ? '/pages/pronostics/index.php' : '/pages/auth/connexion.php' ?>" class="btn home-prono-cta">
          <?= $user ? '🎯 Faire mes pronostics →' : '🎯 Je m\'inscris pour jouer →' ?>
        </a>
      </div>

      <div class="home-prono-podium-card">
        <span class="home-prono-eyebrow">Classement</span>
        <?php if (empty($topPronostics)): ?>
        <p class="home-prono-podium-empty">Soyez les premiers à grimper au classement !</p>
        <?php else: ?>
        <ol class="home-prono-podium">
          <?php foreach ($topPronostics as $i => $p):
              $rank = $i + 1;
              $nom  = trim(($p['prenom'] ?? '') . ' ' . mb_substr($p['nom'] ?? '', 0, 1) . '.');
              $pts  = (int)$p['pts_prono'] + (int)$p['pts_quiz'];
          ?>
          <li class="home-prono-podium-item home-prono-podium-item--<?= $rank ?>">
              <span class="home-prono-podium-rank"><?= $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉') ?></span>
              <span class="home-prono-podium-name"><?= h($nom) ?></span>
              <span class="home-prono-podium-pts"><?= $pts ?> pts</span>
          </li>
          <?php endforeach; ?>
        </ol>
        <?php endif; ?>
        <a href="/pages/pronostics/classement.php" class="home-prono-link">Voir le classement complet →</a>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>
<?php endif; ?>

<!-- ACCÈS RAPIDE -->
<section class="home-banners">
  <a href="/pages/auth/tableau-de-bord.php" class="home-banner-card" id="home-member-card">
    <span class="home-banner-card__icon">🔐</span>
    <div class="home-banner-card__text">
      <h2>Espace membres</h2>
      <?php if ($user && !empty($user['prenom'])): ?>
      <p id="home-member-desc">Bonjour <?= h($user['prenom']) ?> ! Retrouvez votre tableau de bord et vos outils.</p>
      <?php else: ?>
      <p id="home-member-desc">Adhérents, entraîneurs, arbitres ou membres du bureau, accédez à votre espace dédié.</p>
      <?php endif; ?>
    </div>
    <span class="btn home-banner-card__btn" id="home-member-btn"><?= ($user && !empty($user['prenom'])) ? 'Mon espace' : 'Se connecter' ?> →</span>
  </a>
  <a href="/pages/leClub/remboursement.html" class="home-banner-card">
    <span class="home-banner-card__icon">📋</span>
    <div class="home-banner-card__text">
      <h2>Remboursement de frais</h2>
      <p>Faites une demande de remboursement en ligne, directement depuis le site.</p>
    </div>
    <span class="btn home-banner-card__btn">Accéder →</span>
  </a>
</section>

<!-- GALERIE -->
<section class="home-showcase">
  <div class="home-showcase__header">
    <h2>Galerie photos</h2>
    <a href="/pages/galerie/galerie.html" class="home-showcase__link">Voir tout →</a>
  </div>
  <?php if (!empty($recentPhotos)): ?>
  <div class="home-galerie-grid">
    <?php foreach ($recentPhotos as $photo): ?>
    <a href="/<?= h($photo['filepath']) ?>" class="home-galerie-thumb">
      <img src="/<?= h($photo['filepath']) ?>" alt="<?= h($photo['alt_text'] ?? 'Photo du club') ?>">
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="home-showcase__empty">Aucune photo publiée pour le moment.</p>
  <?php endif; ?>
</section>

<!-- PARTENAIRES -->
<section class="home-showcase">
  <div class="home-showcase__header">
    <h2>Nos partenaires</h2>
    <a href="/pages/partenaires/partenaires.php" class="home-showcase__link">En savoir plus →</a>
  </div>
  <?php if (!empty($partenaires)): ?>
  <div class="home-partenaires-grid">
    <?php foreach ($partenaires as $p): ?>
    <?php if (!empty($p['url'])): ?>
    <a href="<?= h($p['url']) ?>" target="_blank" rel="noopener" class="home-partenaire-item" title="<?= h($p['nom']) ?>">
    <?php else: ?>
    <div class="home-partenaire-item" title="<?= h($p['nom']) ?>">
    <?php endif; ?>
      <?php if (!empty($p['logo'])): ?>
      <img src="<?= h($p['logo']) ?>" alt="<?= h($p['nom']) ?>">
      <?php else: ?>
      <span class="home-partenaire-initial"><?= h(mb_strtoupper(mb_substr($p['nom'], 0, 1))) ?></span>
      <?php endif; ?>
    <?php echo !empty($p['url']) ? '</a>' : '</div>'; ?>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="home-showcase__empty">Aucun partenaire à afficher.</p>
  <?php endif; ?>
</section>

<!-- BÉNÉVOLES -->
<section class="home-banner">
  <div class="home-banner__stats">
    <div class="home-banner__stat">
      <span class="home-banner__stat-num">250</span>
      <span class="home-banner__stat-label">licenciés</span>
    </div>
    <div class="home-banner__stat-sep"></div>
    <div class="home-banner__stat">
      <span class="home-banner__stat-num">35+</span>
      <span class="home-banner__stat-label">ans d'existence</span>
    </div>
    <div class="home-banner__stat-sep"></div>
    <div class="home-banner__stat">
      <span class="home-banner__stat-num">12+</span>
      <span class="home-banner__stat-label">équipes</span>
    </div>
  </div>
  <h2>Parents &amp; Bénévoles</h2>
  <p>Vous êtes parent de joueur ou vous souhaitez devenir bénévole ? Découvrez les actions mises en place par le VBO.</p>
  <a href="/pages/leClub/benevoles.html" class="btn btn--light">Devenir bénévole</a>
</section>
