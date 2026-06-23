<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['moderateur','admin'])) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Fichier manquant']); exit;
}

$allowedMimes = ['image/jpeg','image/png','image/gif','image/webp'];
$mime = mime_content_type($_FILES['photo']['tmp_name']);
if (!in_array($mime, $allowedMimes, true)) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Format non supporté (JPG, PNG, GIF, WebP)']); exit;
}

$ext = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => 'jpg',
};

$dir = $_SERVER['DOCUMENT_ROOT'] . '/images/staff/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = uniqid('staff_', true) . '.' . $ext;
if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $filename)) {
    ob_end_clean(); echo json_encode(['success'=>false,'error'=>'Erreur lors de l\'enregistrement']); exit;
}

ob_end_clean(); echo json_encode(['success'=>true,'path'=>'/images/staff/' . $filename]);
