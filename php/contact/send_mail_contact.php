<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

require_once __DIR__ . '/../auth.php';
check_csrf();

$to = 'dimitrigarrigues@gmail.com';
try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'club_email'");
    $stmt->execute();
    $row  = $stmt->fetch();
    if ($row && filter_var($row['valeur'], FILTER_VALIDATE_EMAIL)) {
        $to = $row['valeur'];
    }
} catch (Exception $e) {}

$selected_subject = $_POST['subject-dropdown'] ?? '';
$email_subject    = 'Nouveau message de contact: ' . $selected_subject;

$rawMail  = $_POST['mail'] ?? '';
$replyTo  = filter_var($rawMail, FILTER_VALIDATE_EMAIL)
            ? str_replace(["\r", "\n"], '', $rawMail)
            : '';

$message = 'Nom: '       . ($_POST['lastname']  ?? '') . "\n"
         . 'Prénom: '    . ($_POST['firstname'] ?? '') . "\n"
         . 'Téléphone: ' . ($_POST['phone']     ?? '') . "\n\n"
         . 'E-mail: '    . $rawMail                    . "\n\n"
         . 'Objet: '     . $selected_subject           . "\n"
         . 'Message: '   . ($_POST['subject']   ?? '');

$headers = 'From: webmaster@volleyballollioulais.fr' . "\r\n"
         . 'X-Mailer: PHP/' . phpversion();
if ($replyTo !== '') {
    $headers .= "\r\nReply-To: " . $replyTo;
}

if (mail($to, $email_subject, $message, $headers)) {
    echo 'Message envoyé avec succès';
} else {
    http_response_code(500);
    echo "Erreur lors de l'envoi du message";
}
