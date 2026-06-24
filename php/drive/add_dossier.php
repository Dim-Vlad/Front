<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}

$nom = trim($_POST['nom'] ?? '');
$url = trim($_POST['url'] ?? '');

if ($nom === '' || $url === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Nom et URL requis']); exit;
}

// Extrait l'ID du dossier Drive depuis l'URL de partage
$embedUrl = $url;
if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $url, $m)) {
    $embedUrl = 'https://drive.google.com/embeddedfolderview?id=' . $m[1] . '#list';
}

try {
    $pdo = get_pdo();
    $max = (int)$pdo->query("SELECT COALESCE(MAX(ordre),0) FROM drive_dossiers")->fetchColumn();
    $pdo->prepare("INSERT INTO drive_dossiers (nom, url, ordre) VALUES (?, ?, ?)")
        ->execute([$nom, $embedUrl, $max + 1]);
    $id = (int)$pdo->lastInsertId();
    log_activite($pdo, 'ajout', 'drive_dossier', $nom);
    ob_end_clean(); echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
