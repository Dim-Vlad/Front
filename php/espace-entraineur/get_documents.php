<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['entraineur', 'arbitre', 'bureau', 'moderateur', 'admin'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}

try {
    $pdo      = get_pdo();
    $sections = $pdo->query("SELECT id, label, ordre FROM entraineur_sections ORDER BY ordre, id")->fetchAll();
    $docs     = $pdo->query("SELECT id, section_id, label, url, type, ordre FROM entraineur_documents ORDER BY section_id, ordre, id")->fetchAll();

    $bySection = [];
    foreach ($docs as $doc) {
        $bySection[$doc['section_id']][] = $doc;
    }
    foreach ($sections as &$s) {
        $s['documents'] = $bySection[$s['id']] ?? [];
    }
    unset($s);

    ob_end_clean();
    echo json_encode(['success' => true, 'data' => $sections]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
