<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$validCategories = ['entraineur', 'arbitre', 'marqueur', 'joueurs', 'detection'];
$extToType       = ['pdf' => 'pdf', 'doc' => 'word', 'docx' => 'word', 'xls' => 'excel', 'xlsx' => 'excel'];

$id          = (int)($_POST['id'] ?? 0);
$label       = trim($_POST['label'] ?? '');
$description = trim($_POST['description'] ?? '');
$categorie   = in_array($_POST['categorie'] ?? '', $validCategories, true) ? $_POST['categorie'] : '';
$kind        = ($_POST['kind'] ?? '') === 'lien' ? 'lien' : 'fichier';

if ($label === '' || $categorie === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Nom et catégorie requis']); exit;
}

try {
    $pdo = get_pdo();

    $existingUrl  = null;
    $existingType = null;
    if ($id > 0) {
        $e = $pdo->prepare("SELECT url, type FROM formations WHERE id = ?");
        $e->execute([$id]);
        $existing = $e->fetch();
        if (!$existing) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Formation introuvable']); exit;
        }
        $existingUrl  = $existing['url'];
        $existingType = $existing['type'];
    }

    $url  = null;
    $type = null;

    if ($kind === 'fichier') {
        $hasFichier = isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK;
        if ($hasFichier) {
            $ext = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
            if (!isset($extToType[$ext])) {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Formats acceptés : PDF, Word, Excel']); exit;
            }
            if ($ext === 'pdf' && mime_content_type($_FILES['fichier']['tmp_name']) !== 'application/pdf') {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Format PDF uniquement']); exit;
            }
            $type  = $extToType[$ext];
            $slug  = preg_replace('/[^a-z0-9]+/', '-', strtolower($label));
            $fname = $slug . '-' . time() . '.' . $ext;
            $dir   = $_SERVER['DOCUMENT_ROOT'] . '/documents/formations/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $dir . $fname)) {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => "Erreur d'enregistrement du fichier"]); exit;
            }
            $url = '/documents/formations/' . $fname;
        } elseif ($id > 0) {
            $url  = $existingUrl;
            $type = $existingType;
        } else {
            $url  = '';
            $type = 'lien';
        }
    } else {
        $url  = trim($_POST['url'] ?? '');
        $type = 'lien';
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Lien invalide']); exit;
        }
    }

    if ($id > 0) {
        $pdo->prepare("UPDATE formations SET label = ?, description = ?, categorie = ?, type = ?, url = ? WHERE id = ?")
            ->execute([$label, $description, $categorie, $type, $url, $id]);
        log_activite($pdo, 'modification', 'formation', "Modification de « {$label} »");
    } else {
        $ord = $pdo->query("SELECT COALESCE(MAX(ordre), 0) + 1 FROM formations")->fetchColumn();
        $pdo->prepare("INSERT INTO formations (label, description, categorie, type, url, ordre) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$label, $description, $categorie, $type, $url, (int)$ord]);
        $id = (int)$pdo->lastInsertId();
        log_activite($pdo, 'ajout', 'formation', "Ajout de « {$label} »");
    }

    $row = $pdo->prepare("SELECT * FROM formations WHERE id = ?");
    $row->execute([$id]);
    ob_end_clean();
    echo json_encode(['success' => true, 'data' => $row->fetch()]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
