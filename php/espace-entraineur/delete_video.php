<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur', 'bureau', 'entraineur', 'arbitre'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}
check_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'ID invalide']); exit;
}

try {
    $pdo   = get_pdo();
    $check = $pdo->prepare('SELECT titre, type, url, user_id FROM videos_entrainement WHERE id = ?');
    $check->execute([$id]);
    $video = $check->fetch();
    if (!$video) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Ressource introuvable']); exit;
    }

    // Seuls l'auteur du partage ou un admin/modérateur peuvent le supprimer.
    $me = current_user();
    if ((int)$video['user_id'] !== (int)$me['id'] && !has_any_role(['admin', 'moderateur'])) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
    }

    if (in_array($video['type'], ['image', 'pdf'], true)
        && (str_starts_with($video['url'], '/images/espace-entraineur/') || str_starts_with($video['url'], '/documents/espace-entraineur/'))) {
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $video['url'];
        if (file_exists($filePath)) unlink($filePath);
    }

    $pdo->prepare('DELETE FROM videos_entrainement WHERE id = ?')->execute([$id]);
    log_activite($pdo, 'suppression', 'video_entrainement', "Suppression de « {$video['titre']} »");

    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
