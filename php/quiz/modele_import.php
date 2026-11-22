<?php
/**
 * Modèle CSV vide (avec un exemple) à télécharger, remplir, puis réimporter
 * via import_questions.php.
 */
require_once __DIR__ . '/../auth.php';
require_login();
if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    exit('Accès refusé');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="modele_import_quiz.csv"');

echo "\xEF\xBB\xBF"; // BOM pour un affichage correct des accents dans Excel
$out = fopen('php://output', 'w');
fputcsv($out, ['question', 'choix_a', 'choix_b', 'choix_c', 'choix_d', 'reponse_correcte', 'points', 'explication'], ';');
fputcsv($out, [
    'Combien de joueurs une équipe aligne-t-elle sur le terrain au volley-ball ?',
    '5', '6', '7', '8', 'b', '1',
    'En volleyball intérieur, chaque équipe aligne 6 joueurs sur le terrain.',
], ';');
fclose($out);
