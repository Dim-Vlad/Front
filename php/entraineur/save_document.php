<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$id         = (int)($_POST['id'] ?? 0);
$section_id = (int)($_POST['section_id'] ?? 0);
$label      = trim($_POST['label'] ?? '');
$type       = in_array($_POST['type'] ?? '', ['pdf', 'link'], true) ? $_POST['type'] : '';

if ($section_id < 1 || $label === '' || $type === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Données invalides']); exit;
}

try {
    $pdo = get_pdo();

    $sec = $pdo->prepare("SELECT id FROM entraineur_sections WHERE id = ?");
    $sec->execute([$section_id]);
    if (!$sec->fetch()) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Section introuvable']); exit;
    }

    $existingUrl = null;
    if ($id > 0) {
        $e = $pdo->prepare("SELECT url FROM entraineur_documents WHERE id = ?");
        $e->execute([$id]);
        $existing = $e->fetch();
        if (!$existing) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Document introuvable']); exit;
        }
        $existingUrl = $existing['url'];
    }

    $url = null;

    if ($type === 'pdf') {
        $hasFichier = isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK;
        if (!$hasFichier && $id === 0) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Fichier PDF requis']); exit;
        }
        if ($hasFichier) {
            if (mime_content_type($_FILES['fichier']['tmp_name']) !== 'application/pdf') {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Format PDF uniquement']); exit;
            }
            $slug  = preg_replace('/[^a-z0-9]+/', '-', strtolower($label));
            $fname = $slug . '-' . time() . '.pdf';
            $dir   = $_SERVER['DOCUMENT_ROOT'] . '/documents/entraineur/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $dir . $fname)) {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => "Erreur d'enregistrement du fichier"]); exit;
            }
            $url = '/documents/entraineur/' . $fname;
        } else {
            $url = $existingUrl;
        }
    } else {
        $url = trim($_POST['url'] ?? '');
        if ($url === '') {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => 'URL manquante']); exit;
        }
    }

    if ($id > 0) {
        $pdo->prepare("UPDATE entraineur_documents SET section_id = ?, label = ?, url = ?, type = ? WHERE id = ?")
            ->execute([$section_id, $label, $url, $type, $id]);
        log_activite($pdo, 'modification', 'entraineur_documents', $label);
    } else {
        $ord = $pdo->prepare("SELECT COALESCE(MAX(ordre), 0) + 1 FROM entraineur_documents WHERE section_id = ?");
        $ord->execute([$section_id]);
        $ordre = (int)$ord->fetchColumn();
        $pdo->prepare("INSERT INTO entraineur_documents (section_id, label, url, type, ordre) VALUES (?, ?, ?, ?, ?)")
            ->execute([$section_id, $label, $url, $type, $ordre]);
        $id = (int)$pdo->lastInsertId();
        log_activite($pdo, 'ajout', 'entraineur_documents', $label);
    }

    $doc = $pdo->prepare("SELECT * FROM entraineur_documents WHERE id = ?");
    $doc->execute([$id]);
    ob_end_clean();
    echo json_encode(['success' => true, 'data' => $doc->fetch()]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
