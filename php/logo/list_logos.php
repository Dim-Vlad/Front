<?php
ob_start();
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}

$dir     = realpath(__DIR__ . '/../../images/logo-club');
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$logos   = [];

if ($dir && is_dir($dir)) {
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) continue;
        $logos[] = 'images/logo-club/' . $file;
    }
    sort($logos);
}

ob_end_clean();
echo json_encode(['success' => true, 'logos' => $logos]);
