<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}
check_csrf();

$id             = (int)($_POST['id'] ?? 0);
$titre          = trim($_POST['titre']          ?? '');
$saison         = trim($_POST['saison']         ?? '') ?: null;
$description    = trim($_POST['description']    ?? '') ?: null;
$inscriptionUrl = trim($_POST['inscription_url'] ?? '') ?: null;
$sheetUrl       = trim($_POST['sheet_url']       ?? '') ?: null;

if ($titre === '') {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Le titre est requis']); exit;
}

try {
    $pdo = get_pdo();
    if ($id) {
        $pdo->prepare("UPDATE tournois SET titre=?, saison=?, description=?, inscription_url=?, sheet_url=? WHERE id=?")
            ->execute([$titre, $saison, $description, $inscriptionUrl, $sheetUrl, $id]);
        log_activite($pdo, 'Modification page tournoi', 'tournois', $titre);
        ob_end_clean(); echo json_encode(['success'=>true, 'id'=>$id]);
    } else {
        $pdo->prepare("INSERT INTO tournois (titre, saison, description, inscription_url, sheet_url) VALUES (?,?,?,?,?)")
            ->execute([$titre, $saison, $description, $inscriptionUrl, $sheetUrl]);
        $newId = (int)$pdo->lastInsertId();
        log_activite($pdo, 'Création page tournoi', 'tournois', $titre);
        ob_end_clean(); echo json_encode(['success'=>true, 'id'=>$newId]);
    }
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
