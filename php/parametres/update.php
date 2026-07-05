<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$staticKeys = ['arbitres_sheet_url', 'minibus_sheet_url', 'presences_sheet_url'];

$cle    = $_POST['cle']    ?? '';
$valeur = trim($_POST['valeur'] ?? '');

$isTournoiKey = (bool)preg_match('/^tournoi_\d+_(inscription|sheet)$/', $cle);
if (!in_array($cle, $staticKeys, true) && !$isTournoiKey) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Clé invalide']); exit;
}
if ($valeur === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'URL requise']); exit;
}

try {
    $pdo = get_pdo();
    $pdo->prepare("INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?")
        ->execute([$cle, $valeur, $valeur]);
    log_activite($pdo, 'modification', 'parametres', $cle . ' → ' . substr($valeur, 0, 80));
    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
