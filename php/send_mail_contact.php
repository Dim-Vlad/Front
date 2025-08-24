<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = 'dimitrigarrigues@gmail.com';

    // Récupérer l'objet sélectionné dans la liste déroulante
    $selected_subject = $_POST['subject-dropdown'];

    // Créer un sujet d'email plus descriptif
    $email_subject = 'Nouveau message de contact: ' . $selected_subject;

    // Composer le corps du message
    $message = 'Nom: ' . $_POST['lastname'] . "\n" .
            'Prénom: ' . $_POST['firstname'] . "\n" .
            'Téléphone: ' . $_POST['phone'] . "\n\n" .
            'E-mail: ' . $_POST['mail'] . "\n\n" .
            'Objet: ' . $selected_subject . "\n" .
            'Message: ' . $_POST['subject'];

    // Définir les en-têtes de l'email
    $headers = 'From: webmaster@volleyballollioulais.fr' . "\r\n" .
            'Reply-To: ' . $_POST['mail'] . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

    // Envoyer l'email
    if (mail($to, $email_subject, $message, $headers)) {
        echo 'Message envoyé avec succès';
    } else {
        http_response_code(500);
        echo 'Erreur lors de l\'envoi du message';
    }
}
?>
