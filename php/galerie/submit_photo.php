<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

try {
    $pdo = get_pdo();

    $saison = $pdo->query("SELECT * FROM saisons_galerie WHERE est_active = 1 LIMIT 1")->fetch();
    if (!$saison) {
        echo json_encode(['success' => false, 'error' => 'Aucune saison active en ce moment.']);
        exit;
    }

    $nom      = trim($_POST['name'] ?? '');
    $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $categorie = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');
    $legende  = htmlspecialchars(trim($_POST['caption'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (!$nom || !$email) {
        echo json_encode(['success' => false, 'error' => 'Prénom/Nom et email sont requis.']);
        exit;
    }

    $files = $_FILES['photos'] ?? null;
    if (!$files || empty($files['name'][0])) {
        echo json_encode(['success' => false, 'error' => 'Aucune photo sélectionnée.']);
        exit;
    }

    $maxFiles    = 10;
    $maxSize     = 15 * 1024 * 1024; // 15 Mo
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $uploadDir   = __DIR__ . '/../../photos/galerie/attente/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $count   = min(count($files['name']), $maxFiles);
    $saved   = 0;
    $errors  = [];

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $files['name'][$i] . ' : erreur upload';
            continue;
        }
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
            $errors[] = $files['name'][$i] . ' : fichier non reconnu comme image';
            continue;
        }

        $filename = $saison['slug'] . '-' . date('YmdHis') . '-' . $i . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $pdo->prepare(
                "INSERT INTO photos_galerie (saison_id, filepath, alt_text, legende, statut, soumise_par_nom, soumise_par_email, categorie)
                 VALUES (?, ?, ?, ?, 'en_attente', ?, ?, ?)"
            )->execute([
                $saison['id'],
                'photos/galerie/attente/' . $filename,
                $categorie,
                $legende ?: null,
                $nom,
                $email,
                $categorie,
            ]);
            $saved++;
        }
    }

    if ($saved === 0) {
        echo json_encode(['success' => false, 'error' => 'Aucune photo valide envoyée.', 'details' => $errors]);
        exit;
    }

    _notifier_admins($pdo, $nom, $email, $categorie, $saved, $saison['label']);

    echo json_encode(['success' => true, 'count' => $saved, 'errors' => $errors]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur.']);
}

function _notifier_admins(PDO $pdo, string $nom, string $email, string $categorie, int $count, string $saison): void {
    try {
        $row = $pdo->query("SELECT valeur FROM parametres WHERE cle = 'email_galerie_notif' LIMIT 1")->fetch();
        $to  = $row ? $row['valeur'] : 'dimitrigarrigues@gmail.com';

        $subject = "[$count photo(s) en attente] Galerie VBO – Saison $saison";
        $body    = "Bonjour,\r\n\r\n"
                 . "$nom ($email) a soumis $count photo(s) pour la catégorie $categorie, saison $saison.\r\n\r\n"
                 . "Connectez-vous au site pour les modérer :\r\n"
                 . "https://volleyballollioulais.fr/pages/galerie/galerie.html\r\n\r\n"
                 . "-- VBO";

        $headers = "From: no-reply@volleyballollioulais.fr\r\n"
                 . "Reply-To: $email\r\n"
                 . "Content-Type: text/plain; charset=utf-8\r\n";

        @mail($to, $subject, $body, $headers);
    } catch (Exception $e) {
        // Ne pas bloquer si l'email échoue
    }
}
