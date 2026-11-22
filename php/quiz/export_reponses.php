<?php
/**
 * Export CSV de toutes les réponses au quiz (sauvegarde avant remise à zéro).
 * Lien GET ouvert depuis la page d'admin — pas d'appel AJAX, donc pas de
 * vérification CSRF (lecture seule, réservée aux admins/modérateurs).
 */
require_once __DIR__ . '/../auth.php';
require_login();
if (!has_any_role(['admin', 'moderateur'])) {
    http_response_code(403);
    exit('Accès refusé');
}

$pdo = get_pdo();
$rows = $pdo->query(
    "SELECT q.id AS question_id, q.question, q.choix_a, q.choix_b, q.choix_c, q.choix_d,
            q.reponse_correcte, q.points AS points_question,
            u.prenom, u.nom, u.username AS email,
            r.choix AS choix_utilisateur, r.correct, r.points_obtenus, r.created_at
     FROM quiz_reponses r
     JOIN quiz_questions q ON q.id = r.question_id
     JOIN users u ON u.id = r.user_id
     ORDER BY q.id, u.nom, u.prenom"
)->fetchAll();

$filename = 'quiz_export_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF"; // BOM pour un affichage correct des accents dans Excel
$out = fopen('php://output', 'w');
fputcsv($out, [
    'ID question', 'Question', 'Choix A', 'Choix B', 'Choix C', 'Choix D',
    'Bonne réponse', 'Points question',
    'Prénom', 'Nom', 'Email',
    'Choix utilisateur', 'Correct', 'Points obtenus', 'Date réponse',
], ';');

foreach ($rows as $r) {
    fputcsv($out, [
        $r['question_id'], $r['question'], $r['choix_a'], $r['choix_b'], $r['choix_c'], $r['choix_d'],
        strtoupper($r['reponse_correcte']), $r['points_question'],
        $r['prenom'], $r['nom'], $r['email'],
        strtoupper($r['choix_utilisateur']), $r['correct'] ? 'Oui' : 'Non', $r['points_obtenus'], $r['created_at'],
    ], ';');
}
fclose($out);
