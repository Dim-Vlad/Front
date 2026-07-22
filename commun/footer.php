<?php
require_once __DIR__ . '/../php/auth.php';

function _footer_param(PDO $pdo, string $key, string $default): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $cache[$key] = $row ? $row['valeur'] : $default;
        } catch (Exception $e) {
            $cache[$key] = $default;
        }
    }
    return $cache[$key];
}

try {
    $pdo      = get_pdo();
    $adresse1 = _footer_param($pdo, 'club_adresse_ligne1', '34 Allée des Bleuets');
    $adresse2 = _footer_param($pdo, 'club_adresse_ligne2', '83 190 Ollioules');
    $reseaux  = $pdo->query('SELECT nom, url, logo FROM reseaux_sociaux ORDER BY ordre ASC, id ASC')->fetchAll();
} catch (Exception $e) {
    $adresse1 = '34 Allée des Bleuets';
    $adresse2 = '83 190 Ollioules';
    $reseaux  = [];
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<footer class="footer">
    <div class="footer-content">
        <div class="footer-col adresse">
            <p class="footer-title">Volley Ball Ollioulais</p>
            <p><?= h($adresse1) ?><br><?= h($adresse2) ?></p>
        </div>
        <div class="footer-col liens">
            <p class="footer-title">Contact</p>
            <a href="/pages/nousContacter.html">Nous contacter</a>
        </div>
        <div class="footer-col réseaux">
            <p class="footer-title">Réseaux</p>
            <div class="social-icons">
                <?php foreach ($reseaux as $r): ?>
                <a href="<?= h($r['url']) ?>" target="_blank" rel="noopener" aria-label="<?= h($r['nom']) ?>">
                    <?php if ($r['logo']): ?>
                    <img src="<?= h($r['logo']) ?>" alt="<?= h($r['nom']) ?>" class="social-icon">
                    <?php else: ?>
                    <span class="social-icon social-icon--fallback"><?= h(strtoupper(substr($r['nom'], 0, 1))) ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="footer-legal-links">
        <a href="/pages/legal/mentions-legales.html">Mentions légales</a>
        <span aria-hidden="true">·</span>
        <a href="/pages/legal/politique-confidentialite.html">Politique de confidentialité</a>
    </div>
    <div class="footer-copyright">
        <p>&copy; 2024 Site VBO / <a href="/vCard/vCard-DG-Dev.html">DG-Dev</a>. Tous droits réservés.</p>
    </div>
</footer>
