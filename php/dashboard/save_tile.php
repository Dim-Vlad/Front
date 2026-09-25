<?php
ob_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../journal_log.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_any_role(['moderateur', 'admin'])) {
    http_response_code(403); ob_end_clean(); echo json_encode(['error' => 'Accès refusé.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); ob_end_clean(); echo json_encode(['error' => 'Méthode non autorisée.']); exit;
}
check_csrf();

$id          = (int)($_POST['id'] ?? 0);
$section     = $_POST['section'] ?? '';
$icone       = trim($_POST['icone'] ?? '');
$titre       = trim($_POST['titre'] ?? '');
$description = trim($_POST['description'] ?? '');
$url         = trim($_POST['url'] ?? '');

$validSections = ['jeux', 'entraineur', 'commissions'];
if (!in_array($section, $validSections, true)) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Section invalide.']); exit;
}
if ($icone === '') {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Le pictogramme est requis.']); exit;
}
if ($titre === '') {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Le titre est requis.']); exit;
}
if ($url === '' || !preg_match('#^(https?://|/)#i', $url)) {
    http_response_code(400); ob_end_clean(); echo json_encode(['error' => 'Le lien doit commencer par http://, https:// ou /']); exit;
}

$pdo = get_pdo();

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id FROM dashboard_tiles WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) { http_response_code(404); ob_end_clean(); echo json_encode(['error' => 'Tuile introuvable.']); exit; }

        $pdo->prepare('UPDATE dashboard_tiles SET section=?, icone=?, titre=?, description=?, url=? WHERE id=?')
            ->execute([$section, $icone, $titre, $description, $url, $id]);
        log_activite($pdo, 'modification', 'dashboard_tile', "Modification de la tuile « {$titre} »");
    } else {
        $stmtMax = $pdo->prepare('SELECT COALESCE(MAX(ordre),0)+1 FROM dashboard_tiles WHERE section=?');
        $stmtMax->execute([$section]);
        $ordre = (int)$stmtMax->fetchColumn();

        $pdo->prepare('INSERT INTO dashboard_tiles (section, icone, titre, description, url, ordre) VALUES (?,?,?,?,?,?)')
            ->execute([$section, $icone, $titre, $description, $url, $ordre]);
        $id = (int)$pdo->lastInsertId();
        log_activite($pdo, 'ajout', 'dashboard_tile', "Ajout de la tuile « {$titre} »");
    }
    ob_end_clean(); echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    ob_end_clean(); echo json_encode(['error' => 'Erreur serveur']);
}
