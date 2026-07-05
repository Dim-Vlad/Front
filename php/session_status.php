<?php
ob_start();
ini_set('display_errors', 0);
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

$prenom = $_SESSION['prenom'] ?? '';
$nom    = $_SESSION['nom'] ?? '';
if ($prenom !== '') {
    $initial       = $nom !== '' ? strtoupper(substr($nom, 0, 1)) . '.' : '';
    $display_short = trim($prenom . ' ' . $initial);
} else {
    $display_short = $_SESSION['username'] ?? null;
}

ob_end_clean();
echo json_encode([
    'logged_in'     => isset($_SESSION['user_id']),
    'username'      => $_SESSION['username'] ?? null,
    'display_short' => $display_short,
    'roles'         => $_SESSION['roles'] ?? [],
    'csrf_token'    => csrf_token(),
]);
