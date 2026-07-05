<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { ob_end_clean(); echo json_encode(['success' => false, 'error' => 'ID manquant']); exit; }

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT label FROM entraineur_sections WHERE id = ?");
    $stmt->execute([$id]);
    $section = $stmt->fetch();
    if (!$section) { ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Section introuvable']); exit; }

    $pdo->prepare("DELETE FROM entraineur_sections WHERE id = ?")->execute([$id]);
    log_activite($pdo, 'suppression', 'entraineur_sections', $section['label']);
    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
