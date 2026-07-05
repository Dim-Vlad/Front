<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']); exit;
}
check_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$ids = array_values(array_filter(array_map('intval', $_POST['ids'] ?? []), fn($id) => $id > 0));

if (count($ids) < 2) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Données invalides']); exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('UPDATE staff_documents SET ordre = ? WHERE id = ?');
    foreach ($ids as $ordre => $id) {
        $stmt->execute([$ordre + 1, $id]);
    }
    log_activite($pdo, 'modification', 'staff_documents', 'Réordonnancement');
    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
