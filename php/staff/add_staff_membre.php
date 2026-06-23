<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur','admin'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}

$section     = $_POST['section']     ?? '';
$nom         = trim($_POST['nom']    ?? '');
$sous_titre  = trim($_POST['sous_titre'] ?? '');
$photo       = trim($_POST['photo']  ?? '/images/icones/interrogation-point.png');
$description = trim($_POST['description'] ?? '') ?: null;
$lien_vcard  = trim($_POST['lien_vcard']  ?? '') ?: null;

$validSections = ['bureau','membres','commissions','entraineurs'];
if (!in_array($section, $validSections, true) || $nom === '') {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Données invalides']); exit;
}

try {
    $pdo = get_pdo();
    $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM staff_membres WHERE section=?");
    $stmtMax->execute([$section]);
    $ordre = (int)$stmtMax->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO staff_membres (section,nom,sous_titre,photo,description,lien_vcard,visible,ordre) VALUES (?,?,?,?,?,?,1,?)");
    $stmt->execute([$section,$nom,$sous_titre,$photo,$description,$lien_vcard,$ordre]);
    $id = (int)$pdo->lastInsertId();

    log_activite($pdo, 'Ajout membre staff', $section, "$nom ($sous_titre)");
    ob_end_clean(); echo json_encode(['success'=>true,'data'=>compact('id','section','nom','sous_titre','photo','description','lien_vcard','ordre') + ['visible'=>1]]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
