<?php
function log_activite(PDO $pdo, string $action, string $entite, string $details): void {
    $user = current_user();
    if (!$user) return;
    try {
        $pdo->prepare(
            'INSERT INTO journal_activites (user_id, username, action, entite, details) VALUES (?, ?, ?, ?, ?)'
        )->execute([(int)$user['id'], $user['username'], $action, $entite, $details]);
    } catch (Exception $e) {
        // Ne pas bloquer l'action principale si le log échoue
    }
}
