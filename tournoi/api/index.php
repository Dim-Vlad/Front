<?php
// =====================================================================
//  API Tournoi VBO (OVH / PHP / MySQL)
//  Point d'entrée unique. Appelée par l'app via :  api/?action=xxx.yyy
//
//  Actions :
//    auth.login            POST {email,password}         -> {ok,user}
//    auth.logout           POST                          -> {ok}
//    auth.me               GET                           -> {user|null}
//    tournaments.list      GET                           -> [rows]
//    tournaments.save      POST {id?,row}                -> {id}
//    tournaments.setArchived POST {id,archived}          -> {ok}
//    tournaments.setFinished POST {id,finished,finished_at?} -> {ok}
//    tournaments.setStatus POST {id,status,closed_at?}   -> {ok}
//    tournaments.remove    POST {id}                     -> {ok}
//    tournaments.saveStructure POST {tid,s}              -> {counts} (remplace TOUT)
//    tournaments.addPhase     POST {tid,s}              -> {counts} (ajoute une phase, sans toucher au reste)
//    tournaments.loadStructure GET  ?tid=                -> {pools,schedule}|null
//    matches.setScore      POST {id,sets}                -> {ok} (admin/référent)
//    matches.submitCaptainScore POST {matchId,teamName,sets} (public) -> {status:pending|validated,...}
//    matches.startMatch    POST {matchId,teamName} (public) -> {ok} (équipe/arbitre du match)
//    matches.adminStartMatch POST {matchId}               -> {ok} (admin/référent)
//    matches.conflicts     GET  ?tid=                    -> [rows] (admin/référent, type:conflict|waiting)
//    matches.resolveConflict POST {matchId,pick|sets}    -> {ok} (admin/référent)
//    tournaments.publicByCode  GET  ?code=&team= (public) -> {name,pools,schedule,myEntries}|null
//    teams.verifyPhone     POST {code,team,phone} (public) -> {ok,skip?} (jamais le numéro en clair)
//    teams.directory       GET  ?tid=                    -> {teams:[{name,phone,pool}]} (admin/référent)
//    referents.list        GET                           -> [rows]
//    referents.create      POST {name,email,password}    -> {ok,id,email,name}
//    organizers.list       GET                           -> [rows]
//    organizers.create     POST {name,email,password}    -> {ok,id,email,name}
// =====================================================================

require __DIR__ . '/_bootstrap.php';

// Relit la structure persistée d'un tournoi (gymnases/équipes/poules/matchs)
// et la met en forme pour l'app ({ pools, schedule }), ou null si rien n'est
// persisté. Partagée par tournaments.loadStructure (organisateur/référent,
// authentifiés) et tournaments.publicByCode (capitaines, via le QR code).
function load_structure(string $tid): ?array {
  $pdo = db();
  $fetchAll = function (string $tbl) use ($pdo, $tid) {
    $st = $pdo->prepare("SELECT * FROM $tbl WHERE tournament_id = ?");
    $st->execute([$tid]);
    return $st->fetchAll();
  };
  ensure_match_started_column($pdo);
  $gyms = $fetchAll('vbo_gyms'); $teams = $fetchAll('vbo_teams');
  $pools = $fetchAll('vbo_pools'); $matches = $fetchAll('vbo_matches');
  if (!$matches) return null;
  $poolIds = array_map(fn($p) => $p['id'], $pools);
  $pteams = [];
  if ($poolIds) {
    $ph = implode(',', array_fill(0, count($poolIds), '?'));
    $st = $pdo->prepare("SELECT * FROM vbo_pool_teams WHERE pool_id IN ($ph)");
    $st->execute($poolIds);
    $pteams = $st->fetchAll();
  }
  $W2ID = [4 => 'nat', 3 => 'reg', 2 => 'dep', 1 => 'jeu'];
  $gymById = []; foreach ($gyms as $g) $gymById[$g['id']] = $g['name'];
  $teamById = []; foreach ($teams as $t) $teamById[$t['id']] = ['name' => $t['name'], 'level' => $W2ID[(int)$t['level']] ?? 'dep'];
  $poolById = []; foreach ($pools as $p) $poolById[$p['id']] = $p;

  usort($pools, fn($a, $b) => strcmp((string)$a['letter'], (string)$b['letter']));
  $poolsOut = array_map(function ($p) use ($pteams, $gymById, $teamById) {
    $members = array_values(array_filter($pteams, fn($x) => $x['pool_id'] === $p['id']));
    usort($members, fn($a, $b) => ((int)($a['seed'] ?? 0)) <=> ((int)($b['seed'] ?? 0)));
    $teams = [];
    foreach ($members as $m) {
      if (isset($teamById[$m['team_id']])) {
        $teams[] = ['t' => $teamById[$m['team_id']]['name'], 'level' => $teamById[$m['team_id']]['level']];
      }
    }
    return ['letter' => $p['letter'], 'phase' => $p['phase'] ?? 'brassage', 'gym' => $gymById[$p['gym_id']] ?? '',
            'court' => $p['court'] !== null ? (int)$p['court'] : null, 'teams' => $teams];
  }, $pools);

  // Un match n'est « en cours » que si l'arbitre (ou l'un des deux joueurs)
  // l'a explicitement démarré (started_at, via matches.startMatch), ou si un
  // capitaine a déjà saisi un score pour ce match (status 'pending' : il n'y
  // a alors plus de doute, le match est bien en train de se jouer).
  $livePhase = function (array $m): string {
    if ($m['status'] === 'valid') return 'done';
    if ($m['status'] === 'pending' || !empty($m['started_at'])) return 'live';
    return 'upcoming';
  };
  usort($matches, function ($a, $b) {
    $r = ((int)$a['round']) <=> ((int)$b['round']);
    return $r !== 0 ? $r : strcmp((string)$a['scheduled_time'], (string)$b['scheduled_time']);
  });
  $schedOut = array_map(function ($m) use ($gymById, $teamById, $poolById, $livePhase) {
    return [
      'id' => $m['id'],
      'phase' => $m['phase'] ?? 'brassage',
      'r' => $m['round'] !== null ? (int)$m['round'] : null,
      'time' => $m['scheduled_time'],
      'gym' => $gymById[$m['gym_id']] ?? '',
      'court' => $m['court'] !== null ? (int)$m['court'] : null,
      'pool' => isset($poolById[$m['pool_id']]) ? $poolById[$m['pool_id']]['letter'] : '',
      'a' => isset($teamById[$m['team_a']]) ? $teamById[$m['team_a']]['name'] : '',
      'b' => isset($teamById[$m['team_b']]) ? $teamById[$m['team_b']]['name'] : '',
      'ref' => isset($teamById[$m['referee_team']]) ? $teamById[$m['referee_team']]['name'] : '',
      'sa' => $m['score_a'] !== null ? (int)$m['score_a'] : null,
      'sb' => $m['score_b'] !== null ? (int)$m['score_b'] : null,
      'sets' => $m['sets'] !== null ? json_decode($m['sets']) : null,
      'status' => $livePhase($m),
    ];
  }, $matches);

  return ['pools' => $poolsOut, 'schedule' => $schedOut];
}

