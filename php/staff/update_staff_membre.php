<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur','admin'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}
check_csrf();

$id          = (int)($_POST['id'] ?? 0);
$nom         = trim($_POST['nom']        ?? '');
$sous_titre  = trim($_POST['sous_titre'] ?? '');
$photo       = trim($_POST['photo']      ?? '/images/icones/interrogation-point.png');
$description = trim($_POST['description'] ?? '') ?: null;
$lien_vcard  = trim($_POST['lien_vcard']  ?? '') ?: null;

if (!$id) { ob_end_clean(); echo json_encode(['success'=>false,'error'=>'ID manquant']); exit; }

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("UPDATE staff_membres SET nom=?,sous_titre=?,photo=?,description=?,lien_vcard=? WHERE id=?");
    $stmt->execute([$nom,$sous_titre,$photo,$description,$lien_vcard,$id]);
    log_activite($pdo, 'Modification membre staff', 'staff', "$nom ($sous_titre)");
    ob_end_clean(); echo json_encode(['success'=>true,'data'=>compact('id','nom','sous_titre','photo','description','lien_vcard')]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
