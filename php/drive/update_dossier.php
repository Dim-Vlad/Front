<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
check_csrf();

$id  = (int)($_POST['id']  ?? 0);
$nom = trim($_POST['nom']  ?? '');
$url = trim($_POST['url']  ?? '');

if ($id <= 0 || $nom === '' || $url === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Paramètres invalides']); exit;
}

// Convertit l'URL de partage Drive en URL d'intégration iframe
$embedUrl = $url;
if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $url, $m)) {
    $embedUrl = 'https://drive.google.com/embeddedfolderview?id=' . $m[1] . '#list';
}

try {
    $pdo = get_pdo();
    $check = $pdo->prepare("SELECT id FROM drive_dossiers WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Dossier introuvable']); exit;
    }
    $pdo->prepare("UPDATE drive_dossiers SET nom = ?, url = ? WHERE id = ?")
        ->execute([$nom, $embedUrl, $id]);
    log_activite($pdo, 'modification', 'drive_dossier', $nom);
    ob_end_clean(); echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
