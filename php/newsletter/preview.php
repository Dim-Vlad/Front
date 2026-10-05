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

$pdo     = get_pdo();
$content = nl_content_from_post();
$html    = nl_build_html($content, nl_upcoming_events($pdo), nl_club_info($pdo), NL_BASE_URL . '/php/newsletter/desabonner.php?t=apercu', 'Prénom');

ob_end_clean(); echo json_encode(['success' => true, 'html' => $html]);
