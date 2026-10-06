<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']); exit;
}

$content = nl_content_from_post();
if ($content['titre'] === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Le titre de la newsletter est requis.']); exit;
}

$pdo  = get_pdo();
$user = current_user();
$email = filter_var($user['username'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Votre identifiant n\'est pas une adresse email : impossible d\'envoyer le test.']); exit;
}

$stmt = $pdo->prepare('SELECT id, prenom, newsletter_token FROM users WHERE id = ?');
$stmt->execute([(int)$user['id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (empty($me['newsletter_token'])) {
    $token = bin2hex(random_bytes(16));
    $pdo->prepare('UPDATE users SET newsletter_token = ? WHERE id = ?')->execute([$token, $me['id']]);
    $me['newsletter_token'] = $token;
}

$unsub = NL_BASE_URL . '/php/newsletter/desabonner.php?t=' . urlencode($me['newsletter_token']);
$html  = nl_build_html($content, $content['show_events'] ? nl_upcoming_events($pdo) : [], nl_club_info($pdo), $unsub, (string)($me['prenom'] ?? ''));

$headers = nl_mail_headers();

if (@mail($email, nl_mail_subject($content['titre'], true), $html, $headers)) {
    ob_end_clean(); echo json_encode(['success' => true, 'email' => $email]);
} else {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'L\'envoi du test a échoué (le serveur mail n\'a pas accepté le message).']);
}
