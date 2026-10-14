<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_role('admin')) {
    http_response_code(403);
    ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']);
    exit;
}
check_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'ID invalide.']);
    exit;
}

$pdo   = get_pdo();
$check = $pdo->prepare('SELECT id FROM remboursement_villes WHERE id = ?');
$check->execute([$id]);
if (!$check->fetch()) {
    http_response_code(404);
    ob_end_clean(); echo json_encode(['error' => 'Ville introuvable.']);
    exit;
}

$nom      = trim($_POST['nom'] ?? '');
$distance = (float)str_replace(',', '.', $_POST['distance_km'] ?? '0');

if ($nom === '' || mb_strlen($nom) > 100) {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'Nom de ville requis (100 caractères max).']);
    exit;
}
if ($distance <= 0 || $distance > 5000) {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'Distance invalide.']);
    exit;
}

$dup = $pdo->prepare('SELECT id FROM remboursement_villes WHERE nom = ? AND id != ?');
$dup->execute([$nom, $id]);
if ($dup->fetch()) {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'Une autre ville porte déjà ce nom.']);
    exit;
}

$pdo->prepare('UPDATE remboursement_villes SET nom = ?, distance_km = ? WHERE id = ?')->execute([$nom, $distance, $id]);

log_activite($pdo, 'modification', 'remboursement_ville', "Modification de la ville « {$nom} » ({$distance} km)");

ob_end_clean();
echo json_encode(['success' => true, 'data' => ['id' => $id, 'nom' => $nom, 'distance_km' => $distance]]);
