<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
ob_end_clean();
header('Content-Type: application/json');

if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT nom, image_path FROM boutique_articles WHERE id = ?");
    $stmt->execute([$id]);
    $article = $stmt->fetch();

    if (!$article) {
        echo json_encode(['success' => false, 'error' => 'Article introuvable']);
        exit;
    }

    // Suppression du fichier image uniquement s'il est dans le dossier boutique
    if (str_starts_with($article['image_path'], 'images/boutique/')) {
        $filePath = __DIR__ . '/../../' . $article['image_path'];
        if (file_exists($filePath)) @unlink($filePath);
    }

    $pdo->prepare("DELETE FROM boutique_articles WHERE id = ?")->execute([$id]);
    log_activite($pdo, 'suppression', 'boutique_articles', "Article #$id supprimé : {$article['nom']}");

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
