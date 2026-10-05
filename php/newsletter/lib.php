<?php
// Outils partagés par l'envoi, l'aperçu et le désabonnement de la newsletter.

const NL_BASE_URL = 'https://volleyballollioulais.fr';

function nl_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function nl_param(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare('SELECT valeur FROM parametres WHERE cle = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row && $row['valeur'] !== '' ? $row['valeur'] : $default;
    } catch (Exception) {
        return $default;
    }
}

function nl_club_info(PDO $pdo): array {
    return [
        'adresse1' => nl_param($pdo, 'club_adresse_ligne1', '34 Allée des Bleuets'),
        'adresse2' => nl_param($pdo, 'club_adresse_ligne2', '83 190 Ollioules'),
        'email'    => nl_param($pdo, 'club_email', 'dimitrigarrigues@gmail.com'),
    ];
}

function nl_upcoming_events(PDO $pdo, int $limit = 5): array {
    try {
        $stmt = $pdo->prepare(
            'SELECT titre, date_debut, date_fin, lieu FROM evenements
             WHERE termine = 0 AND (date_debut IS NULL OR date_debut >= CURDATE())
             ORDER BY (date_debut IS NULL), date_debut, ordre, id
             LIMIT ' . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception) {
        return [];
    }
}

function nl_format_date(?string $debut, ?string $fin): string {
    if (!$debut) return 'Date à confirmer';
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $d1 = new DateTime($debut);
    $s  = intval($d1->format('j')) . ' ' . $mois[(int)$d1->format('n')] . ' ' . $d1->format('Y');
    if ($fin && $fin !== $debut) {
        $d2 = new DateTime($fin);
        $s .= ' au ' . intval($d2->format('j')) . ' ' . $mois[(int)$d2->format('n')] . ' ' . $d2->format('Y');
    }
    return $s;
}

function nl_content_from_post(): array {
    return [
        'titre'       => mb_substr(trim($_POST['titre'] ?? ''), 0, 150),
        'intro'       => trim($_POST['intro'] ?? ''),
        'une_titre'   => mb_substr(trim($_POST['une_titre'] ?? ''), 0, 150),
        'une_texte'   => trim($_POST['une_texte'] ?? ''),
        'une_image'   => trim($_POST['une_image'] ?? ''),
        'show_events' => ($_POST['show_events'] ?? '') === '1',
    ];
}

// Un saut de ligne vide sépare deux paragraphes ; deux sauts vides (ou plus)
// insèrent un séparateur visuel entre deux sections.
function nl_paragraphs(string $texte): string {
    $texte   = str_replace("\r\n", "\n", trim($texte));
    $sections = preg_split('/\n(?:[ \t]*\n){2,}/', $texte);
    $html    = '';
    foreach ($sections as $i => $section) {
        if ($i > 0) {
            $html .= '<hr style="border:none;border-top:1px solid #d5e2d5;margin:20px 0;">';
        }
        foreach (preg_split('/\n[ \t]*\n/', trim($section)) as $bloc) {
            if (trim($bloc) === '') continue;
            $html .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#2a2a2a;">'
                   . str_replace("\n", '<br>', nl_h(trim($bloc))) . '</p>';
        }
    }
    return $html;
}

// Construit le HTML complet d'une édition. Toutes les saisies sont échappées.
function nl_build_html(array $c, array $events, array $club, string $unsubUrl, string $prenom = ''): string {
    $titre  = nl_h($c['titre'] ?? 'Newsletter du VBO');
    $salut  = $prenom !== '' ? 'Bonjour ' . nl_h($prenom) . ',' : 'Bonjour,';
    $logo   = NL_BASE_URL . '/images/logo-club/Logo-VBO-blanc.png';

    $une = '';
    if (!empty($c['une_titre']) || !empty($c['une_texte']) || !empty($c['une_image'])) {
        $img = '';
        $imgUrl = trim($c['une_image'] ?? '');
        if ($imgUrl !== '' && str_starts_with($imgUrl, '/')) $imgUrl = NL_BASE_URL . $imgUrl;
        if (preg_match('#^https?://#i', $imgUrl)) {
            $img = '<img src="' . nl_h($imgUrl) . '" alt="" width="536" style="display:block;width:100%;max-width:536px;height:auto;border-radius:10px;margin:0 0 14px;">';
        }
        $une = '<tr><td style="padding:0 32px 8px;">
                  <div style="border-left:4px solid #063E0B;background:#f4f8f4;border-radius:10px;padding:18px 20px;">
                    <p style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#063E0B;">À la une</p>'
             . ($img ?: '')
             . (!empty($c['une_titre']) ? '<h2 style="margin:0 0 8px;font-size:18px;color:#063E0B;">' . nl_h($c['une_titre']) . '</h2>' : '')
             . (!empty($c['une_texte']) ? nl_paragraphs($c['une_texte']) : '')
             . '</div></td></tr>';
    }

    $evHtml = '';
    if (!empty($c['show_events']) && !empty($events)) {
        $items = '';
        foreach ($events as $ev) {
            $items .= '<tr><td style="padding:10px 0;border-bottom:1px solid #e8efe8;">
                         <p style="margin:0 0 2px;font-size:15px;font-weight:700;color:#063E0B;">' . nl_h($ev['titre']) . '</p>
                         <p style="margin:0;font-size:13px;color:#667066;">📅 ' . nl_h(nl_format_date($ev['date_debut'], $ev['date_fin'])) . ($ev['lieu'] ? ' · 📍 ' . nl_h($ev['lieu']) : '') . '</p>
                       </td></tr>';
        }
        $evHtml = '<tr><td style="padding:18px 32px 6px;">
                     <h2 style="margin:0 0 6px;font-size:16px;color:#063E0B;">Prochains événements</h2>
                     <table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $items . '</table>
                     <p style="margin:12px 0 0;"><a href="' . NL_BASE_URL . '/pages/evenements/evenements.php" style="color:#063E0B;font-weight:600;">Voir tous les événements →</a></p>
                   </td></tr>';
    }

    $adresse = nl_h($club['adresse1']) . ' · ' . nl_h($club['adresse2']);
    $mail    = nl_h($club['email']);

    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $titre . '</title></head>
<body style="margin:0;padding:0;background:#eef3ee;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3ee;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;">
      <tr><td style="background:#063E0B;padding:26px 32px;text-align:center;">
        <img src="' . $logo . '" alt="Volley Ball Ollioulais" width="90" style="display:block;margin:0 auto 12px;height:auto;">
        <p style="margin:0;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#acc2ab;">Newsletter</p>
        <h1 style="margin:6px 0 0;font-size:22px;color:#ffffff;">' . $titre . '</h1>
      </td></tr>
      <tr><td style="padding:28px 32px 8px;">
        <p style="margin:0 0 14px;font-size:15px;color:#2a2a2a;">' . $salut . '</p>
        ' . (!empty($c['intro']) ? nl_paragraphs($c['intro']) : '') . '
      </td></tr>
      ' . $une . '
      ' . $evHtml . '
      <tr><td style="padding:22px 32px 26px;background:#f7faf7;border-top:1px solid #e8efe8;">
        <p style="margin:0 0 6px;font-size:13px;color:#063E0B;font-weight:700;">Volley Ball Ollioulais</p>
        <p style="margin:0 0 4px;font-size:12px;color:#667066;">' . $adresse . '</p>
        <p style="margin:0 0 14px;font-size:12px;color:#667066;">' . $mail . '</p>
        <p style="margin:0;font-size:11px;color:#8a938a;line-height:1.5;">Vous recevez ce message car vous êtes adhérent du VBO.
          <a href="' . nl_h($unsubUrl) . '" style="color:#8a938a;">Se désabonner de la newsletter</a>.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>';
}
