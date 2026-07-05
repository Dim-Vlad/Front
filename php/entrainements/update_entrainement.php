<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    http_response_code(403); ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']); exit;
}
check_csrf();

$validJours    = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
$validColspans = ['111','21','12','3'];

$id      = (int)($_POST['id']      ?? 0);
$jour    = trim($_POST['jour']     ?? '');
$horaire = trim($_POST['horaire']  ?? '');
$lieu    = trim($_POST['lieu']     ?? '');
$t1      = trim($_POST['t1']       ?? '');
$t2      = trim($_POST['t2']       ?? '');
$t3      = trim($_POST['t3']       ?? '');
$colspan = trim($_POST['colspan']  ?? '111');

if ($id <= 0 || !in_array($jour, $validJours, true) || $horaire === '' || $t1 === '' || !in_array($colspan, $validColspans, true)) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Données incomplètes ou invalides.']); exit;
}

$pdo  = get_pdo();
$check = $pdo->prepare('SELECT id FROM entrainements WHERE id = ?');
$check->execute([$id]);
if (!$check->fetch()) { http_response_code(404); ob_end_clean(); echo json_encode(['error' => 'Créneau introuvable.']); exit; }

$pdo->prepare('UPDATE entrainements SET jour=?, horaire=?, lieu=?, t1=?, t2=?, t3=?, colspan=? WHERE id=?')
    ->execute([$jour, $horaire, $lieu, $t1, $t2, $t3, $colspan, $id]);

log_activite($pdo, 'modification', 'entrainement', "Modification créneau #{$id} {$jour} {$horaire}");

$row = $pdo->prepare('SELECT * FROM entrainements WHERE id = ?');
$row->execute([$id]);
ob_end_clean(); echo json_encode(['success' => true, 'data' => $row->fetch(PDO::FETCH_ASSOC)]);
