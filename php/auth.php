<?php
require_once __DIR__ . '/config.php';
date_default_timezone_set('Europe/Paris');

if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_cache_limiter('');
    session_start();
}

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_login(string $redirect = '/pages/auth/connexion.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function current_user(): array {
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'roles'    => $_SESSION['roles'] ?? [],
        'prenom'   => $_SESSION['prenom'] ?? null,
        'nom'      => $_SESSION['nom'] ?? null,
    ];
}

function has_role(string $role): bool {
    return in_array($role, $_SESSION['roles'] ?? [], true);
}

function has_any_role(array $roles): bool {
    return !empty(array_intersect($roles, $_SESSION['roles'] ?? []));
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    // Accept token from form field (FormData) or X-CSRF-Token header (JSON fetch)
    $token = $_POST['_csrf']
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        ob_end_clean();
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
        exit;
    }
}
