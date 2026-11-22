<?php
/**
 * Export CSV de tous les pronostics (sauvegarde avant remise à zéro).
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
    "SELECT m.id AS match_id, m.adversaire, m.date_match, m.domicile,
            m.resultat_victoire, m.resultat_sets,
            u.prenom, u.nom, u.username AS email,
            v.choix_victoire, v.choix_sets, v.created_at,
            (CASE
                WHEN m.resultat_sets IS NOT NULL AND v.choix_sets = m.resultat_sets THEN 3
                WHEN m.resultat_victoire IS NOT NULL AND v.choix_victoire = m.resultat_victoire THEN 1
                ELSE 0
             END) AS points_obtenus
     FROM pronostics_votes v
     JOIN pronostics_matchs m ON m.id = v.match_id
     JOIN users u ON u.id = v.user_id
     ORDER BY m.date_match, u.nom, u.prenom"
)->fetchAll();

$filename = 'pronostics_export_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF"; // BOM pour un affichage correct des accents dans Excel
$out = fopen('php://output', 'w');
fputcsv($out, [
    'ID match', 'Adversaire', 'Date match', 'Domicile',
    'Résultat victoire VBO', 'Résultat sets',
    'Prénom', 'Nom', 'Email',
    'Choix victoire VBO', 'Choix sets', 'Date pronostic', 'Points obtenus',
], ';');

foreach ($rows as $r) {
    fputcsv($out, [
        $r['match_id'], $r['adversaire'], $r['date_match'], $r['domicile'] ? 'Oui' : 'Non',
        $r['resultat_victoire'] === null ? '' : ($r['resultat_victoire'] ? 'Oui' : 'Non'),
        $r['resultat_sets'],
        $r['prenom'], $r['nom'], $r['email'],
        $r['choix_victoire'] ? 'Oui' : 'Non', $r['choix_sets'], $r['created_at'], $r['points_obtenus'],
    ], ';');
}
fclose($out);
