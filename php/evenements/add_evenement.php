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
$imageUrl  = null;

if (!empty($_FILES['image']['name'])) {
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $codes = [
            UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite php.ini)',
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux',
            UPLOAD_ERR_PARTIAL    => 'Téléversement partiel',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire PHP introuvable',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier temporaire',
        ];
        $msg = $codes[$_FILES['image']['error']] ?? 'Erreur d\'upload (' . $_FILES['image']['error'] . ')';
        ob_end_clean(); echo json_encode(['success'=>false,'error'=>$msg]); exit;
    }
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $mime    = mime_content_type($_FILES['image']['tmp_name']);
    if (!$mime || !in_array($mime, $allowed)) {
        ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Format non autorisé (jpg, png, webp, gif)']); exit;
    }
    $exts     = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $ext      = $exts[$mime] ?? 'jpg';
    $uploadDir = __DIR__ . '/../../documents/evenements/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $slug     = preg_replace('/[^a-z0-9]+/', '-', strtolower($titre));
    $filename = $slug . '-' . time() . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
        ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Impossible d\'écrire dans documents/evenements/']); exit;
    }
    $imageUrl = '/documents/evenements/' . $filename;
}

try {
    $pdo = get_pdo();
    $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM evenements WHERE termine=?");
    $stmtMax->execute([$termine]);
    $ordre = (int)$stmtMax->fetchColumn();

    $stmt = $pdo->prepare(
        "INSERT INTO evenements (titre, description, date_debut, date_fin, lieu, lien_url, lien_label, termine, ordre, image_url)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([$titre, $desc, $dateDebut, $dateFin, $lieu, $lienUrl, $lienLabel, $termine, $ordre, $imageUrl]);
    $id = (int)$pdo->lastInsertId();

    log_activite($pdo, 'Ajout événement', 'evenements', $titre);
    ob_end_clean(); echo json_encode(['success'=>true, 'id'=>$id]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
