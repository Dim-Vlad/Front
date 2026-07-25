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

function parse_user_agent(string $ua): string {
    if ($ua === '') return 'Inconnu';
    if (str_contains($ua, 'Edg/'))                                          $browser = 'Edge';
    elseif (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera/'))      $browser = 'Opera';
    elseif (str_contains($ua, 'YaBrowser'))                                 $browser = 'Yandex';
    elseif (str_contains($ua, 'Chrome/') && !str_contains($ua, 'Chromium'))$browser = 'Chrome';
    elseif (str_contains($ua, 'Chromium/'))                                 $browser = 'Chromium';
    elseif (str_contains($ua, 'Firefox/'))                                  $browser = 'Firefox';
    elseif (str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome/')) $browser = 'Safari';
    else                                                                     $browser = 'Navigateur';

    if (str_contains($ua, 'Android'))                                       $os = 'Android';
    elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad'))      $os = 'iOS';
    elseif (str_contains($ua, 'Windows'))                                   $os = 'Windows';
    elseif (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')) $os = 'macOS';
    elseif (str_contains($ua, 'Linux'))                                     $os = 'Linux';
    else                                                                     $os = '';

    return $os ? "$browser · $os" : $browser;
}

function check_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    // 1. FormData → $_POST['_csrf']
    // 2. JSON body → champ _csrf dans le payload
    // 3. Fallback header X-CSRF-Token (peut être filtré par certains hébergeurs)
    $token = $_POST['_csrf'] ?? '';
    if ($token === '') {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $json  = json_decode($raw, true);
            $token = $json['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        } else {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
    }
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        ob_end_clean();
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
        exit;
    }
}
