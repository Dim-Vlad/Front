<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

// Formulaire public (comme l'ancien formulaire Google) : pas de connexion requise.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}
check_csrf();

// Nettoie un nom pour l'utiliser dans un nom de fichier (sans accent, alphanumérique).
// Doit rester strictement identique à _rembSafeFilenamePart() côté JS.
function remb_safe_filename_part(string $s): string {
    $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = $translit !== false ? $translit : $s;
    return preg_replace('/[^a-zA-Z0-9]/', '', $s) ?? '';
}

$prenom = trim($_POST['prenom'] ?? '');
$nom    = trim($_POST['nom'] ?? '');
$email  = trim($_POST['email'] ?? '');

if ($prenom === '' || $nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Merci de renseigner votre prénom, nom et une adresse email valide.']); exit;
}

// Numéro de dossier généré côté client (NDF-YYYYMMDD_HHmm-XXXX) ; on le régénère
// ici s'il est absent ou mal formé plutôt que de faire confiance à une valeur libre.
$dossier = trim($_POST['dossier_numero'] ?? '');
if (!preg_match('/^NDF-\d{8}_\d{4}-[A-Z0-9]{4}$/', $dossier)) {
    $dossier = 'NDF-' . date('Ymd_Hi') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}
$nomSafe = remb_safe_filename_part($nom);

$nbLignes = (int)($_POST['nb_lignes'] ?? 0);
if ($nbLignes <= 0 || $nbLignes > 20) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Ajoutez au moins une ligne de remboursement.']); exit;
}

$pdo = get_pdo();
$equipesValides = $pdo->query('SELECT nom FROM equipes')->fetchAll(PDO::FETCH_COLUMN);
$villesConnues  = $pdo->query('SELECT nom FROM remboursement_villes')->fetchAll(PDO::FETCH_COLUMN);

// ── 1er passage : on valide toutes les lignes sans rien écrire sur le disque,
//    pour ne jamais se retrouver avec un fichier orphelin si une ligne suivante échoue.
$lignes = [];
for ($i = 0; $i < $nbLignes; $i++) {
    $type = $_POST["ligne_{$i}_type"] ?? '';
    if (!in_array($type, ['km', 'facture'], true)) continue;

    $num  = count($lignes) + 1;
    $date = trim($_POST["ligne_{$i}_date"] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => "Date invalide pour la ligne $num"]); exit;
    }
    $montant = (float)str_replace(',', '.', $_POST["ligne_{$i}_montant"] ?? '0');
    if ($montant <= 0 || $montant > 100000) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => "Montant invalide pour la ligne $num"]); exit;
    }

    $ligne = ['type' => $type, 'date' => $date, 'montant' => $montant];

    // Équipe requise sur les deux types de ligne.
    $equipe = trim($_POST["ligne_{$i}_equipe"] ?? '');
    if (!in_array($equipe, $equipesValides, true)) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => "Équipe invalide pour la ligne $num"]); exit;
    }
    $ligne['equipe'] = $equipe;

    if ($type === 'km') {
        $arrivee = trim($_POST["ligne_{$i}_arrivee"] ?? '');
        if ($arrivee === '' || mb_strlen($arrivee) > 150) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => "Lieu d'arrivée requis pour la ligne $num"]); exit;
        }
        $distance = (float)str_replace(',', '.', $_POST["ligne_{$i}_distance"] ?? '0');
        if ($distance <= 0 || $distance > 5000) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => "Distance invalide pour la ligne $num"]); exit;
        }
        $ligne['arrivee']       = $arrivee;
        $ligne['distance']      = $distance;
        // Ville absente de la liste officielle : signalée aux admins après
        // validation complète de la demande (voir 2e passage plus bas).
        $ligne['ville_inconnue'] = !in_array($arrivee, $villesConnues, true);
        $ligne['aller_retour'] = ($_POST["ligne_{$i}_aller_retour"] ?? '') === '1';
        // Détail du montant (indicatif, affiché dans l'email) : le total validé
        // ci-dessus reste la seule valeur garantie correcte côté serveur.
        $ligne['montant_km']   = max(0, (float)str_replace(',', '.', $_POST["ligne_{$i}_montant_km"] ?? '0'));
        $ligne['autres_frais'] = max(0, (float)str_replace(',', '.', $_POST["ligne_{$i}_autres_frais"] ?? '0'));
    } else { // facture
        $motif = trim($_POST["ligne_{$i}_motif"] ?? '');
        if ($motif === '' || mb_strlen($motif) > 200) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => "Motif requis pour la ligne $num"]); exit;
        }
        $ligne['motif'] = $motif;
    }

    // Justificatif PDF : toujours requis sur une ligne "facture", et sur une
    // ligne kilométrique uniquement si d'autres frais (péages, etc.) sont déclarés.
    $fileKey         = "ligne_{$i}_justificatif";
    $hasFile         = isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK;
    $fileObligatoire = $type === 'facture' || ($type === 'km' && $ligne['autres_frais'] > 0);

    if (!$hasFile && $fileObligatoire) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => "Justificatif PDF requis pour la ligne $num"]); exit;
    }
    if ($hasFile) {
        $file = $_FILES[$fileKey];
        if ($file['size'] > 10 * 1024 * 1024) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => "Le justificatif de la ligne $num ne doit pas dépasser 10 Mo"]); exit;
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf' || mime_content_type($file['tmp_name']) !== 'application/pdf') {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => "Le justificatif de la ligne $num doit être un fichier PDF"]); exit;
        }
        $ligne['fileTmp'] = $file['tmp_name'];
    }
    $ligne['hasFile'] = $hasFile;

    $lignes[] = $ligne;
}

