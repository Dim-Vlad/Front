<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    http_response_code(403); ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']); exit;
}
check_csrf();

$id    = (int)($_POST['id'] ?? 0);
$label = trim($_POST['label'] ?? '');
if ($id <= 0 || $label === '') {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'ID et libellé requis.']); exit;
}
$numero = filter_var($_POST['numero'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 255]]);
if ($numero === false || $numero === null) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Le numéro doit être un entier entre 0 et 255.']); exit;
}

$pdo  = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM licence_documents WHERE id = ?');
$stmt->execute([$id]);
$doc  = $stmt->fetch();
if (!$doc) { http_response_code(404); ob_end_clean(); echo json_encode(['error' => 'Document introuvable.']); exit; }

$newPath = $doc['path'];

if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['fichier']['tmp_name'];
    $type = mime_content_type($tmp);
    if ($type !== 'application/pdf') {
        http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Seuls les fichiers PDF sont acceptés.']); exit;
    }
    $uploadDir = __DIR__ . '/../../documents/doc-lience/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = $doc['slug'] . '.pdf';
    if (!move_uploaded_file($tmp, $uploadDir . $filename)) {
        http_response_code(500); ob_end_clean(); echo json_encode(['error' => 'Erreur lors de l\'enregistrement.']); exit;
    }
    $newPath = '/documents/doc-lience/' . $filename;
}

$pdo->prepare('UPDATE licence_documents SET label = ?, numero = ?, path = ? WHERE id = ?')
    ->execute([$label, $numero, $newPath, $id]);

log_activite($pdo, 'modification', 'licence_document', "Modification du document « {$doc['slug']} » → « {$label} » (n° {$numero})");

$row = $pdo->prepare('SELECT * FROM licence_documents WHERE id = ?');
$row->execute([$id]);
ob_end_clean(); echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
