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

$allowedKeys = [
    'club_adresse_ligne1', 'club_adresse_ligne2',
    'club_email', 'email_remboursement_notif',
    'arbitres_sheet_url', 'minibus_sheet_url',
    'remboursement_tarif_km',
];

$updates = [];
foreach ($allowedKeys as $key) {
    if (array_key_exists($key, $_POST)) {
        $updates[$key] = trim($_POST[$key]);
    }
}

if (isset($updates['remboursement_tarif_km'])) {
    $tarif = (float)str_replace(',', '.', $updates['remboursement_tarif_km']);
    if ($tarif <= 0 || $tarif > 10) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Tarif kilométrique invalide.']); exit;
    }
    $updates['remboursement_tarif_km'] = (string)$tarif;
}

// Adresse optionnelle : si elle est renseignée, doit être une adresse valide ;
// laissée vide, elle est supprimée (pas stockée comme chaîne vide) pour que
// send_demande.php retombe sur club_email comme destinataire par défaut.
if (isset($updates['email_remboursement_notif']) && $updates['email_remboursement_notif'] !== ''
    && !filter_var($updates['email_remboursement_notif'], FILTER_VALIDATE_EMAIL)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Adresse email de remboursement invalide.']); exit;
}

if (empty($updates)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Aucune donnée fournie']); exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        "INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?"
    );
    $del = $pdo->prepare("DELETE FROM parametres WHERE cle = ?");
    foreach ($updates as $key => $val) {
        if ($key === 'email_remboursement_notif' && $val === '') {
            $del->execute([$key]);
            continue;
        }
        $stmt->execute([$key, $val, $val]);
    }
    log_activite($pdo, 'modification', 'parametres', 'Gestion du site : ' . implode(', ', array_keys($updates)));
    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
