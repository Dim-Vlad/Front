<?php
ob_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Méthode invalide']); exit;
}

$prenom   = trim($_POST['prenom']   ?? '');
$nom      = trim($_POST['nom']      ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password']         ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

if ($prenom === '' || $nom === '' || $username === '' || $password === '') {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Tous les champs sont obligatoires.']); exit;
}
if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Adresse email invalide.']); exit;
}
if (strlen($password) < 8) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 8 caractères.']); exit;
}
if ($password !== $confirm) {
    ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Les mots de passe ne correspondent pas.']); exit;
}

try {
    $pdo = get_pdo();

    $exists = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $exists->execute([$username]);
    if ($exists->fetch()) {
        ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Cette adresse email est déjà utilisée.']); exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (username, password, prenom, nom, actif) VALUES (?, ?, ?, ?, 0)')
        ->execute([$username, $hash, $prenom, $nom]);
    $newId = (int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = ?')
        ->execute([$newId, 'adherent']);

    // Email à l'admin
    try {
        $row  = $pdo->query("SELECT valeur FROM parametres WHERE cle = 'admin_email'")->fetch();
        $to   = $row ? $row['valeur'] : 'dimitrigarrigues@gmail.com';
        $subj = '[VBO] Nouveau compte adhérent en attente de validation';
        $body = "Bonjour,\r\n\r\n"
              . $prenom . ' ' . $nom . ' (' . $username . ") vient de créer un compte adhérent sur le site.\r\n\r\n"
              . "Connectez-vous au panel d'administration pour valider ou refuser ce compte :\r\n"
              . "https://volleyballollioulais.fr/pages/admin/comptes-attente.php\r\n\r\n"
              . "-- VBO";
        $headers = "From: no-reply@volleyballollioulais.fr\r\n"
                 . "Content-Type: text/plain; charset=utf-8\r\n";
        @mail($to, $subj, $body, $headers);
    } catch (Exception $e) {}

    ob_end_clean();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Erreur serveur. Veuillez réessayer.']);
}
