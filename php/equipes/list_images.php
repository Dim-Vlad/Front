<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur','admin'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}
check_csrf();

$candidates = [
    'ImageEntrainement',
    'ImageMatch',
    'maillotJaune',
    'mainCercle',
];

$imgDir  = __DIR__ . '/../../images/';
$allowed = ['jpg','jpeg','png','webp','gif'];
$images  = [];

foreach ($candidates as $name) {
    foreach ($allowed as $ext) {
        if (file_exists($imgDir . $name . '.' . $ext)) {
            $images[] = '/images/' . $name . '.' . $ext;
            break;
        }
    }
}

ob_end_clean(); echo json_encode(['success'=>true, 'images'=>$images]);
