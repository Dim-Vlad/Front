<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur','admin'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}

$type  = $_POST['type']  ?? '';
$label = trim($_POST['label'] ?? '');
if (!in_array($type, ['pvag','statuts'], true) || $label === '') {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Données invalides']); exit;
}

if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Fichier PDF manquant']); exit;
}

$mime = mime_content_type($_FILES['fichier']['tmp_name']);
if ($mime !== 'application/pdf') {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Format PDF uniquement']); exit;
}

$dir  = $type === 'pvag' ? '/documents/pv-ag/' : '/documents/statuts-RI/';
$slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($label));
$dest = $_SERVER['DOCUMENT_ROOT'] . $dir . $slug . '.pdf';
if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $dest)) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur lors de l\'enregistrement du fichier']); exit;
}

$path = $dir . $slug . '.pdf';
try {
    $pdo = get_pdo();
    $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM staff_documents WHERE type=?");
    $stmtMax->execute([$type]);
    $ordre = (int)$stmtMax->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO staff_documents (type,label,path,ordre) VALUES (?,?,?,?)");
    $stmt->execute([$type,$label,$path,$ordre]);
    $id = (int)$pdo->lastInsertId();

    log_activite($pdo, 'Ajout document staff', $type, $label);
    ob_end_clean();
    echo json_encode(['success'=>true,'data'=>compact('id','type','label','path','ordre')]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
