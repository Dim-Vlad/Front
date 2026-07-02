<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$adversaire  = trim($_POST['adversaire'] ?? '');
$date_match  = trim($_POST['date_match'] ?? '');
$competition = trim($_POST['competition'] ?? '') ?: null;
$domicile    = isset($_POST['domicile']) ? 1 : 0;

if ($adversaire === '' || $date_match === '') {
    echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']); exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $date_match)) {
    echo json_encode(['success' => false, 'error' => 'Date invalide']); exit;
}

$date_mysql = str_replace('T', ' ', $date_match) . ':00';

try {
    $pdo = get_pdo();
    $pdo->prepare('INSERT INTO pronostics_matchs (adversaire, date_match, competition, domicile, created_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([$adversaire, $date_mysql, $competition, $domicile, (int)$_SESSION['user_id']]);
    log_activite($pdo, 'creation', 'pronostics', 'Match créé : VBO vs ' . $adversaire . ' (' . $date_mysql . ')');
    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
