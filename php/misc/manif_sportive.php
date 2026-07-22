<?php
ob_start();
require_once __DIR__ . '/../../php/auth.php';
require_login();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Méthode non autorisée</title></head><body style='text-align:center;padding:2rem;font-family:sans-serif'><h1>⚠️ Méthode non autorisée</h1><p>Seule la méthode POST est acceptée.</p><a href='/pages/leClub/espace-entraineur/espace-entraineur.php'>← Retour</a></body></html>";
    exit;
}

$nom   = strip_tags(trim($_POST['nom']   ?? ''));
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$nom || !$email) {
    ob_end_clean();
    http_response_code(400);
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Erreur</title></head><body style='text-align:center;padding:2rem;font-family:sans-serif'><h1>⚠️ Données invalides</h1><p>Les champs Nom ou Email sont invalides.</p><a href='javascript:history.back()'>← Retour</a></body></html>";
    exit;
}

if (!isset($_FILES['fichier']) || !is_array($_FILES['fichier']['error'])) {
    ob_end_clean();
    http_response_code(400);
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Erreur</title></head><body style='text-align:center;padding:2rem;font-family:sans-serif'><h1>⚠️ Aucun fichier reçu</h1><p>Vérifiez le champ <strong>fichier[]</strong>.</p><a href='javascript:history.back()'>← Retour</a></body></html>";
    exit;
}

$files    = $_FILES['fichier'];
$pdfFiles = [];

for ($i = 0; $i < count($files['error']); $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        ob_end_clean();
        http_response_code(400);
        echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Erreur upload</title></head><body style='text-align:center;padding:2rem;font-family:sans-serif'><h1>⚠️ Erreur upload</h1><p>Erreur fichier #$i (code : {$files['error'][$i]}).</p><a href='javascript:history.back()'>← Retour</a></body></html>";
        exit;
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
    finfo_close($finfo);

    if ($mimeType !== 'application/pdf') {
        ob_end_clean();
        http_response_code(400);
        echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Erreur</title></head><body style='text-align:center;padding:2rem;font-family:sans-serif'><h1>⚠️ Fichier invalide</h1><p>Le fichier #$i n'est pas un PDF valide.</p><a href='javascript:history.back()'>← Retour</a></body></html>";
        exit;
    }

    // Strip CRLF to prevent MIME header injection
    $safeName = str_replace(["\r", "\n"], '', $files['name'][$i]);

    $pdfFiles[] = [
        'tmp_name' => $files['tmp_name'][$i],
        'name'     => $safeName
    ];
}

if (empty($pdfFiles)) {
    ob_end_clean();
    http_response_code(400);
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Erreur</title></head><body style='text-align:center;padding:2rem;font-family:sans-serif'><h1>⚠️ Aucun PDF valide</h1><a href='javascript:history.back()'>← Retour</a></body></html>";
    exit;
}

$destinataire = "dimitrigarrigues@gmail.com, elodiep67@gmail.com, secretariatvbo@free.fr";
$sujet        = "Franchise de manifestation sportive - " . str_replace(["\r", "\n"], '', $nom);

$message  = "Message reçu depuis le formulaire de franchise de manifestation sportive.\n\n";
$message .= "Merci de trouver ci-joint les documents de $nom ($email).\n";
$message .= "Nombre de fichiers joints : " . count($pdfFiles) . "\n\n";
$message .= "------------------------------------------\n";
$message .= "Nom : $nom\n";
$message .= "Email : $email\n";
$message .= "Date : " . date('d/m/Y H:i') . "\n";
$message .= "------------------------------------------\n\n";

$boundary = md5(uniqid());
$headers  = "From: webmaster-manifestation@volleyballollioulais.fr\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Return-Path: webmaster-manifestation@volleyballollioulais.fr\r\n";
$headers .= "X-Mailer: PHP\r\n";
$headers .= "X-Priority: 3\r\n";
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

ob_end_clean();
if (mail($destinataire, $sujet, $body, $headers)) {
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Message envoyé</title></head><body style='text-align:center;padding:2rem;font-family:sans-serif'><h1>✅ Message envoyé !</h1><p>Merci $nom, votre message a été envoyé avec " . count($pdfFiles) . " fichier(s) joint(s).</p><a href='/pages/leClub/espace-entraineur/espace-entraineur.php'>← Retour à l'espace ressources</a></body></html>";
} else {
    http_response_code(500);
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Erreur envoi</title></head><body style='text-align:center;padding:2rem;font-family:sans-serif'><h1>❌ Erreur lors de l'envoi</h1><p>Une erreur est survenue lors de l'envoi du mail.</p><a href='javascript:history.back()'>← Réessayer</a></body></html>";
}