// Table de la double-saisie croisée des capitaines (une ligne par équipe par
// match, le temps que les deux aient saisi). Pas de système de migration dans
// ce projet : on la crée au premier besoin, aussi bien en local qu'en prod.
function ensure_score_entries_table(PDO $pdo): void {
  static $done = false;
  if ($done) return;
  $pdo->exec("CREATE TABLE IF NOT EXISTS vbo_score_entries (
    id CHAR(36) NOT NULL PRIMARY KEY,
    match_id CHAR(36) NOT NULL,
    team_id CHAR(36) NOT NULL,
    sets JSON NOT NULL,
    score_a INT NOT NULL,
    score_b INT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_match_team (match_id, team_id),
    KEY idx_match (match_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $done = true;
}

// Ajoute (une fois) la colonne qui retient l'instant où un match a été
// démarré (matches.startMatch) — pas de système de migration dans ce
// projet, donc on l'ajoute à la volée si elle n'existe pas déjà, aussi bien
// en local qu'en prod. On évite une ENUM pour ne pas risquer de casser la
// colonne `status` existante : un simple NULL = « pas démarré ».
function ensure_match_started_column(PDO $pdo): void {
  static $done = false;
  if ($done) return;
  $st = $pdo->query("SELECT COUNT(*) FROM information_schema.columns
                      WHERE table_schema = DATABASE() AND table_name = 'vbo_matches'
                        AND column_name = 'started_at'");
  if ((int)$st->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE vbo_matches ADD COLUMN started_at DATETIME NULL");
  }
  $done = true;
}

// Numéro de téléphone du capitaine : ajouté après coup, même mécanisme que
// started_at ci-dessus. Jamais exposé par load_structure() (public via
// tournaments.publicByCode) : seulement lu par teams.verifyPhone (qui ne
// renvoie qu'un booléen) et teams.directory (admin/référent authentifiés).
function ensure_team_phone_column(PDO $pdo): void {
  static $done = false;
  if ($done) return;
  $st = $pdo->query("SELECT COUNT(*) FROM information_schema.columns
                      WHERE table_schema = DATABASE() AND table_name = 'vbo_teams'
                        AND column_name = 'phone'");
  if ((int)$st->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE vbo_teams ADD COLUMN phone VARCHAR(30) NULL");
  }
  $done = true;
}

// Marqueur « Terminé » posé par l'organisateur une fois les phases finales
// jouées. Volontairement distinct de `status` (qui reste 'live') : passer le
// statut à 'closed' coupe l'accès public (tournaments.publicByCode exige
// status='live'), ce qui priverait les capitaines de la vue du tableau final
// une fois le tournoi terminé — `finished` est purement additif, affiché sur
// la carte « Mes tournois », sans retirer aucun accès existant.
function ensure_tournament_finished_column(PDO $pdo): void {
  static $done = false;
  if ($done) return;
  $st = $pdo->query("SELECT COUNT(*) FROM information_schema.columns
                      WHERE table_schema = DATABASE() AND table_name = 'vbo_tournaments'
                        AND column_name = 'finished'");
  if ((int)$st->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE vbo_tournaments ADD COLUMN finished TINYINT(1) NOT NULL DEFAULT 0");
    $pdo->exec("ALTER TABLE vbo_tournaments ADD COLUMN finished_at DATETIME NULL");
  }
  $done = true;
}

// Réduit un numéro à ses seuls chiffres, en ramenant +33/0033 vers le 0
// français, pour comparer deux saisies (espaces, points, indicatif...)
// sans jamais avoir à stocker/comparer le texte brut.
function normalize_phone(string $v): string {
  $digits = preg_replace('/\D+/', '', $v) ?? '';
  if (strpos($digits, '0033') === 0) $digits = '0' . substr($digits, 4);
  if (strlen($digits) === 11 && strpos($digits, '33') === 0) $digits = '0' . substr($digits, 2);
  return $digits;
}

// Les scores déjà saisis par UNE équipe (capitaine) pour un tournoi, afin
// que sa saisie survive à une perte de connexion / un rechargement de page
// (vbo_score_entries est déjà persistée en base ; il ne manquait que ce
// moyen de la relire côté capitaine, qui n'a pas de compte/session).
function my_entries_for_team(PDO $pdo, string $tid, string $teamName): array {
  ensure_score_entries_table($pdo);
  $teamName = trim($teamName);
  if ($teamName === '') return [];
  $st = $pdo->prepare("SELECT se.match_id, se.team_id, se.sets, m.team_a, m.team_b,
                               ta.name AS name_a, tb.name AS name_b
                        FROM vbo_score_entries se
                        JOIN vbo_matches m ON m.id = se.match_id
                        LEFT JOIN vbo_teams ta ON ta.id = m.team_a
                        LEFT JOIN vbo_teams tb ON tb.id = m.team_b
                        WHERE m.tournament_id = ?");
  $st->execute([$tid]);
  $norm = fn($s) => mb_strtolower(trim((string)$s));
  $out = [];
  foreach ($st->fetchAll() as $r) {
    $isA = $r['team_id'] === $r['team_a'];
    $mine = $isA ? ($r['name_a'] ?? '') : ($r['name_b'] ?? '');
    if ($norm($mine) !== $norm($teamName)) continue;
    // Toujours stocké dans le sens team_a/team_b : on remet dans le sens de
    // CE capitaine (a en premier) pour préremplir son formulaire.
    $sets = json_decode($r['sets'], true);
    if (!$isA) $sets = array_map(fn($s) => [$s[1], $s[0]], $sets);
    $out[$r['match_id']] = ['sa' => $sets[0][0], 'sb' => $sets[0][1], 'sets' => $sets];
  }
  return $out;
}

// Valide et normalise un tableau de sets envoyé par le client (même règles
// pour la saisie admin, la saisie capitaine et la résolution de conflit).
function clean_sets($sets): array {
  if (!is_array($sets) || !count($sets)) fail(400, "Score manquant.");
  $clean = [];
  foreach ($sets as $s) {
    if (!is_array($s) || count($s) !== 2) fail(400, "Score de set invalide.");
    $a = $s[0]; $b = $s[1];
    if (!is_numeric($a) || !is_numeric($b)) fail(400, "Score de set invalide.");
    $a = (int)$a; $b = (int)$b;
    if ($a < 0 || $b < 0) fail(400, "Un score ne peut pas être négatif.");
    if ($a === $b) fail(400, "Un set ne peut pas finir à égalité.");
    $clean[] = [$a, $b];
  }
  return $clean;
}

$action = $_GET['action'] ?? '';

switch ($action) {

  // ============================= AUTH ================================
  case 'auth.login': {
    $email = strtolower(trim((string)field('email', '')));
    $pass  = (string)field('password', '');
    if ($email === '' || $pass === '') fail(400, "E-mail et mot de passe requis.");
    $st = db()->prepare("SELECT id, full_name, email, role, status, password_hash FROM vbo_users WHERE email = ?");
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u || !password_verify($pass, $u['password_hash'])) fail(401, "Identifiants incorrects.");
    if (($u['status'] ?? '') !== 'active') fail(403, "Compte suspendu — contactez l'organisation.");
    set_session_user($u);
    ok(['ok' => true, 'user' => [
      'id' => $u['id'], 'name' => $u['full_name'], 'email' => $u['email'], 'role' => $u['role'],
    ]]);
    break;
  }

  case 'auth.logout': {
    clear_session_user();
    ok();
    break;
  }

  case 'auth.me': {
    ok(['user' => current_user()]);
    break;
  }

  case 'auth.changePassword': {
    $u = require_login();
    $pass = (string)field('password', '');
    if (strlen($pass) < 6) fail(400, "Le mot de passe doit faire au moins 6 caractères.");
    db()->prepare("UPDATE vbo_users SET password_hash = ? WHERE id = ?")
        ->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
    ok(['ok' => true, 'user' => $u]);
    break;
  }

  // ========================== TOURNAMENTS ===========================
  case 'tournaments.list': {
    require_login(); // admin + référent peuvent lire
    ensure_tournament_finished_column(db());
    $rows = db()->query(
      "SELECT id, created_by, name, event_date, place, status, archived,
              join_code, live_phase, config, created_at, launched_at, closed_at,
              finished, finished_at
      FROM vbo_tournaments ORDER BY created_at DESC"
    )->fetchAll();
    foreach ($rows as &$r) {
      $r['archived'] = (bool)$r['archived'];
      $r['finished'] = (bool)$r['finished'];
      $r['config']   = $r['config'] !== null ? json_decode($r['config']) : null;
    }
    ok($rows);
    break;
  }

  case 'tournaments.save': {
    // admin + référent : un référent doit pouvoir clôturer/relancer une phase
    // depuis l'accueil (confirmPhaseSwitch/revertPhase passent par ici).
    require_login();
    $id  = field('id');
    $row = (array)field('row', []);
    // Colonnes autorisées (liste blanche).
    $cols = ['name','event_date','place','status','archived','join_code','live_phase','launched_at','closed_at'];
    $data = [];
    foreach ($cols as $c) if (array_key_exists($c, $row)) $data[$c] = $row[$c];
    if (array_key_exists('config', $row)) $data['config'] = json_encode($row['config'], JSON_UNESCAPED_UNICODE);
    if (isset($data['archived'])) $data['archived'] = $data['archived'] ? 1 : 0;
    if (isset($data['join_code']) && $data['join_code'] === '') $data['join_code'] = null;
    foreach (['launched_at','closed_at'] as $dt) if (array_key_exists($dt, $data)) $data[$dt] = mysql_dt($data[$dt]);

    if (is_uuid($id)) {
      if ($data) {
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
        $vals = array_values($data); $vals[] = $id;
        db()->prepare("UPDATE vbo_tournaments SET $set WHERE id = ?")->execute($vals);
      }
      ok(['id' => $id]);
    } else {
      $newId = uuid4();
      $u = current_user();
      $data['id'] = $newId;
      $data['created_by'] = $u['id'];
      if (!isset($data['name']) || $data['name'] === '') $data['name'] = 'Tournoi';
      if (!isset($data['status'])) $data['status'] = 'draft';
      $keys = array_keys($data);
      $ph   = implode(', ', array_fill(0, count($keys), '?'));
      db()->prepare("INSERT INTO vbo_tournaments (" . implode(', ', $keys) . ") VALUES ($ph)")
          ->execute(array_values($data));
      ok(['id' => $newId]);
    }
    break;
  }

  case 'tournaments.setArchived': {
    require_admin();
    $id = field('id');
    if (!is_uuid($id)) { ok(); break; }
    db()->prepare("UPDATE vbo_tournaments SET archived = ? WHERE id = ?")
        ->execute([field('archived') ? 1 : 0, $id]);
    ok();
    break;
  }

  case 'tournaments.setFinished': {
    require_admin();
    $id = field('id');
    if (!is_uuid($id)) { ok(); break; }
    ensure_tournament_finished_column(db());
    $finished = field('finished') ? 1 : 0;
    db()->prepare("UPDATE vbo_tournaments SET finished = ?, finished_at = ? WHERE id = ?")
        ->execute([$finished, $finished ? mysql_dt(field('finished_at', date('c'))) : null, $id]);
    ok();
    break;
  }

  case 'tournaments.setStatus': {
    require_admin();
    $id = field('id');
    if (!is_uuid($id)) { ok(); break; }
    $status = (string)field('status', 'draft');
    if (!in_array($status, ['draft','live','closed'], true)) fail(400, "Statut invalide.");
    $extra = ['status' => $status];
    foreach (['closed_at','launched_at'] as $k) {
      $v = field($k, '__none__');
      if ($v !== '__none__') $extra[$k] = mysql_dt($v);
    }
    $set  = implode(', ', array_map(fn($c) => "$c = ?", array_keys($extra)));
    $vals = array_values($extra); $vals[] = $id;
    db()->prepare("UPDATE vbo_tournaments SET $set WHERE id = ?")->execute($vals);
    ok();
    break;
  }

  case 'tournaments.remove': {
    require_admin();
    $id = field('id');
    if (is_uuid($id)) db()->prepare("DELETE FROM vbo_tournaments WHERE id = ?")->execute([$id]);
    ok();
    break;
  }

  case 'tournaments.saveStructure': {
    require_admin();
    $tid = field('tid');
    if (!is_uuid($tid)) fail(400, "Tournoi non encore enregistré.");
    $s = (array)field('s', []);
    $gyms = $s['gyms'] ?? []; $teams = $s['teams'] ?? [];
    $pools = $s['pools'] ?? []; $matches = $s['matches'] ?? [];
    $pdo = db();
    try {
      $pdo->beginTransaction();
      // Effacement (l'ordre respecte les clés étrangères).
      foreach (['vbo_matches','vbo_pools','vbo_teams','vbo_gyms'] as $tbl) {
        $pdo->prepare("DELETE FROM $tbl WHERE tournament_id = ?")->execute([$tid]);
      }
      // Gymnases
      $gymKeyToId = []; $nameToGymId = [];
      $insGym = $pdo->prepare("INSERT INTO vbo_gyms (id, tournament_id, name, position) VALUES (?,?,?,?)");
      foreach ($gyms as $g) {
        $gid = uuid4();
        $insGym->execute([$gid, $tid, $g['name'] ?? '', (int)($g['position'] ?? 0)]);
        if (isset($g['key'])) $gymKeyToId[$g['key']] = $gid;
        $nameToGymId[$g['name'] ?? ''] = $gid;
      }
      // Équipes
      ensure_team_phone_column($pdo);
      $teamNameToId = [];
      $insTeam = $pdo->prepare("INSERT INTO vbo_teams (id, tournament_id, name, level, phone) VALUES (?,?,?,?,?)");
      foreach ($teams as $t) {
        $teid = uuid4();
        $lvl = isset($t['levelW']) ? (int)$t['levelW'] : null;
        $phone = trim((string)($t['phone'] ?? ''));
        $insTeam->execute([$teid, $tid, $t['name'] ?? '', $lvl, $phone !== '' ? $phone : null]);
        $teamNameToId[$t['name'] ?? ''] = $teid;
      }
      // Poules
      $poolLetterToId = [];
      $insPool = $pdo->prepare("INSERT INTO vbo_pools (id, tournament_id, letter, phase, gym_id, court) VALUES (?,?,?,?,?,?)");
      foreach ($pools as $p) {
        $pid = uuid4();
        $gymId = isset($p['gymKey']) ? ($gymKeyToId[$p['gymKey']] ?? null) : null;
        $court = array_key_exists('court', $p) && $p['court'] !== null ? (int)$p['court'] : null;
        $insPool->execute([$pid, $tid, $p['letter'] ?? '', $p['phase'] ?? 'brassage', $gymId, $court]);
        $poolLetterToId[$p['letter'] ?? ''] = $pid;
      }
      // Appartenances équipe ↔ poule
      $insPT = $pdo->prepare("INSERT INTO vbo_pool_teams (id, pool_id, team_id, seed) VALUES (?,?,?,?)");
      foreach ($pools as $p) {
        $pid = $poolLetterToId[$p['letter'] ?? ''] ?? null;
        foreach (($p['teamKeys'] ?? []) as $seed => $nm) {
          $teid = $teamNameToId[$nm] ?? null;
          if ($pid && $teid) $insPT->execute([uuid4(), $pid, $teid, (int)$seed]);
        }
      }
      // Matchs
      $insMatch = $pdo->prepare(
        "INSERT INTO vbo_matches (id, tournament_id, pool_id, phase, round, scheduled_time,
                              gym_id, court, team_a, team_b, referee_team, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'todo')");
      $nMatches = 0;
      foreach ($matches as $m) {
        $poolId = isset($m['poolKey']) ? ($poolLetterToId[$m['poolKey']] ?? null) : null;
        $gymId  = isset($m['gymName']) ? ($nameToGymId[$m['gymName']] ?? null) : null;
        $court  = array_key_exists('court', $m) && $m['court'] !== null ? (int)$m['court'] : null;
        $teamA  = isset($m['aKey'])   ? ($teamNameToId[$m['aKey']] ?? null) : null;
        $teamB  = isset($m['bKey'])   ? ($teamNameToId[$m['bKey']] ?? null) : null;
        $ref    = !empty($m['refKey']) ? ($teamNameToId[$m['refKey']] ?? null) : null;
        $insMatch->execute([
          uuid4(), $tid, $poolId, $m['phase'] ?? 'brassage',
          isset($m['round']) ? (int)$m['round'] : null, $m['time'] ?? null,
          $gymId, $court, $teamA, $teamB, $ref,
        ]);
        $nMatches++;
      }
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      fail(500, "Échec de l'enregistrement de la structure.");
    }
    ok(['gyms' => count($gyms), 'teams' => count($teams), 'pools' => count($pools), 'matches' => $nMatches]);
    break;
  }

  // Ajoute les poules/matchs d'une NOUVELLE phase (ex: Principale/Consolante après le
  // brassage) SANS toucher à l'existant — contrairement à tournaments.saveStructure qui
  // efface tout. Réutilise les équipes/gymnases déjà créés au lancement (par nom).
  case 'tournaments.addPhase': {
    // admin + référent : déclenché par la même action de clôture de phase que
    // tournaments.save ci-dessus (confirmPhaseSwitch génère les poules/matchs
    // de la phase suivante et les persiste via cette route).
    require_login();
    $tid = field('tid');
    if (!is_uuid($tid)) fail(400, "Tournoi non encore enregistré.");
    $s = (array)field('s', []);
    $gyms = $s['gyms'] ?? []; $pools = $s['pools'] ?? []; $matches = $s['matches'] ?? [];
    $pdo = db();
    try {
      $pdo->beginTransaction();

      $teamNameToId = [];
      $st = $pdo->prepare("SELECT id, name FROM vbo_teams WHERE tournament_id = ?");
      $st->execute([$tid]);
      foreach ($st->fetchAll() as $row) $teamNameToId[$row['name']] = $row['id'];

      $nameToGymId = [];
      $st = $pdo->prepare("SELECT id, name FROM vbo_gyms WHERE tournament_id = ?");
      $st->execute([$tid]);
      foreach ($st->fetchAll() as $row) $nameToGymId[$row['name']] = $row['id'];
      $insGym = $pdo->prepare("INSERT INTO vbo_gyms (id, tournament_id, name, position) VALUES (?,?,?,?)");
      foreach ($gyms as $g) {
        $name = $g['name'] ?? '';
        if ($name === '' || isset($nameToGymId[$name])) continue;
        $gid = uuid4();
        $insGym->execute([$gid, $tid, $name, (int)($g['position'] ?? 0)]);
        $nameToGymId[$name] = $gid;
      }

      // Poules de cette phase (clé = phase|lettre, au cas où une lettre est réutilisée
      // entre phases, ex. Principale ET Consolante ont chacune une poule "A").
      $poolKeyToId = [];
      $insPool = $pdo->prepare("INSERT INTO vbo_pools (id, tournament_id, letter, phase, gym_id, court) VALUES (?,?,?,?,?,?)");
      foreach ($pools as $p) {
        $pid = uuid4();
        $phase = $p['phase'] ?? 'poules2';
        $gymId = isset($p['gymName']) ? ($nameToGymId[$p['gymName']] ?? null) : null;
        $court = array_key_exists('court', $p) && $p['court'] !== null ? (int)$p['court'] : null;
        $insPool->execute([$pid, $tid, $p['letter'] ?? '', $phase, $gymId, $court]);
        $poolKeyToId[$phase . '|' . ($p['letter'] ?? '')] = $pid;
      }
      $insPT = $pdo->prepare("INSERT INTO vbo_pool_teams (id, pool_id, team_id, seed) VALUES (?,?,?,?)");
      foreach ($pools as $p) {
        $phase = $p['phase'] ?? 'poules2';
        $pid = $poolKeyToId[$phase . '|' . ($p['letter'] ?? '')] ?? null;
        foreach (($p['teamKeys'] ?? []) as $seed => $nm) {
          $teid = $teamNameToId[$nm] ?? null;
          if ($pid && $teid) $insPT->execute([uuid4(), $pid, $teid, (int)$seed]);
        }
      }

      $insMatch = $pdo->prepare(
        "INSERT INTO vbo_matches (id, tournament_id, pool_id, phase, round, scheduled_time,
                              gym_id, court, team_a, team_b, referee_team, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'todo')");
      $nMatches = 0;
      foreach ($matches as $m) {
        $phase  = $m['phase'] ?? 'poules2';
        $poolId = isset($m['poolKey']) ? ($poolKeyToId[$phase . '|' . $m['poolKey']] ?? null) : null;
        $gymId  = isset($m['gymName']) ? ($nameToGymId[$m['gymName']] ?? null) : null;
        $court  = array_key_exists('court', $m) && $m['court'] !== null ? (int)$m['court'] : null;
        $teamA  = isset($m['aKey'])   ? ($teamNameToId[$m['aKey']] ?? null) : null;
        $teamB  = isset($m['bKey'])   ? ($teamNameToId[$m['bKey']] ?? null) : null;
        $ref    = !empty($m['refKey']) ? ($teamNameToId[$m['refKey']] ?? null) : null;
        $insMatch->execute([
          uuid4(), $tid, $poolId, $phase,
          isset($m['round']) ? (int)$m['round'] : null, $m['time'] ?? null,
          $gymId, $court, $teamA, $teamB, $ref,
        ]);
        $nMatches++;
      }
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      fail(500, "Échec de l'enregistrement de la phase.");
    }
    ok(['pools' => count($pools), 'matches' => $nMatches]);
    break;
  }

  case 'tournaments.loadStructure': {
    require_login();
    $tid = $_GET['tid'] ?? '';
    if (!is_uuid($tid)) { ok(null); break; }
    ok(load_structure($tid));
    break;
  }

  // Saisie manuelle d'un score par un admin ou un référent (quand les
  // capitaines ne saisissent pas eux-mêmes) : écrit directement le résultat
  // validé sur le match, sans passer par la double-saisie croisée des
  // capitaines (vbo_score_entries, flux distinct, pas encore branché).
  case 'matches.setScore': {
    require_login();
    $id = (string)field('id', '');
    if (!is_uuid($id)) fail(400, "Match invalide.");
    $clean = clean_sets(field('sets', []));
    $st = db()->prepare("SELECT id FROM vbo_matches WHERE id = ?");
    $st->execute([$id]);
    if (!$st->fetch()) fail(404, "Match introuvable.");
    // `complete` : le front envoie tous les sets joués d'un coup (set par set au
    // fil du match), mais ne marque le match validé que lorsque tous les sets
    // configurés ont été saisis — sinon il reste 'todo' (= upcoming côté front).
    $complete = (bool) field('complete', true);
    $status = $complete ? 'valid' : 'todo';
    db()->prepare("UPDATE vbo_matches SET score_a = ?, score_b = ?, sets = ?, status = ? WHERE id = ?")
      ->execute([$clean[0][0], $clean[0][1], json_encode($clean), $status, $id]);
    ok(['ok' => true]);
    break;
  }

  // Double-saisie croisée des capitaines (public, sans authentification —
  // mêmes capitaines que tournaments.publicByCode, sans compte). Chaque
  // capitaine envoie son score ; on l'oriente dans le sens team_a/team_b du
  // match pour pouvoir comparer les deux saisies. Si les deux concordent, le
  // match est validé automatiquement ; sinon il reste 'pending' (= affiché
  // "en attente" côté capitaines) jusqu'à l'arbitrage de l'organisation.
  case 'matches.submitCaptainScore': {
    $matchId = (string)field('matchId', '');
    if (!is_uuid($matchId)) fail(400, "Match invalide.");
    $teamName = trim((string)field('teamName', ''));
    if ($teamName === '') fail(400, "Équipe manquante.");
    $clean = clean_sets(field('sets', []));

    $pdo = db();
    ensure_score_entries_table($pdo);

    $st = $pdo->prepare("SELECT m.status, m.team_a, m.team_b, ta.name AS name_a, tb.name AS name_b
                          FROM vbo_matches m
                          LEFT JOIN vbo_teams ta ON ta.id = m.team_a
                          LEFT JOIN vbo_teams tb ON tb.id = m.team_b
                          WHERE m.id = ?");
    $st->execute([$matchId]);
    $m = $st->fetch();
    if (!$m) fail(404, "Match introuvable.");
    if ($m['status'] === 'valid') fail(409, "Ce match est déjà validé.");

    $norm = fn($s) => mb_strtolower(trim((string)$s));
    if ($norm($teamName) === $norm($m['name_a'])) { $teamId = $m['team_a']; $side = 'a'; }
    elseif ($norm($teamName) === $norm($m['name_b'])) { $teamId = $m['team_b']; $side = 'b'; }
    else fail(400, "Cette équipe ne joue pas ce match.");

    // Toujours stocké dans le sens team_a/team_b du match, quel que soit le
    // capitaine qui saisit, pour que les deux lignes soient comparables.
    $canon = $side === 'a' ? $clean : array_map(fn($s) => [$s[1], $s[0]], $clean);

    $pdo->prepare("INSERT INTO vbo_score_entries (id, match_id, team_id, sets, score_a, score_b, created_at)
                    VALUES (?,?,?,?,?,?,NOW())
                    ON DUPLICATE KEY UPDATE sets = VALUES(sets), score_a = VALUES(score_a), score_b = VALUES(score_b), created_at = NOW()")
      ->execute([uuid4(), $matchId, $teamId, json_encode($canon), $canon[0][0], $canon[0][1]]);

    $st = $pdo->prepare("SELECT sets FROM vbo_score_entries WHERE match_id = ?");
    $st->execute([$matchId]);
    $entries = $st->fetchAll();

    if (count($entries) < 2) {
      $pdo->prepare("UPDATE vbo_matches SET status = 'pending' WHERE id = ? AND status = 'todo'")->execute([$matchId]);
      ok(['status' => 'pending']);
      break;
    }

    if ($entries[0]['sets'] === $entries[1]['sets']) {
      $agreed = json_decode($entries[0]['sets'], true);
      try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE vbo_matches SET score_a = ?, score_b = ?, sets = ?, status = 'valid' WHERE id = ?")
          ->execute([$agreed[0][0], $agreed[0][1], json_encode($agreed), $matchId]);
        $pdo->prepare("DELETE FROM vbo_score_entries WHERE match_id = ?")->execute([$matchId]);
        $pdo->commit();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fail(500, "Échec de la validation du score.");
      }
      ok(['status' => 'validated', 'sa' => $agreed[0][0], 'sb' => $agreed[0][1], 'sets' => $agreed]);
    } else {
      // Conflit : volontairement pas exposé au capitaine (saisie "à l'aveugle"),
      // seule l'organisation le voit via matches.conflicts.
      $pdo->prepare("UPDATE vbo_matches SET status = 'pending' WHERE id = ?")->execute([$matchId]);
      ok(['status' => 'pending']);
    }
    break;
  }

  // Démarre un match (public, sans authentification — même modèle de
  // confiance que submitCaptainScore : il faut connaître l'id du match ET
  // le nom d'une équipe qui y est partie prenante, comme capitaine ou comme
  // arbitre). C'est ce qui bascule le match en "live" côté API : avant ça,
  // même un match 'todo' ne peut pas recevoir de score (cf. live_phase()).
  case 'matches.startMatch': {
    $matchId = (string)field('matchId', '');
    if (!is_uuid($matchId)) fail(400, "Match invalide.");
    $teamName = trim((string)field('teamName', ''));
    if ($teamName === '') fail(400, "Équipe manquante.");

    $pdo = db();
    ensure_match_started_column($pdo);
    $st = $pdo->prepare("SELECT m.status, ta.name AS name_a, tb.name AS name_b, tr.name AS name_ref
                          FROM vbo_matches m
                          LEFT JOIN vbo_teams ta ON ta.id = m.team_a
                          LEFT JOIN vbo_teams tb ON tb.id = m.team_b
                          LEFT JOIN vbo_teams tr ON tr.id = m.referee_team
                          WHERE m.id = ?");
    $st->execute([$matchId]);
    $m = $st->fetch();
    if (!$m) fail(404, "Match introuvable.");
    if ($m['status'] === 'valid') fail(409, "Ce match est déjà validé.");

    $norm = fn($s) => mb_strtolower(trim((string)$s));
    $allowed = array_filter([$norm($m['name_a'] ?? ''), $norm($m['name_b'] ?? ''), $norm($m['name_ref'] ?? '')]);
    if (!in_array($norm($teamName), $allowed, true)) fail(403, "Cette équipe n'est pas partie prenante de ce match.");

    $pdo->prepare("UPDATE vbo_matches SET started_at = COALESCE(started_at, NOW()) WHERE id = ?")->execute([$matchId]);
    ok(['ok' => true]);
    break;
  }

  // Même bascule en "live", déclenchée par l'organisation (admin/référent)
  // depuis le planning, plutôt que par une équipe sur le terrain.
  case 'matches.adminStartMatch': {
    require_login();
    $matchId = (string)field('matchId', '');
    if (!is_uuid($matchId)) fail(400, "Match invalide.");

    $pdo = db();
    ensure_match_started_column($pdo);
    $st = $pdo->prepare("SELECT status FROM vbo_matches WHERE id = ?");
    $st->execute([$matchId]);
    $m = $st->fetch();
    if (!$m) fail(404, "Match introuvable.");
    if ($m['status'] === 'valid') fail(409, "Ce match est déjà validé.");

    $pdo->prepare("UPDATE vbo_matches SET started_at = COALESCE(started_at, NOW()) WHERE id = ?")->execute([$matchId]);
    ok(['ok' => true]);
    break;
  }

  // Liste les matchs à traiter par l'organisation sur l'écran "Valider" :
  // soit un vrai conflit (deux saisies différentes), soit une saisie seule
  // qui attend l'autre capitaine depuis un moment — l'organisation peut
  // choisir de valider ce score sans attendre.
  case 'matches.conflicts': {
    require_login();
    $tid = (string)($_GET['tid'] ?? '');
    if (!is_uuid($tid)) { ok([]); break; }
    $pdo = db();
    ensure_score_entries_table($pdo);
    $st = $pdo->prepare("SELECT se.match_id, se.team_id, se.sets, se.score_a, se.score_b,
                                 m.round, m.scheduled_time, m.court, m.team_a, m.team_b,
                                 g.name AS gym_name, p.letter AS pool_letter,
                                 ta.name AS name_a, tb.name AS name_b
                          FROM vbo_score_entries se
                          JOIN vbo_matches m ON m.id = se.match_id
                          LEFT JOIN vbo_gyms g ON g.id = m.gym_id
                          LEFT JOIN vbo_pools p ON p.id = m.pool_id
                          LEFT JOIN vbo_teams ta ON ta.id = m.team_a
                          LEFT JOIN vbo_teams tb ON tb.id = m.team_b
                          WHERE m.tournament_id = ?
                          ORDER BY se.match_id");
    $st->execute([$tid]);
    $byMatch = [];
    foreach ($st->fetchAll() as $r) { $byMatch[$r['match_id']][] = $r; }
    $out = [];
    foreach ($byMatch as $matchId => $pair) {
      if (count($pair) === 2 && $pair[0]['sets'] === $pair[1]['sets']) continue; // filet de sécurité (devrait déjà être auto-validé)
      $bySide = [];
      foreach ($pair as $p) { $bySide[$p['team_id'] === $p['team_a'] ? 'a' : 'b'] = $p; }
      $entry = fn($s) => isset($bySide[$s])
        ? ['sa' => (int)$bySide[$s]['score_a'], 'sb' => (int)$bySide[$s]['score_b'], 'sets' => json_decode($bySide[$s]['sets'])]
        : null;
      $out[] = [
        'matchId' => $matchId,
        'type'    => count($pair) === 2 ? 'conflict' : 'waiting',
        'round'   => $pair[0]['round'] !== null ? (int)$pair[0]['round'] : null,
        'time'    => $pair[0]['scheduled_time'],
        'gym'     => $pair[0]['gym_name'] ?? '',
        'court'   => $pair[0]['court'] !== null ? (int)$pair[0]['court'] : null,
        'pool'    => $pair[0]['pool_letter'] ?? '',
        'a'       => $pair[0]['name_a'] ?? '',
        'b'       => $pair[0]['name_b'] ?? '',
        'entryA'  => $entry('a'),
        'entryB'  => $entry('b'),
      ];
    }
    ok($out);
    break;
  }

  // Tranche un conflit de double-saisie : reprend la saisie d'un des deux
  // capitaines (`pick`:'a'|'b') ou un score saisi par l'organisation (`sets`).
  case 'matches.resolveConflict': {
    require_login();
    $matchId = (string)field('matchId', '');
    if (!is_uuid($matchId)) fail(400, "Match invalide.");
    $pdo = db();
    ensure_score_entries_table($pdo);

    $pick = field('pick', null);
    $setsIn = field('sets', null);
    if ($setsIn !== null) {
      $clean = clean_sets($setsIn);
    } elseif ($pick === 'a' || $pick === 'b') {
      $st = $pdo->prepare("SELECT se.team_id, se.sets, m.team_a, m.team_b
                            FROM vbo_score_entries se JOIN vbo_matches m ON m.id = se.match_id
                            WHERE se.match_id = ?");
      $st->execute([$matchId]);
      $clean = null;
      foreach ($st->fetchAll() as $r) {
        $side = $r['team_id'] === $r['team_a'] ? 'a' : 'b';
        if ($side === $pick) { $clean = json_decode($r['sets'], true); break; }
      }
      if ($clean === null) fail(404, "Saisie introuvable.");
    } else {
      fail(400, "Indiquez le score retenu (pick ou sets).");
    }

    $st = $pdo->prepare("SELECT id FROM vbo_matches WHERE id = ?");
    $st->execute([$matchId]);
    if (!$st->fetch()) fail(404, "Match introuvable.");

    $pdo->prepare("UPDATE vbo_matches SET score_a = ?, score_b = ?, sets = ?, status = 'valid' WHERE id = ?")
      ->execute([$clean[0][0], $clean[0][1], json_encode($clean), $matchId]);
    $pdo->prepare("DELETE FROM vbo_score_entries WHERE match_id = ?")->execute([$matchId]);
    ok(['ok' => true]);
    break;
  }

  // Accès public (sans authentification) pour un capitaine qui scanne le QR
  // code / saisit le code du tournoi : retrouve le tournoi en cours par son
  // join_code et renvoie juste de quoi afficher la liste des équipes et le
  // planning (pas de données sensibles : c'est précisément ce que le QR est
  // censé donner).
  case 'tournaments.publicByCode': {
    $code = strtoupper(trim((string)($_GET['code'] ?? '')));
    if ($code === '') fail(400, "Code manquant.");
    $st = db()->prepare("SELECT id, name, status FROM vbo_tournaments WHERE join_code = ?");
    $st->execute([$code]);
    $t = $st->fetch();
    if (!$t || $t['status'] !== 'live') { ok(null); break; }
    $s = load_structure($t['id']);
    if ($s === null) { ok(null); break; }
    $team = trim((string)($_GET['team'] ?? ''));
    $myEntries = $team !== '' ? my_entries_for_team(db(), $t['id'], $team) : [];
    ok(['name' => $t['name'], 'pools' => $s['pools'], 'schedule' => $s['schedule'], 'myEntries' => $myEntries]);
    break;
  }

  // Vérifie le numéro saisi par un capitaine après son choix d'équipe, contre
  // celui enregistré par l'organisation à l'inscription — sans jamais renvoyer
  // ce numéro au client (seulement un booléen). Si l'équipe n'a pas de numéro
  // enregistré (tournois créés avant cette fonctionnalité, ou champ laissé
  // vide), on laisse passer (skip:true) plutôt que de bloquer le capitaine.
  case 'teams.verifyPhone': {
    $code = strtoupper(trim((string)field('code', '')));
    $team = trim((string)field('team', ''));
    if ($code === '' || $team === '') fail(400, "Paramètres manquants.");
    $st = db()->prepare("SELECT id FROM vbo_tournaments WHERE join_code = ?");
    $st->execute([$code]);
    $t = $st->fetch();
    if (!$t) fail(404, "Tournoi introuvable.");
    $pdo = db();
    ensure_team_phone_column($pdo);
    $st = $pdo->prepare("SELECT phone FROM vbo_teams WHERE tournament_id = ? AND name = ?");
    $st->execute([$t['id'], $team]);
    $row = $st->fetch();
    if (!$row) fail(404, "Équipe introuvable.");
    $stored = normalize_phone((string)($row['phone'] ?? ''));
    if ($stored === '') { ok(['ok' => true, 'skip' => true]); break; }
    $given = normalize_phone((string)field('phone', ''));
    ok(['ok' => $given !== '' && $given === $stored]);
    break;
  }

  // Répertoire des capitaines (admin/référent, voir lien tel: côté client) :
  // seule route qui renvoie le numéro en clair, jamais exposée aux capitaines.
  case 'teams.directory': {
    require_login();
    $tid = (string)($_GET['tid'] ?? '');
    if (!is_uuid($tid)) fail(400, "Tournoi non encore enregistré.");
    $pdo = db();
    ensure_team_phone_column($pdo);
    $st = $pdo->prepare("SELECT t.name AS name, t.phone AS phone, p.letter AS pool
                         FROM vbo_teams t
                         LEFT JOIN vbo_pool_teams pt ON pt.team_id = t.id
                         LEFT JOIN vbo_pools p ON p.id = pt.pool_id AND p.phase = 'brassage'
                         WHERE t.tournament_id = ?
                         ORDER BY t.name");
    $st->execute([$tid]);
    ok(['teams' => $st->fetchAll()]);
    break;
  }

  // =========================== REFERENTS ============================
  case 'referents.list': {
    require_admin();
    $rows = db()->query(
      "SELECT id, full_name, email, status, created_at
       FROM vbo_users WHERE role = 'referent' ORDER BY created_at ASC"
    )->fetchAll();
    ok($rows);
    break;
  }

  case 'referents.create': {
    require_admin();
    $name  = trim((string)field('name', ''));
    $email = strtolower(trim((string)field('email', '')));
    $pass  = (string)field('password', '');
    if ($name === '') fail(400, "Le nom du référent est requis.");
    if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) fail(400, "Adresse e-mail invalide.");
    if (strlen($pass) < 8) fail(400, "Le mot de passe doit faire au moins 8 caractères.");
    // Doublon ?
    $st = db()->prepare("SELECT id FROM vbo_users WHERE email = ?");
    $st->execute([$email]);
    if ($st->fetch()) fail(409, "Un compte existe déjà pour cette adresse e-mail.");
    $id = uuid4();
    db()->prepare(
      "INSERT INTO vbo_users (id, email, password_hash, full_name, role, status)
      VALUES (?,?,?,?, 'referent', 'active')"
    )->execute([$id, $email, password_hash($pass, PASSWORD_DEFAULT), $name]);
    ok(['ok' => true, 'id' => $id, 'email' => $email, 'name' => $name]);
    break;
  }

  case 'referents.setStatus': {
    require_admin();
    $id = field('id');
    $status = (string)field('status', 'active');
    if (!in_array($status, ['active','suspended'], true)) fail(400, "Statut invalide.");
    if (!is_uuid($id)) fail(400, "Référent inconnu.");
    db()->prepare("UPDATE vbo_users SET status = ? WHERE id = ? AND role = 'referent'")
        ->execute([$status, $id]);
    ok();
    break;
  }

  case 'referents.remove': {
    require_admin();
    $id = field('id');
    if (is_uuid($id)) {
      db()->prepare("DELETE FROM vbo_users WHERE id = ? AND role = 'referent'")->execute([$id]);
    }
    ok();
    break;
  }

  // =========================== ORGANIZERS ============================
  case 'organizers.list': {
    require_admin();
    $rows = db()->query(
      "SELECT id, full_name, email, status, created_at
       FROM vbo_users WHERE role = 'admin' ORDER BY created_at ASC"
    )->fetchAll();
    ok($rows);
    break;
  }

  case 'organizers.create': {
    require_admin();
    $name  = trim((string)field('name', ''));
    $email = strtolower(trim((string)field('email', '')));
    $pass  = (string)field('password', '');
    if ($name === '') fail(400, "Le nom de l'organisateur est requis.");
    if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) fail(400, "Adresse e-mail invalide.");
    if (strlen($pass) < 8) fail(400, "Le mot de passe doit faire au moins 8 caractères.");
    $st = db()->prepare("SELECT id FROM vbo_users WHERE email = ?");
    $st->execute([$email]);
    if ($st->fetch()) fail(409, "Un compte existe déjà pour cette adresse e-mail.");
    $id = uuid4();
    db()->prepare(
      "INSERT INTO vbo_users (id, email, password_hash, full_name, role, status)
      VALUES (?,?,?,?, 'admin', 'active')"
    )->execute([$id, $email, password_hash($pass, PASSWORD_DEFAULT), $name]);
    ok(['ok' => true, 'id' => $id, 'email' => $email, 'name' => $name]);
    break;
  }

  case 'organizers.setStatus': {
    $u = require_admin();
    $id = field('id');
    $status = (string)field('status', 'active');
    if (!in_array($status, ['active','suspended'], true)) fail(400, "Statut invalide.");
    if (!is_uuid($id)) fail(400, "Organisateur inconnu.");
    if ($id === $u['id']) fail(400, "Vous ne pouvez pas modifier votre propre compte depuis cet écran.");
    db()->prepare("UPDATE vbo_users SET status = ? WHERE id = ? AND role = 'admin'")
        ->execute([$status, $id]);
    ok();
    break;
  }

  case 'organizers.remove': {
    $u = require_admin();
    $id = field('id');
    if ($id === $u['id']) fail(400, "Vous ne pouvez pas supprimer votre propre compte.");
    if (is_uuid($id)) {
      db()->prepare("DELETE FROM vbo_users WHERE id = ? AND role = 'admin'")->execute([$id]);
    }
    ok();
    break;
  }

  default:
    fail(404, "Action inconnue : " . ($action ?: '(vide)'));
}
