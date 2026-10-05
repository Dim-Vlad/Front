<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Connexion requise']); exit;
}

$id = (int)($_GET['id'] ?? 0);
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT titre, contenu, created_at FROM newsletters WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Édition introuvable']); exit;
}

$data = json_decode($row['contenu'], true) ?: [];

// Le lien de désabonnement pointe vers le jeton de la personne qui consulte l'archive.
$stmtMe = $pdo->prepare('SELECT prenom, newsletter_token FROM users WHERE id = ?');
$stmtMe->execute([(int)current_user()['id']]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);
if (empty($me['newsletter_token'])) {
    $token = bin2hex(random_bytes(16));
    $pdo->prepare('UPDATE users SET newsletter_token = ? WHERE id = ?')->execute([$token, (int)current_user()['id']]);
    $me['newsletter_token'] = $token;
}
$unsub = NL_BASE_URL . '/php/newsletter/desabonner.php?t=' . urlencode($me['newsletter_token']);

$html = nl_build_html(
    $data['content'] ?? [],
    $data['events'] ?? [],
    $data['club'] ?? nl_club_info($pdo),
    $unsub,
    (string)($me['prenom'] ?? '')
);

ob_end_clean(); echo json_encode(['success' => true, 'titre' => $row['titre'], 'date' => $row['created_at'], 'html' => $html]);
