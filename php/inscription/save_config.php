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
$tarifs    = ['jeunes' => [], 'seniors' => []];
$tarifsRaw = trim($_POST['tarifs_json'] ?? '');

if ($tarifsRaw !== '') {
    $decoded = json_decode($tarifsRaw, true);
    if (is_array($decoded)) {
        foreach (['jeunes', 'seniors'] as $group) {
            if (!isset($decoded[$group]) || !is_array($decoded[$group])) continue;
            foreach ($decoded[$group] as $card) {
                $label = substr(strip_tags(trim($card['label'] ?? '')), 0, 100);
                if ($label === '') continue;
                $tarifs[$group][] = [
                    'value'   => substr(strip_tags(trim($card['value'] ?? $label)), 0, 100),
                    'label'   => $label,
                    'prix'    => substr(strip_tags(trim($card['prix']    ?? '')), 0, 200),
                    'cheques' => substr(strip_tags(trim($card['cheques'] ?? '')), 0, 200),
                ];
            }
        }
    }
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
