<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$label      = trim($_POST['label'] ?? '');
$slug       = trim($_POST['slug'] ?? '');
$activer    = !empty($_POST['activer']);
$flickr_url = trim($_POST['flickr_url'] ?? '') ?: null;

if (!$label) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Label requis (ex : 2026-2027)']);
    exit;
}

// Auto-generate slug from label if not provided
if (!$slug) {
    $slug = 'saison-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($label));
    $slug = trim($slug, '-');
}
if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Slug invalide (lettres minuscules, chiffres, tirets)']);
    exit;
}

try {
    $pdo = get_pdo();

    // Check slug uniqueness
    $existing = $pdo->prepare("SELECT id FROM saisons_galerie WHERE slug = ? LIMIT 1");
    $existing->execute([$slug]);
    if ($existing->fetch()) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Ce slug existe déjà : ' . $slug]);
        exit;
    }

    $pdo->beginTransaction();

    if ($activer) {
        // Archive the current active season
        $pdo->exec("UPDATE saisons_galerie SET est_active = 0 WHERE est_active = 1");
    }

    // Get highest ordre to place new season first
    $minOrdre = (int)$pdo->query("SELECT MIN(ordre) FROM saisons_galerie")->fetchColumn();
    $newOrdre = $activer ? 0 : $minOrdre - 1;

    $pdo->prepare(
        "INSERT INTO saisons_galerie (label, slug, est_active, flickr_url, ordre) VALUES (?, ?, ?, ?, ?)"
    )->execute([$label, $slug, $activer ? 1 : 0, $flickr_url, $newOrdre]);

    $newId = (int)$pdo->lastInsertId();

    // Create upload directory for this season
    $dir = __DIR__ . '/../../photos/galerie/' . $slug . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo->commit();

    log_activite($pdo, 'CREATE', 'saison_galerie', "Saison $label ($slug) créée" . ($activer ? ', activée' : ''));

    ob_end_clean(); echo json_encode(['success' => true, 'id' => $newId, 'slug' => $slug, 'active' => $activer]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
