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

$saison = trim($_POST['saison'] ?? '');
if (!preg_match('/^\d{4}-\d{4}$/', $saison)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Format de saison invalide (ex: 2026-2027)']); exit;
}

// Reconstruction des tarifs depuis POST
$tarifs = [
    'jeunes' => [],
    'seniors' => [],
];

$labelsJeunes  = ['M7 – Baby Volley', 'M9 à M15', 'M18 et M21'];
$valuesJeunes  = ['M7', 'M9-M15', 'M18-M21'];
$labelsSeniors = ['Seniors', 'Seniors Loisirs', 'Compet Lib'];
$valuesSeniors = ['Seniors', 'Seniors Loisirs', 'Compet Lib'];

foreach ($valuesJeunes as $i => $val) {
    $tarifs['jeunes'][] = [
        'value'   => $val,
        'label'   => $labelsJeunes[$i],
        'prix'    => trim($_POST['prix_j_' . $i]    ?? ''),
        'cheques' => trim($_POST['cheques_j_' . $i] ?? ''),
    ];
}
foreach ($valuesSeniors as $i => $val) {
    $tarifs['seniors'][] = [
        'value'   => $val,
        'label'   => $labelsSeniors[$i],
        'prix'    => trim($_POST['prix_s_' . $i]    ?? ''),
        'cheques' => trim($_POST['cheques_s_' . $i] ?? ''),
    ];
}

try {
    $pdo = get_pdo();
    $upsert = $pdo->prepare("INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?");
    $upsert->execute(['inscription_saison', $saison, $saison]);
    $tarifsJson = json_encode($tarifs, JSON_UNESCAPED_UNICODE);
    $upsert->execute(['inscription_tarifs', $tarifsJson, $tarifsJson]);
    log_activite($pdo, 'modification', 'inscription', 'Saison ' . $saison);
    ob_end_clean(); echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
