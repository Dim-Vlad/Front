<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

try {
    $pdo = get_pdo();

    $saisonId = (int)($_POST['saison_id'] ?? 0);
    if ($saisonId <= 0) {
        // Default to active season
        $saison = $pdo->query("SELECT * FROM saisons_galerie WHERE est_active = 1 LIMIT 1")->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM saisons_galerie WHERE id = ? LIMIT 1");
        $stmt->execute([$saisonId]);
        $saison = $stmt->fetch();
    }

    if (!$saison) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Saison introuvable ou aucune saison active']);
        exit;
    }

    $files = $_FILES['photos'] ?? null;
    if (!$files || empty($files['name'][0])) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Aucune photo sélectionnée']);
        exit;
    }

    $maxFiles    = 30;
    $maxSize     = 15 * 1024 * 1024;
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $uploadDir   = __DIR__ . '/../../photos/galerie/' . $saison['slug'] . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ordreStmt = $pdo->prepare("SELECT COALESCE(MAX(ordre), 0) + 1 FROM photos_galerie WHERE saison_id = ?");
    $ordreStmt->execute([$saison['id']]);
    $nextOrdre = (int)$ordreStmt->fetchColumn() ?: 1;

    $count  = min(count($files['name']), $maxFiles);
    $saved  = 0;
    $errors = [];
    $user   = current_user();

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['size'][$i] > $maxSize) {
            $errors[] = $files['name'][$i] . ' : dépasse 15 Mo';
            continue;
        }

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $errors[] = $files['name'][$i] . ' : format non accepté';
            continue;
        }

        $imageInfo = @getimagesize($files['tmp_name'][$i]);
        if (!$imageInfo || !in_array($imageInfo['mime'], $allowedMimes, true)) {
            $errors[] = $files['name'][$i] . ' : non reconnu comme image';
            continue;
        }

        $safe     = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($files['name'][$i], PATHINFO_FILENAME));
        $filename = $safe . '-' . date('Ymd') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $pdo->prepare(
                "INSERT INTO photos_galerie (saison_id, filepath, alt_text, statut, uploaded_by, ordre)
                 VALUES (?, ?, ?, 'publiee', ?, ?)"
            )->execute([
                $saison['id'],
                'photos/galerie/' . $saison['slug'] . '/' . $filename,
                '',
                $user['id'],
                $nextOrdre + $saved,
            ]);
            $saved++;
        }
    }

    if ($saved > 0) {
        log_activite($pdo, 'UPLOAD', 'photos_galerie', "$saved photo(s) ajoutée(s) à la saison {$saison['label']}");
    }

    ob_end_clean(); echo json_encode(['success' => true, 'count' => $saved, 'errors' => $errors]);

} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
