<?php
require_once __DIR__ . '/../../php/auth.php';

if (!is_logged_in()) {
    http_response_code(403);
    echo "<div style='text-align:center;padding:2rem;font-family:sans-serif;'><h1>⛔ Accès refusé</h1><p>Vous devez être connecté pour soumettre une demande.</p><a href='/pages/auth/connexion.php'>← Se connecter</a></div>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $nom   = strip_tags(trim($_POST['nom']   ?? ''));
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$nom || !$email) {
        die("Les champs Nom ou Email sont invalides.");
    }

    if (!isset($_FILES['fichier']) || !is_array($_FILES['fichier']['error'])) {
        die("Aucun fichier reçu ou format invalide. Vérifiez le champ <strong>fichier[]</strong>.");
    }

    $files    = $_FILES['fichier'];
    $pdfFiles = [];

    for ($i = 0; $i < count($files['error']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            die("Erreur lors de l'upload du fichier à l'indice $i. Code : " . $files['error'][$i]);
        }

        $mime_type = (new finfo(FILEINFO_MIME_TYPE))->file($files['tmp_name'][$i]);

        if ($mime_type !== 'application/pdf') {
            die("Le fichier à l'indice $i n'est pas un PDF valide. Type détecté : $mime_type");
        }

        // Assainissement du nom de fichier pour éviter l'injection d'en-têtes MIME
        $safeName = preg_replace('/[\r\n"\\\\]/', '_', $files['name'][$i]);
        $safeName = mb_substr($safeName, 0, 200);

        $pdfFiles[] = ['tmp_name' => $files['tmp_name'][$i], 'name' => $safeName];
    }

    if (empty($pdfFiles)) {
        die("Aucun fichier PDF valide à envoyer.");
    }

    $destinataire = "dimitrigarrigues@gmail.com, elodiep67@gmail.com, secretariatvbo@free.fr";
    $sujet        = "Demande de remboursement - " . $nom;

    $message  = "Message reçu depuis le formulaire de remboursement.\n\n";
    $message .= "Merci de trouver ci-joint les documents de $nom ($email).\n";
    $message .= "Nombre de fichiers joints : " . count($pdfFiles) . "\n\n";
    $message .= "------------------------------------------\n";
    $message .= "Nom : $nom\nEmail : $email\nDate : " . date('d/m/Y H:i') . "\n";
    $message .= "------------------------------------------\n\n";

    $boundary = md5(uniqid());
    $headers  = "From: webmaster-remboursement@volleyballollioulais.fr\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Return-Path: webmaster-remboursement@volleyballollioulais.fr\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=\"utf-8\"\r\n\r\n";
    $body .= $message . "\r\n";

    foreach ($pdfFiles as $file) {
        $content = chunk_split(base64_encode(file_get_contents($file['tmp_name'])));
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: application/pdf; name=\"" . $file['name'] . "\"\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . $file['name'] . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $content . "\r\n";
    }

    $body .= "--$boundary--";

    if (mail($destinataire, $sujet, $body, $headers)) {
        echo "<div style='text-align:center;padding:2rem;font-family:sans-serif;'><h1>✅ Message envoyé !</h1><p>Merci $nom, votre demande a été envoyée avec " . count($pdfFiles) . " fichier(s).</p><a href='/pages/leClub/espace-entraineur/espace-entraineur.php'>← Retour</a></div>";
    } else {
        echo "<div style='text-align:center;padding:2rem;font-family:sans-serif;'><h1>❌ Erreur d'envoi</h1><p>Une erreur est survenue. Veuillez réessayer.</p><a href='javascript:history.back()'>← Réessayer</a></div>";
    }

} else {
    echo "<div style='text-align:center;padding:2rem;font-family:sans-serif;'><h1>⚠️ Méthode non autorisée</h1><a href='/'>← Accueil</a></div>";
}
