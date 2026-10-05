<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
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
if ($content['intro'] === '' && $content['une_texte'] === '' && !$content['show_events']) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'La newsletter est vide : ajoutez un texte ou des événements.']); exit;
}

set_time_limit(0);

$pdo    = get_pdo();
$club   = nl_club_info($pdo);
$events = $content['show_events'] ? nl_upcoming_events($pdo) : [];

$destinataires = $pdo->query(
    'SELECT id, username, prenom, newsletter_token FROM users WHERE actif = 1 AND newsletter = 1'
)->fetchAll(PDO::FETCH_ASSOC);

$headers = "From: no-reply@volleyballollioulais.fr\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/html; charset=UTF-8\r\n";

$envoyes = 0;
$ignores = 0;
foreach ($destinataires as $u) {
    $email = filter_var($u['username'], FILTER_VALIDATE_EMAIL);
    if (!$email) { $ignores++; continue; }

    if (empty($u['newsletter_token'])) {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare('UPDATE users SET newsletter_token = ? WHERE id = ?')->execute([$token, $u['id']]);
        $u['newsletter_token'] = $token;
    }

    $unsub = NL_BASE_URL . '/php/newsletter/desabonner.php?t=' . urlencode($u['newsletter_token']);
    $html  = nl_build_html($content, $events, $club, $unsub, (string)($u['prenom'] ?? ''));

    if (@mail($email, '[VBO] ' . $content['titre'], $html, $headers)) $envoyes++;
    else $ignores++;
}

$pdo->prepare('INSERT INTO newsletters (titre, contenu, nb_envois, created_by) VALUES (?, ?, ?, ?)')
    ->execute([
        $content['titre'],
        json_encode(['content' => $content, 'events' => $events, 'club' => $club], JSON_UNESCAPED_UNICODE),
        $envoyes,
        (int)current_user()['id'],
    ]);

log_activite($pdo, 'ajout', 'newsletter', "Newsletter « {$content['titre']} » envoyée à {$envoyes} destinataire(s)");

ob_end_clean();
echo json_encode(['success' => true, 'envoyes' => $envoyes, 'ignores' => $ignores]);
