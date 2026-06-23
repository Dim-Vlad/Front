<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur','admin'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}

$titre = trim($_POST['titre'] ?? '');
if ($titre === '') {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Le titre est requis']); exit;
}

$dateDebut = trim($_POST['date_debut'] ?? '') ?: null;
$dateFin   = trim($_POST['date_fin']   ?? '') ?: null;
$lieu      = trim($_POST['lieu']       ?? '');
$desc      = trim($_POST['description'] ?? '');
$lienUrl   = trim($_POST['lien_url']   ?? '');
$lienLabel = trim($_POST['lien_label'] ?? '') ?: 'En savoir plus';
$termine   = isset($_POST['termine']) ? 1 : 0;

try {
    $pdo = get_pdo();
    $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM evenements WHERE termine=?");
    $stmtMax->execute([$termine]);
    $ordre = (int)$stmtMax->fetchColumn();

    $stmt = $pdo->prepare(
        "INSERT INTO evenements (titre, description, date_debut, date_fin, lieu, lien_url, lien_label, termine, ordre)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([$titre, $desc, $dateDebut, $dateFin, $lieu, $lienUrl, $lienLabel, $termine, $ordre]);
    $id = (int)$pdo->lastInsertId();

    log_activite($pdo, 'Ajout événement', 'evenements', $titre);
    ob_end_clean(); echo json_encode(['success'=>true, 'id'=>$id]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