if (empty($lignes)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Ajoutez au moins une ligne de remboursement.']); exit;
}

// ── 2e passage : tout est valide, on enregistre les justificatifs et on construit le récapitulatif.
$dir = $_SERVER['DOCUMENT_ROOT'] . '/documents/remboursements/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$lignesTexte  = [];
$montantTotal = 0.0;
$piecesJointes = []; // chemin absolu + nom de fichier pour chaque PDF, afin de les attacher à l'email

// Le récapitulatif PDF (généré côté client, même mise en page que l'ancien
// formulaire) est joint en premier s'il a bien été transmis par le navigateur.
if (isset($_FILES['recap_pdf']) && $_FILES['recap_pdf']['error'] === UPLOAD_ERR_OK) {
    $recapFile = $_FILES['recap_pdf'];
    if ($recapFile['size'] <= 10 * 1024 * 1024 && mime_content_type($recapFile['tmp_name']) === 'application/pdf') {
        $recapFilename = "{$dossier}_{$nomSafe}.pdf";
        if (move_uploaded_file($recapFile['tmp_name'], $dir . $recapFilename)) {
            $piecesJointes[] = ['path' => $dir . $recapFilename, 'name' => $recapFilename];
        }
    }
}

foreach ($lignes as $i => $ligne) {
    $num = $i + 1;
    $montantTotal += $ligne['montant'];
    $dateFr = (new DateTime($ligne['date']))->format('d/m/Y');

    // Même convention de nommage que l'ancien système (et que le PDF récapitulatif
    // généré côté client), pour que le nom affiché dans le tableau corresponde réellement
    // au fichier joint : {dossier}_{Nom}_Justificatif_{n}.pdf
    // Ligne kilométrique sans autres frais : aucun justificatif n'a été demandé/joint.
    $justificatifUrl = null;
    if ($ligne['hasFile']) {
        $filename = "{$dossier}_{$nomSafe}_Justificatif_{$num}.pdf";
        if (!move_uploaded_file($ligne['fileTmp'], $dir . $filename)) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => "Erreur d'enregistrement du justificatif de la ligne $num"]); exit;
        }
        $piecesJointes[]  = ['path' => $dir . $filename, 'name' => $filename];
        $justificatifUrl = 'https://volleyballollioulais.fr/documents/remboursements/' . $filename;
    }

    // Ville absente de la liste officielle : journalisée pour qu'un admin
    // vérifie la distance déclarée et l'ajoute éventuellement à la liste.
    if ($ligne['type'] === 'km' && $ligne['ville_inconnue']) {
        $pdo->prepare('INSERT INTO remboursement_villes_attente (nom, distance_declaree, demandeur) VALUES (?, ?, ?)')
            ->execute([$ligne['arrivee'], $ligne['distance'], "$prenom $nom"]);
    }

    if ($ligne['type'] === 'km') {
        $detailMontant = "Frais kilométriques : " . number_format($ligne['montant_km'], 2, ',', ' ') . ' €';
        if ($ligne['autres_frais'] > 0) {
            $detailMontant .= " + Autres frais (péages, etc.) : " . number_format($ligne['autres_frais'], 2, ',', ' ') . ' €';
        }
        // Ville absente de la liste officielle : rappel en tête de ligne pour que la
        // trésorerie le voie dès l'email, avant même d'ouvrir le PDF joint.
        $alerteVille = $ligne['ville_inconnue']
            ? "  ⚠️ VILLE NON RECONNUE DANS LA LISTE OFFICIELLE — vérifier la distance déclarée avant remboursement\r\n"
            : '';
        $lignesTexte[] = sprintf(
            "Ligne %d — Remboursement kilométrique\r\n%s  Équipe : %s\r\n  Date : %s\r\n  Trajet : Ollioules → %s (%s)\r\n  Distance : %s km\r\n  %s\r\n  Montant total : %s €\r\n  Justificatif : %s",
            $num, $alerteVille, $ligne['equipe'], $dateFr, $ligne['arrivee'],
            $ligne['aller_retour'] ? 'aller-retour' : 'aller simple',
            rtrim(rtrim(number_format($ligne['distance'], 2, ',', ' '), '0'), ','),
            $detailMontant,
            number_format($ligne['montant'], 2, ',', ' '),
            $justificatifUrl ?? 'Aucun (pas d\'autres frais)'
        );
    } else {
        $lignesTexte[] = sprintf(
            "Ligne %d — Remboursement sur facture\r\n  Équipe : %s\r\n  Date : %s\r\n  Motif : %s\r\n  Montant : %s €\r\n  Justificatif : %s",
            $num, $ligne['equipe'], $dateFr, $ligne['motif'], number_format($ligne['montant'], 2, ',', ' '),
            $justificatifUrl
        );
    }
}

