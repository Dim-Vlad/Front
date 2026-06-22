<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$photoId = (int)($_POST['photo_id'] ?? 0);
$action  = $_POST['action'] ?? '';
$raison  = htmlspecialchars(trim($_POST['raison'] ?? ''), ENT_QUOTES, 'UTF-8');

if ($photoId <= 0 || !in_array($action, ['approuver', 'rejeter'], true)) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

try {
    $pdo = get_pdo();

    $stmt = $pdo->prepare(
        "SELECT p.*, s.slug AS saison_slug, s.label AS saison_label
            FROM photos_galerie p
            JOIN saisons_galerie s ON s.id = p.saison_id
            WHERE p.id = ? AND p.statut = 'en_attente'
            LIMIT 1"
    );
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();

    if (!$photo) {
        echo json_encode(['success' => false, 'error' => 'Photo introuvable ou déjà traitée']);
        exit;
    }

    $oldPath = __DIR__ . '/../../' . $photo['filepath'];

    if ($action === 'approuver') {
        $destDir     = __DIR__ . '/../../photos/galerie/' . $photo['saison_slug'] . '/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);

        $filename    = basename($photo['filepath']);
        $newPath     = $destDir . $filename;
        $newFilepath = 'photos/galerie/' . $photo['saison_slug'] . '/' . $filename;

        if (file_exists($oldPath) && rename($oldPath, $newPath)) {
            $ordreStmt = $pdo->prepare("SELECT COALESCE(MAX(ordre), 0) + 1 FROM photos_galerie WHERE saison_id = ?");
            $ordreStmt->execute([$photo['saison_id']]);
            $nextOrdre = (int)$ordreStmt->fetchColumn() ?: 1;

            $pdo->prepare(
                "UPDATE photos_galerie SET statut = 'publiee', filepath = ?, ordre = ? WHERE id = ?"
            )->execute([$newFilepath, $nextOrdre, $photoId]);
        } else {
            $pdo->prepare("UPDATE photos_galerie SET statut = 'publiee' WHERE id = ?")->execute([$photoId]);
        }

        log_activite($pdo, 'APPROVE', 'photos_galerie', "Photo #$photoId approuvée (saison {$photo['saison_label']})");

    } else {
        if (file_exists($oldPath)) @unlink($oldPath);
        $pdo->prepare("DELETE FROM photos_galerie WHERE id = ?")->execute([$photoId]);

        $logDetail = "Photo #$photoId rejetée (saison {$photo['saison_label']})";
        if ($raison) $logDetail .= " — raison : $raison";
        log_activite($pdo, 'REJECT', 'photos_galerie', $logDetail);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
