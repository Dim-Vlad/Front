<?php
require_once __DIR__ . '/auth.php';
$params = session_get_cookie_params();
session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
header('Location: /');
exit;
