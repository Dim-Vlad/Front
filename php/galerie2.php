<?php
$to_email = "dimitrigarrigues@gmail.com";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = htmlspecialchars($_POST['name']);
    $email    = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $category = htmlspecialchars($_POST['category']);
    $caption  = htmlspecialchars($_POST['caption']);

    // Vérifier qu'au moins un fichier a été envoyé
    if (!isset($_FILES['photos']) || $_FILES['photos']['error'][0] !== UPLOAD_ERR_OK) {
        die("Veuillez sélectionner au moins une image.");
    }

    // Récupérer tous les fichiers uploadés
    $file_count = count($_FILES['photos']['name']);
    $attachments = [];

    for ($i = 0; $i < $file_count; $i++) {
        $tmp_name = $_FILES['photos']['tmp_name'][$i];
        $name_file = $_FILES['photos']['name'][$i];
        $size = $_FILES['photos']['size'][$i];
        $type = $_FILES['photos']['type'][$i];

        if ($size > 0) {
            $handle = fopen($tmp_name, "rb");
            $content = fread($handle, $size);
            fclose($handle);

            $attachments[] = [
                'name' => $name_file,
                'type' => $type,
                'content' => chunk_split(base64_encode($content))
            ];
        }
    }

    // Si aucun fichier valide n’a été téléchargé
    if (empty($attachments)) {
        die("Aucun fichier valide n'a été sélectionné.");
    }

    // FRONTIÈRE MULTIPART POUR LE MAIL
    $boundary = md5(uniqid(time()));
    $boundary_alt = md5(uniqid(time()));

    // En-têtes de l'email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: Formulaire Galerie <no-reply@volleyballollioulais.fr>\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    // Corps du message
    $message = "--$boundary\r\n";
    $message .= "Content-Type: multipart/alternative; boundary=\"$boundary_alt\"\r\n\r\n";

    $message .= "--$boundary_alt\r\n";
    $message .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
    $message .= "
    <html>
    <head><title>Nouvelle soumission</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
        <h2>Quelqu'un a envoyé des photos via le formulaire</h2>
        <p><strong>Nom :</strong> $name</p>
        <p><strong>Email :</strong> $email</p>
        <p><strong>Catégorie :</strong> $category</p>
        <p><strong>Légende :</strong> " . (!empty($caption) ? $caption : "Aucune légende fournie") . "</p>
        <p><strong>Nombre de photos envoyées :</strong> " . count($attachments) . "</p>
    </body>
    </html>
    \r\n\r\n";

    $message .= "--$boundary_alt--\r\n";

    // Ajout des pièces jointes
    foreach ($attachments as $att) {
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: {$att['type']}; name=\"{$att['name']}\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment\r\n\r\n";
        $message .= $att['content'] . "\r\n";
    }

    $message .= "--$boundary--";

    // Sujet de l'email
    $subject = "Nouvelles photos reçues - $category (" . count($attachments) . ")";

    // Envoi de l'email
    if (mail($to_email, $subject, $message, $headers)) {
        echo "<p>Merci $name ! Vos photos ont été envoyées.</p>";
    } else {
        echo "<p>Erreur lors de l'envoi du message. Veuillez réessayer plus tard.</p>";
    }

} else {
    header("Location: index.html");
    exit;
}
?>