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

$pdo = get_pdo();

$exists = $pdo->prepare('SELECT id FROM remboursement_villes WHERE nom = ?');
$exists->execute([$nom]);
if ($exists->fetch()) {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'Cette ville existe déjà dans la liste.']);
    exit;
}

$pdo->prepare('INSERT INTO remboursement_villes (nom, distance_km) VALUES (?, ?)')->execute([$nom, $distance]);
$newId = (int)$pdo->lastInsertId();

// La ville est désormais connue : l'alerte « à vérifier » n'a plus lieu d'être.
$pdo->prepare('DELETE FROM remboursement_villes_attente WHERE nom = ?')->execute([$nom]);

log_activite($pdo, 'ajout', 'remboursement_ville', "Ajout de la ville « {$nom} » ({$distance} km)");

ob_end_clean();
echo json_encode(['success' => true, 'data' => ['id' => $newId, 'nom' => $nom, 'distance_km' => $distance]]);
