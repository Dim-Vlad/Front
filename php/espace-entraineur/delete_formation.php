<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}
check_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'ID invalide']); exit;
}

try {
    $pdo   = get_pdo();
    $check = $pdo->prepare("SELECT label, url, type FROM formations WHERE id = ?");
    $check->execute([$id]);
    $formation = $check->fetch();
    if (!$formation) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Formation introuvable']); exit;
    }

    if (in_array($formation['type'], ['pdf', 'word', 'excel'], true) && $formation['url'] && str_starts_with($formation['url'], '/documents/formations/')) {
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $formation['url'];
        if (file_exists($filePath)) unlink($filePath);
    }

    $pdo->prepare("DELETE FROM formations WHERE id = ?")->execute([$id]);
    log_activite($pdo, 'suppression', 'formation', "Suppression de « {$formation['label']} »");

    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
