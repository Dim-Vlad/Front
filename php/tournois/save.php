<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}
check_csrf();

$id               = (int)($_POST['id'] ?? 0);
$titre            = trim($_POST['titre']            ?? '');
$validTypes       = ['tournoi','match','loto','tombola','stage','autre'];
$type             = in_array($_POST['type'] ?? '', $validTypes) ? $_POST['type'] : 'tournoi';
$saison           = trim($_POST['saison']           ?? '') ?: null;
$description      = trim($_POST['description']      ?? '') ?: null;
$inscriptionUrl   = trim($_POST['inscription_url']   ?? '') ?: null;
$inscriptionTel   = trim($_POST['inscription_tel']   ?? '') ?: null;
$inscriptionEmail = trim($_POST['inscription_email'] ?? '') ?: null;
$sheetUrl         = trim($_POST['sheet_url']         ?? '') ?: null;

if ($inscriptionUrl !== null && !preg_match('#^https?://#i', $inscriptionUrl)) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'URL d\'inscription invalide']); exit;
}
if ($sheetUrl !== null && !preg_match('#^https?://#i', $sheetUrl)) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'URL feuille invalide']); exit;
}
if ($titre === '') {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Le titre est requis']); exit;
}

try {
    $pdo = get_pdo();
    if ($id) {
        $pdo->prepare(
            "UPDATE tournois SET type=?, titre=?, saison=?, description=?, inscription_url=?, inscription_tel=?, inscription_email=?, sheet_url=? WHERE id=?"
        )->execute([$type, $titre, $saison, $description, $inscriptionUrl, $inscriptionTel, $inscriptionEmail, $sheetUrl, $id]);
        log_activite($pdo, 'Modification page tournoi', 'tournois', $titre);
        ob_end_clean(); echo json_encode(['success'=>true, 'id'=>$id]);
    } else {
        $pdo->prepare(
            "INSERT INTO tournois (type, titre, saison, description, inscription_url, inscription_tel, inscription_email, sheet_url) VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$type, $titre, $saison, $description, $inscriptionUrl, $inscriptionTel, $inscriptionEmail, $sheetUrl]);
        $newId = (int)$pdo->lastInsertId();
        log_activite($pdo, 'Création page tournoi', 'tournois', $titre);
        ob_end_clean(); echo json_encode(['success'=>true, 'id'=>$newId]);
    }
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
