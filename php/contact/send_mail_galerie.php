<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = 'dimitrigarrigues@gmail.com';
    $subject = 'Nouvelles photos partagées';

    // Création d'une limite pour séparer les parties du message
    $boundary = md5(time());

    // En-têtes de l'email
    $headers = 'From: webmaster@votresite.com' . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";

    // Début du message
    $message = '--' . $boundary . "\r\n";
    $message .= 'Content-Type: text/plain; charset="utf-8"' . "\r\n";
    $message .= 'Content-Transfer-Encoding: 7bit' . "\r\n";
    $message .= "\r\n";
    $message .= 'Des photos ont été soumises.' . "\r\n";

    // Ajout des fichiers joints
    if (!empty($_FILES['photos']['name'][0])) {
        for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
            $filePath = $_FILES['photos']['tmp_name'][$i];
            $fileName = $_FILES['photos']['name'][$i];
            $fileType = $_FILES['photos']['type'][$i];
            $fileSize = $_FILES['photos']['size'][$i];

            // Lire le fichier
            $fileContent = file_get_contents($filePath);
            $fileContent = chunk_split(base64_encode($fileContent));

            $message .= '--' . $boundary . "\r\n";
            $message .= 'Content-Type: ' . $fileType . '; name="' . $fileName . '"' . "\r\n";
            $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
            $message .= 'Content-Disposition: attachment; filename="' . $fileName . '"' . "\r\n";
            $message .= "\r\n";
            $message .= $fileContent . "\r\n";
        }
    }

    // Fin du message
    $message .= '--' . $boundary . '--';

    // Envoi de l'email
    if (mail($to, $subject, $message, $headers)) {
        echo 'Email envoyé avec succès';
    } else {
        http_response_code(500);
        echo 'Erreur lors de l\'envoi de l\'email';
    }
}
?>
