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

$id    = (int)($_POST['id'] ?? 0);
$label = trim($_POST['label'] ?? '');

if ($label === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Nom de section requis']); exit;
}

try {
    $pdo = get_pdo();
    if ($id > 0) {
        $pdo->prepare("UPDATE entraineur_sections SET label = ? WHERE id = ?")->execute([$label, $id]);
        log_activite($pdo, 'modification', 'entraineur_sections', $label);
    } else {
        $ord   = $pdo->query("SELECT COALESCE(MAX(ordre), 0) + 1 FROM entraineur_sections")->fetchColumn();
        $pdo->prepare("INSERT INTO entraineur_sections (label, ordre) VALUES (?, ?)")->execute([$label, $ord]);
        $id = (int)$pdo->lastInsertId();
        log_activite($pdo, 'ajout', 'entraineur_sections', $label);
    }
    ob_end_clean();
    echo json_encode(['success' => true, 'id' => $id, 'label' => $label]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
