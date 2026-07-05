<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$submitters = $body['submitters'] ?? [];

if (empty($submitters)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Aucun soumetteur']);
    exit;
}

$sent = 0;

foreach ($submitters as $s) {
    $email  = filter_var($s['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $nom    = htmlspecialchars($s['nom']          ?? '', ENT_QUOTES, 'UTF-8');
    $saison = htmlspecialchars($s['saison_label'] ?? '', ENT_QUOTES, 'UTF-8');
    $approved = (int)($s['approved'] ?? 0);
    $rejected = (int)($s['rejected'] ?? 0);
    $reasons  = array_filter(array_map('trim', (array)($s['reasons'] ?? [])));

    if (!$email || ($approved + $rejected) === 0) continue;

    $lines = [];

    if ($approved > 0) {
        $lines[] = "✓ $approved photo(s) approuvée(s) et maintenant visible(s) sur le site.";
    }
    if ($rejected > 0) {
        $lines[] = "✗ $rejected photo(s) non retenue(s).";
        if (!empty($reasons)) {
            $uniqueReasons = array_unique($reasons);
            $lines[] = "  Raison(s) : " . implode(', ', $uniqueReasons) . '.';
        }
    }

    $subject = '[VBO] Résultat de la vérification de vos photos – Saison ' . $saison;
    $body    = "Bonjour" . ($nom ? " $nom" : "") . ",\r\n\r\n"
            . "Voici le résultat de la vérification de vos photos pour la saison $saison :\r\n\r\n"
            . implode("\r\n", $lines) . "\r\n\r\n"
            . ($approved > 0
                ? "Vous pouvez les retrouver sur la galerie du club :\r\nhttps://volleyballollioulais.fr/pages/galerie/galerie.html\r\n\r\nMerci pour votre contribution !\r\n\r\n"
                : "N'hésitez pas à nous soumettre d'autres photos !\r\n\r\n")
            . "-- L'équipe VBO";

    $headers = "From: no-reply@volleyballollioulais.fr\r\nContent-Type: text/plain; charset=utf-8\r\n";

    if (@mail($email, $subject, $body, $headers)) $sent++;
}

ob_end_clean(); echo json_encode(['success' => true, 'sent' => $sent]);
