<?php
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

echo json_encode([
    'logged_in'     => isset($_SESSION['user_id']),
    'username'      => $_SESSION['username'] ?? null,
    'display_short' => $display_short,
    'role'          => $_SESSION['role'] ?? null,
]);
