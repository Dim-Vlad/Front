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

$nomActuel = trim($_POST['nom_actuel'] ?? '');
$nom       = trim($_POST['nom'] ?? '');
$distance  = (float)str_replace(',', '.', $_POST['distance_km'] ?? '0');

if ($nomActuel === '') {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'Ville à modifier introuvable.']);
    exit;
}
if ($nom === '' || mb_strlen($nom) > 150) {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'Nom de ville requis (150 caractères max).']);
    exit;
}
if ($distance <= 0 || $distance > 5000) {
    http_response_code(400);
    ob_end_clean(); echo json_encode(['error' => 'Distance invalide.']);
    exit;
}

$pdo = get_pdo();

// Corrige en une fois le nom et/ou la distance de toutes les demandes en
// attente regroupées sous l'ancien nom (typo du demandeur, distance erronée...).
$pdo->prepare('UPDATE remboursement_villes_attente SET nom = ?, distance_declaree = ? WHERE nom = ?')
    ->execute([$nom, $distance, $nomActuel]);

log_activite($pdo, 'modification', 'remboursement_ville_attente', "Correction de l'alerte « {$nomActuel} » → « {$nom} » ({$distance} km)");

$agg = $pdo->prepare(
    "SELECT COUNT(*) AS nb, GROUP_CONCAT(DISTINCT demandeur ORDER BY created_at DESC SEPARATOR ', ') AS demandeurs
     FROM remboursement_villes_attente WHERE nom = ?"
);
$agg->execute([$nom]);
$row = $agg->fetch();

ob_end_clean();
echo json_encode(['success' => true, 'data' => [
    'nom_actuel' => $nomActuel,
    'nom'        => $nom,
    'distance_km' => $distance,
    'nb'         => (int)$row['nb'],
    'demandeurs' => $row['demandeurs'],
]]);