$row = $pdo->query("SELECT valeur FROM parametres WHERE cle = 'email_remboursement_notif'")->fetch();
if (!$row) {
    $row = $pdo->query("SELECT valeur FROM parametres WHERE cle = 'club_email'")->fetch();
}
$to = $row ? $row['valeur'] : 'dimitrigarrigues@gmail.com';

$subject = "Demande de remboursement $dossier — $prenom $nom (" . number_format($montantTotal, 2, ',', ' ') . " €)";
$body = "Bonjour,\r\n\r\nNouvelle demande de remboursement de frais reçue depuis le site :\r\n\r\n"
        . "Numéro de dossier : $dossier\r\n"
        . "Nom : $prenom $nom\r\nEmail : $email\r\n\r\n"
        . implode("\r\n\r\n", $lignesTexte)
        . "\r\n\r\nMontant total de la demande : " . number_format($montantTotal, 2, ',', ' ') . " €\r\n"
        . "Les justificatifs sont joints à cet email.\r\n\r\n-- VBO";

// Envoyé à la trésorière (To) avec la personne qui a fait la demande en copie (Cc),
// pour qu'elle garde une trace avec ses justificatifs en pièces jointes.
ob_end_clean();
if (remb_envoyer_email($to, $email, $subject, $body, $piecesJointes)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => "L'envoi a échoué. Merci de réessayer ou de nous contacter directement."]);
}

// Construit et envoie un email multipart/mixed avec pièces jointes PDF, via mail()
// (pas de bibliothèque externe comme PHPMailer disponible sur cet hébergement).
function remb_envoyer_email(string $to, string $cc, string $subject, string $body, array $pieces): bool {
    $boundary = md5(uniqid('remb', true));

    $headers = "From: no-reply@volleyballollioulais.fr\r\n"
             . "Reply-To: $cc\r\n"
             . "Cc: $cc\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $message = "--$boundary\r\n"
             . "Content-Type: text/plain; charset=utf-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n\r\n"
             . $body . "\r\n";

    foreach ($pieces as $piece) {
        $contenu = @file_get_contents($piece['path']);
        if ($contenu === false) continue;
        $message .= "--$boundary\r\n"
                  . "Content-Type: application/pdf; name=\"{$piece['name']}\"\r\n"
                  . "Content-Transfer-Encoding: base64\r\n"
                  . "Content-Disposition: attachment; filename=\"{$piece['name']}\"\r\n\r\n"
                  . chunk_split(base64_encode($contenu)) . "\r\n";
    }
    $message .= "--$boundary--";

    return @mail($to, $subject, $message, $headers);
}
