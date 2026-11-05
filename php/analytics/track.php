<?php
/**
 * Endpoint public de suivi de fréquentation (appelé en arrière-plan
 * par js/main.js à chaque chargement de page). Pas de CSRF ici : c'est
 * un simple compteur, sans action sensible ni lecture de données.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$sessionId = substr(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['session_id'] ?? ''), 0, 32);
$url       = substr($_POST['url'] ?? '', 0, 255);
$precedent = substr($_POST['page_precedente'] ?? '', 0, 255);

if ($sessionId === '' || $url === '') {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

// Bruit à exclure : robots d'indexation, qui ne représentent pas de
// véritables visiteurs.
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (analytics_is_bot($ua)) {
    echo json_encode(['success' => true]);
    exit;
}

[$navigateur, $plateforme] = analytics_parse_ua($ua);
$pays = geoip_lookup(analytics_client_ip());

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO analytics_visites
            (session_id, url, page_precedente, navigateur, plateforme, pays_code, pays_nom)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$sessionId, $url, $precedent, $navigateur, $plateforme, $pays['code'], $pays['nom']]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
}
