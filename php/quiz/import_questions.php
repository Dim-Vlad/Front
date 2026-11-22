<?php
/**
 * Import en masse de questions de quiz depuis un fichier CSV préparé par
 * l'admin (cf. modele_import.php pour le format attendu). Chaque ligne
 * valide devient une nouvelle question — aucune suppression n'est faite ici,
 * l'admin gère séparément la suppression des anciennes questions.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';
header('Content-Type: application/json');

if (!is_logged_in() || !has_any_role(['admin', 'moderateur'])) {
    echo json_encode(['success' => false, 'error' => 'Accès refusé']); exit;
}
check_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu']); exit;
}

$handle = fopen($_FILES['fichier']['tmp_name'], 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'error' => 'Fichier illisible']); exit;
}

// Retire le BOM UTF-8 éventuel (ajouté par Excel à l'enregistrement) pour
// que la première cellule de l'en-tête ne soit pas corrompue.
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);

$ligne = 1; // la ligne 1 (en-têtes) est toujours ignorée
$creees = 0;
$erreurs = [];
$user = current_user();

try {
    $pdo = get_pdo();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO quiz_questions (question, choix_a, choix_b, choix_c, choix_d, reponse_correcte, explication, points, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );

    $header = fgetcsv($handle, 0, ';');
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $ligne++;
        if (count(array_filter($row, fn($c) => trim((string)$c) !== '')) === 0) continue; // ligne vide

        $question  = trim($row[0] ?? '');
        $choix_a   = trim($row[1] ?? '');
        $choix_b   = trim($row[2] ?? '');
        $choix_c   = trim($row[3] ?? '') ?: null;
        $choix_d   = trim($row[4] ?? '') ?: null;
        $reponse   = strtolower(trim($row[5] ?? ''));
        $points    = (int)($row[6] ?? 1); if ($points < 1 || $points > 5) $points = 1;
        $explication = trim($row[7] ?? '') ?: null;

        $choixDisponibles = ['a' => $choix_a, 'b' => $choix_b, 'c' => $choix_c, 'd' => $choix_d];

        if ($question === '' || $choix_a === '' || $choix_b === '') {
            $erreurs[] = "Ligne $ligne : question ou choix A/B manquant";
            continue;
        }
        if (!in_array($reponse, ['a', 'b', 'c', 'd'], true) || empty($choixDisponibles[$reponse])) {
            $erreurs[] = "Ligne $ligne : bonne réponse invalide (\"$reponse\")";
            continue;
        }

        $stmt->execute([$question, $choix_a, $choix_b, $choix_c, $choix_d, $reponse, $explication, $points, $user['id']]);
        $creees++;
    }

    $pdo->commit();
    fclose($handle);

    if ($creees > 0) {
        log_activite($pdo, 'creation', 'quiz_question', $creees . ' question(s) importée(s) depuis un fichier CSV');
    }

    echo json_encode(['success' => true, 'creees' => $creees, 'erreurs' => $erreurs]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fclose($handle);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
