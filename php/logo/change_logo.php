<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']);
    exit;
}

$pdo      = get_pdo();
$logoPath = null;
$baseDir  = realpath(__DIR__ . '/../../images/logo-club');

// ── Upload d'un nouveau fichier ───────────────────────────────────
if (!empty($_FILES['logo_upload']['name'])) {
    $file    = $_FILES['logo_upload'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'upload']);
        exit;
    }

    if (!in_array($ext, $allowed, true)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Format non supporté (jpg, png, webp, gif)']);
        exit;
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 3 Mo)']);
        exit;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Fichier non reconnu comme image']);
        exit;
    }
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($imageInfo['mime'], $allowedMimes, true)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Format non supporté (jpg, png, webp, gif)']);
        exit;
    }

    $filename = 'logo-' . date('Ymd-His') . '.' . $ext;
    $dest     = $baseDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Impossible d\'enregistrer le fichier']);
        exit;
    }

    $logoPath = 'images/logo-club/' . $filename;

// ── Sélection d'un logo existant ─────────────────────────────────
} elseif (!empty($_POST['logo_path'])) {
    $candidate = $_POST['logo_path'];
    $realPath  = realpath(__DIR__ . '/../../' . $candidate);

    if (!$realPath || !str_starts_with($realPath, $baseDir . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Chemin non autorisé']);
        exit;
    }

    $logoPath = $candidate;

} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Aucun logo fourni']);
    exit;
}

// ── Sauvegarde en base ────────────────────────────────────────────
$stmt = $pdo->prepare(
    "INSERT INTO parametres (cle, valeur) VALUES ('logo_club', ?)
     ON DUPLICATE KEY UPDATE valeur = ?"
);
$stmt->execute([$logoPath, $logoPath]);

log_activite($pdo, 'modifier', 'logo_club', $logoPath);

ob_end_clean();
echo json_encode(['success' => true, 'logo' => $logoPath]);
