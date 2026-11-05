<?php
/**
 * Agrégation des données de fréquentation pour le tableau de bord
 * admin (pages/admin/statistiques.php). Lecture seule, réservée aux
 * administrateurs.
 */
ob_start();
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!is_logged_in() || !has_role('admin')) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}

$pdo = get_pdo();

$periode = $_GET['periode'] ?? '30';
$jours   = match ($periode) {
    '7'   => 7,
    '90'  => 90,
    'tout' => null,
    default => 30,
};

$where  = '';
$params = [];
if ($jours !== null) {
    $where = 'WHERE created_at >= :depuis';
    $params['depuis'] = (new DateTime("-$jours days"))->format('Y-m-d 00:00:00');
}

// --- KPIs globaux ---
$stmt = $pdo->prepare("SELECT COUNT(*) AS pages_vues, COUNT(DISTINCT session_id) AS visites FROM analytics_visites $where");
$stmt->execute($params);
$kpis = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT AVG(duree) AS duree_moyenne FROM (
        SELECT TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) AS duree
        FROM analytics_visites $where
        GROUP BY session_id
    ) t
");
$stmt->execute($params);
$kpis['duree_moyenne'] = round((float)($stmt->fetch(PDO::FETCH_ASSOC)['duree_moyenne'] ?? 0));

// --- Évolution par jour (pages vues + visites uniques) ---
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS jour, COUNT(*) AS pages_vues, COUNT(DISTINCT session_id) AS visites
    FROM analytics_visites $where
    GROUP BY DATE(created_at)
    ORDER BY jour ASC
");
$stmt->execute($params);
$evolution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Navigateurs ---
$stmt = $pdo->prepare("
    SELECT navigateur, COUNT(*) AS total FROM analytics_visites $where
    GROUP BY navigateur ORDER BY total DESC
");
$stmt->execute($params);
$navigateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Plateformes ---
$stmt = $pdo->prepare("
    SELECT plateforme, COUNT(*) AS total FROM analytics_visites $where
    GROUP BY plateforme ORDER BY total DESC
");
$stmt->execute($params);
$plateformes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Pays ---
$stmt = $pdo->prepare("
    SELECT pays_code, pays_nom, COUNT(*) AS total FROM analytics_visites $where
    GROUP BY pays_code, pays_nom ORDER BY total DESC LIMIT 20
");
$stmt->execute($params);
$pays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Pages les plus vues ---
$stmt = $pdo->prepare("
    SELECT url, COUNT(*) AS total FROM analytics_visites $where
    GROUP BY url ORDER BY total DESC LIMIT 15
");
$stmt->execute($params);
$pagesTop = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Parcours les plus fréquents (page précédente -> page) ---
$whereParcours = $where === ''
    ? "WHERE page_precedente <> ''"
    : "$where AND page_precedente <> ''";
$stmt = $pdo->prepare("
    SELECT page_precedente, url, COUNT(*) AS total FROM analytics_visites $whereParcours
    GROUP BY page_precedente, url ORDER BY total DESC LIMIT 15
");
$stmt->execute($params);
$parcours = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_end_clean();
echo json_encode([
    'success'     => true,
    'kpis'        => $kpis,
    'evolution'   => $evolution,
    'navigateurs' => $navigateurs,
    'plateformes' => $plateformes,
    'pays'        => $pays,
    'pages_top'   => $pagesTop,
    'parcours'    => $parcours,
]);
