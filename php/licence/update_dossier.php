<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_role('admin')) {
    http_response_code(403); ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']); exit;
}
check_csrf();

if (!isset($_FILES['dossier']) || $_FILES['dossier']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Aucun fichier reçu.']); exit;
}

$tmp  = $_FILES['dossier']['tmp_name'];
$type = mime_content_type($tmp);
if ($type !== 'application/pdf') {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Seuls les fichiers PDF sont acceptés.']); exit;
}

$dest = __DIR__ . '/../../documents/dossier-partenaires.pdf';
if (!move_uploaded_file($tmp, $dest)) {
    http_response_code(500); ob_end_clean(); echo json_encode(['error' => 'Erreur lors de l\'enregistrement.']); exit;
}

$pdo = get_pdo();
log_activite($pdo, 'modification', 'dossier', 'Mise à jour du dossier de partenariat (PDF)');

ob_end_clean(); echo json_encode(['success' => true]);
