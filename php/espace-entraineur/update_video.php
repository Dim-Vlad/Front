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

$validThemes = ['echauffement', 'attaque', 'contre', 'service', 'reception', 'passe', 'jeu'];

$titre       = trim($_POST['titre'] ?? '');
$description = trim($_POST['description'] ?? '');
$theme       = in_array($_POST['theme'] ?? '', $validThemes, true) ? $_POST['theme'] : 'jeu';

if ($titre === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Titre requis']); exit;
}
if (mb_strlen($titre) > 150) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Titre trop long (150 caractères max)']); exit;
}

try {
    $pdo   = get_pdo();
    $check = $pdo->prepare('SELECT type, url, user_id FROM videos_entrainement WHERE id = ?');
    $check->execute([$id]);
    $existing = $check->fetch();
    if (!$existing) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Ressource introuvable']); exit;
    }

    // Seuls l'auteur du partage ou un admin/modérateur peuvent le modifier.
    $me   = current_user();
    $type = $existing['type']; // le type n'est pas modifiable depuis l'édition
    if ((int)$existing['user_id'] !== (int)$me['id'] && !has_any_role(['admin', 'moderateur'])) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
    }

    $url = $existing['url'];

    if ($type === 'video') {
        $url = trim($_POST['url'] ?? '');
        if ($url === '' || !preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Lien invalide : il doit commencer par http:// ou https://']); exit;
        }
    } elseif (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        // Un nouveau fichier a été envoyé : il remplace l'actuel.
        $file = $_FILES['fichier'];

        if ($type === 'image') {
            if ($file['size'] > 5 * 1024 * 1024) {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => "L'image ne doit pas dépasser 5 Mo"]); exit;
            }
            $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $mime = mime_content_type($file['tmp_name']);
            if (!isset($exts[$mime])) {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Format non autorisé (jpg, png, webp, gif)']); exit;
            }
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/images/espace-entraineur/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $nom = 'vid-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $exts[$mime];
            if (!move_uploaded_file($file['tmp_name'], $dir . $nom)) {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => "Erreur d'enregistrement de l'image"]); exit;
            }
            $newUrl = '/images/espace-entraineur/' . $nom;
        } else { // pdf
            if ($file['size'] > 10 * 1024 * 1024) {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Le PDF ne doit pas dépasser 10 Mo']); exit;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf' || mime_content_type($file['tmp_name']) !== 'application/pdf') {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Format PDF uniquement']); exit;
            }
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/documents/espace-entraineur/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $nom = 'vid-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
            if (!move_uploaded_file($file['tmp_name'], $dir . $nom)) {
                ob_end_clean(); echo json_encode(['success' => false, 'error' => "Erreur d'enregistrement du fichier"]); exit;
            }
            $newUrl = '/documents/espace-entraineur/' . $nom;
        }

        // Supprime l'ancien fichier une fois le nouveau bien enregistré.
        if (in_array($type, ['image', 'pdf'], true)
            && (str_starts_with($url, '/images/espace-entraineur/') || str_starts_with($url, '/documents/espace-entraineur/'))) {
            $oldPath = $_SERVER['DOCUMENT_ROOT'] . $url;
            if (file_exists($oldPath)) unlink($oldPath);
        }
        $url = $newUrl;
    }
    // Sinon (image/pdf sans nouveau fichier envoyé) : on garde l'URL actuelle.

    $pdo->prepare('UPDATE videos_entrainement SET theme = ?, titre = ?, description = ?, url = ? WHERE id = ?')
        ->execute([$theme, $titre, $description, $url, $id]);
    log_activite($pdo, 'modification', 'video_entrainement', "Modification de « {$titre} »");

    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
