<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ====== Récupération des champs texte ======
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$nom || !$email) {
        die("Les champs Nom ou Email sont invalides.");
    }

    // ====== Vérification des fichiers ======
    if (!isset($_FILES['fichier']) || !is_array($_FILES['fichier']['error'])) {
        die("Aucun fichier reçu ou format invalide. Vérifiez le champ <strong>fichier[]</strong>.");
    }

    $files = $_FILES['fichier'];
    $pdfFiles = []; // Stocke les fichiers valides

    for ($i = 0; $i < count($files['error']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            die("Erreur lors de l'upload du fichier à l'indice $i. Code : " . $files['error'][$i]);
        }

        // Vérification du type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $files['tmp_name'][$i]);
        finfo_close($finfo);

        if ($mime_type !== 'application/pdf') {
            die("Le fichier à l'indice $i n'est pas un PDF valide. Type détecté : $mime_type");
        }

        // Ajouter le fichier valide
        $pdfFiles[] = [
            'tmp_name' => $files['tmp_name'][$i],
            'name'     => $files['name'][$i]
        ];
    }

    if (empty($pdfFiles)) {
        die("Aucun fichier PDF valide à envoyer.");
    }

    // ====== Paramètres email ======
    $destinataire = "dimitrigarrigues@gmail.com, elodiep67@gmail.com, secretariatvbo@free.fr";
    $sujet = "Nouveau formulaire avec PDF(s) - $nom";

    $message = "Message reçu depuis le formulaire de remboursement.\n\n";
    $message .= "Merci de trouver ci-joint les documents de $nom ($email).\n";
    $message .= "Nombre de fichiers joints : " . count($pdfFiles) . "\n\n";
    $message .= "------------------------------------------\n";
    $message .= "Nom : $nom\n";
    $message .= "Email : $email\n";
    $message .= "Date : " . date('d/m/Y H:i') . "\n";
    $message .= "------------------------------------------\n\n";

    // ====== Préparation email multipart ======
    $boundary = md5(uniqid());
    $headers = "From: webmaster@volleyballollioulais.fr\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Return-Path: webmaster@volleyballollioulais.fr\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 3\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=\"utf-8\"\r\n\r\n";
    $body .= $message . "\r\n";

    // Ajout de chaque PDF comme pièce jointe
    foreach ($pdfFiles as $file) {
        $content = file_get_contents($file['tmp_name']);
        $content = chunk_split(base64_encode($content));

        $body .= "--$boundary\r\n";
        $body .= "Content-Type: application/pdf; name=\"" . $file['name'] . "\"\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . $file['name'] . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $content . "\r\n";
    }

    $body .= "--$boundary--";

    // ====== Envoi de l'email ======
    if (mail($destinataire, $sujet, $body, $headers)) {
        echo "<div style='text-align:center; padding: 2rem; font-family: sans-serif;'>
                <h1>✅ Message envoyé avec succès !</h1>
                <p>Merci $nom, votre message a été envoyé avec " . count($pdfFiles) . " fichier(s) joint(s).</p>
                <a href='/pages/leClub/espace-entraineur.html'>← Retour au formulaire</a>
            </div>";
    } else {
        echo "<div style='text-align:center; padding: 2rem; font-family: sans-serif;'>
                <h1>❌ Erreur lors de l'envoi</h1>
                <p>Une erreur est survenue lors de l'envoi du mail.</p>
                <a href='javascript:history.back()'>← Réessayer</a>
            </div>";
    }

} else {
    echo "<div style='text-align:center; padding: 2rem; font-family: sans-serif;'>
            <h1>⚠️ Méthode non autorisée</h1>
            <p>Seule la méthode POST est acceptée.</p>
            <a href='../index.html'>← Accueil</a>
        </div>";
}
?>