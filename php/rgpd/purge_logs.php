<?php
ob_start();
/**
 * Purge des journaux RGPD
 * - journal_connexions : suppression des entrées > 90 jours
 * - journal_activites  : suppression des entrées > 365 jours
 *
 * Utilisation HTTP (admin uniquement) : GET /php/rgpd/purge_logs.php
 * Utilisation CLI (CRON OVH)         : php purge_logs.php
 */

$isCli = php_sapi_name() === 'cli';

if ($isCli) {
    // Mode CLI : on charge directement la config sans passer par la session
    require_once __DIR__ . '/../config.php';
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Exception $e) {
        fwrite(STDERR, '[purge_logs] Connexion DB impossible : ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
} else {
    // Mode HTTP : vérification de l'authentification admin
    require_once __DIR__ . '/../auth.php';
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
        exit;
    }

    if (!has_any_role(['admin'])) {
        http_response_code(403);
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']);
        exit;
    }

    check_csrf();

    try {
        $pdo = get_pdo();
    } catch (Exception $e) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        exit;
    }
}

try {
    // Connexions : rétention 90 jours (données d'identification RGPD)
    $s1 = $pdo->prepare("DELETE FROM journal_connexions WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $s1->execute();
    $n1 = $s1->rowCount();

    // Activités admin : rétention 365 jours
    $s2 = $pdo->prepare("DELETE FROM journal_activites WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)");
    $s2->execute();
    $n2 = $s2->rowCount();

    if ($isCli) {
        echo '[purge_logs] Terminé — journal_connexions : ' . $n1 . ' ligne(s) supprimée(s)'
            . ', journal_activites : ' . $n2 . ' ligne(s) supprimée(s)' . PHP_EOL;
    } else {
        ob_end_clean(); echo json_encode([
            'success'               => true,
            'connexions_supprimees' => $n1,
            'activites_supprimees'  => $n2,
        ]);
    }
} catch (Exception $e) {
    if ($isCli) {
        fwrite(STDERR, '[purge_logs] Erreur : ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
