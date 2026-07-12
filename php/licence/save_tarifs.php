<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    http_response_code(403); ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']); exit;
}
check_csrf();

$raw = trim($_POST['tarifs_json'] ?? '');
if ($raw === '') {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Données manquantes.']); exit;
}

$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Format invalide.']); exit;
}

$tarifs = [];
foreach ($input as $row) {
    $label = substr(strip_tags(trim($row['label'] ?? '')), 0, 100);
    if ($label === '') continue;
    $tarifs[] = [
        'label'      => $label,
        'prix'       => max(0, min(9999, (int)($row['prix'] ?? 0))),
        'supplement' => !empty($row['supplement']),
        'comment'    => substr(strip_tags(trim($row['comment'] ?? '')), 0, 300),
    ];
}

if (count($tarifs) === 0) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Aucune ligne valide.']); exit;
}

$json = json_encode($tarifs, JSON_UNESCAPED_UNICODE);
$note = substr(strip_tags(trim($_POST['tarifs_note'] ?? '')), 0, 500);

$pdo  = get_pdo();
$stmt = $pdo->prepare('INSERT INTO licence_config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?');
$stmt->execute(['tarifs_json', $json, $json]);
$stmt->execute(['tarifs_note', $note, $note]);

log_activite($pdo, 'modification', 'licence_config', 'Tarifs licences mis à jour (' . count($tarifs) . ' lignes)');

ob_end_clean(); echo json_encode(['success' => true, 'tarifs' => $tarifs, 'note' => $note]);
