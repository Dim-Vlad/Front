<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
ob_end_clean();
header('Content-Type: application/json');

if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$id          = (int)($_POST['id'] ?? 0);
$nom         = trim($_POST['nom'] ?? '');
$description = trim($_POST['description'] ?? '') ?: null;
$section     = $_POST['section'] ?? 'base';
$actif       = isset($_POST['actif']) && $_POST['actif'] === '1' ? 1 : 0;
$ordre       = (int)($_POST['ordre'] ?? 0);

if (!$nom || !in_array($section, ['promo', 'vedette', 'base'], true)) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
    exit;
}

try {
    $pdo = get_pdo();

    /* ── Traitement de l'image uploadée ── */
    $imagePath = null;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];

        if ($file['size'] > 15 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Image trop volumineuse (15 Mo max)']);
            exit;
        }

        $imgInfo = @getimagesize($file['tmp_name']);
        if (!$imgInfo || !in_array($imgInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            echo json_encode(['success' => false, 'error' => 'Format non supporté (JPG, PNG, WEBP)']);
            exit;
        }

        $ext      = match ($imgInfo[2]) { IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', default => 'jpg' };
        $safeName = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($nom));
        $filename = $safeName . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destDir  = __DIR__ . '/../../images/boutique/';

        if (!is_dir($destDir)) mkdir($destDir, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
            echo json_encode(['success' => false, 'error' => "Erreur lors de l'enregistrement de l'image"]);
            exit;
        }

        $imagePath = 'images/boutique/' . $filename;
    }

    /* ── Mise à jour ── */
    if ($id > 0) {
        $existing = $pdo->prepare("SELECT image_path FROM boutique_articles WHERE id = ?");
        $existing->execute([$id]);
        $row = $existing->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Article introuvable']);
            exit;
        }

        if ($imagePath) {
            // Suppression de l'ancienne image si elle est dans le dossier boutique
            if (str_starts_with($row['image_path'], 'images/boutique/')) {
                $old = __DIR__ . '/../../' . $row['image_path'];
                if (file_exists($old)) @unlink($old);
            }
        } else {
            $imagePath = $row['image_path']; // Conserver l'image existante
        }

        $pdo->prepare(
            "UPDATE boutique_articles SET nom=?, description=?, image_path=?, section=?, actif=?, ordre=? WHERE id=?"
        )->execute([$nom, $description, $imagePath, $section, $actif, $ordre, $id]);

        log_activite($pdo, 'modification', 'boutique_articles', "Article #$id modifié : $nom");
        echo json_encode(['success' => true, 'id' => $id, 'image_path' => $imagePath]);

    /* ── Création ── */
    } else {
        if (!$imagePath) {
            echo json_encode(['success' => false, 'error' => 'Une image est requise pour créer un article']);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO boutique_articles (nom, description, image_path, section, actif, ordre) VALUES (?,?,?,?,?,?)"
        );
        $stmt->execute([$nom, $description, $imagePath, $section, $actif, $ordre]);
        $newId = (int)$pdo->lastInsertId();

        log_activite($pdo, 'ajout', 'boutique_articles', "Article #$newId créé : $nom");
        echo json_encode(['success' => true, 'id' => $newId, 'image_path' => $imagePath]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
