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
//    tournaments.setStatus POST {id,status,closed_at?}   -> {ok}
//    tournaments.remove    POST {id}                     -> {ok}
//    tournaments.saveStructure POST {tid,s}              -> {counts} (remplace TOUT)
//    tournaments.addPhase     POST {tid,s}              -> {counts} (ajoute une phase, sans toucher au reste)
//    tournaments.loadStructure GET  ?tid=                -> {pools,schedule}|null
//    matches.setScore      POST {id,sets}                -> {ok} (admin/référent)
//    tournaments.publicByCode  GET  ?code= (public)       -> {name,pools,schedule}|null
//    referents.list        GET                           -> [rows]
//    referents.create      POST {name,email,password}    -> {ok,id,email,name}
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

  $S = ['valid' => 'done', 'live' => 'live', 'pending' => 'live', 'todo' => 'upcoming'];
  usort($matches, function ($a, $b) {
    $r = ((int)$a['round']) <=> ((int)$b['round']);
    return $r !== 0 ? $r : strcmp((string)$a['scheduled_time'], (string)$b['scheduled_time']);
  });
  $schedOut = array_map(function ($m) use ($gymById, $teamById, $poolById, $S) {
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
      'status' => $S[$m['status']] ?? 'upcoming',
    ];
  }, $matches);

  return ['pools' => $poolsOut, 'schedule' => $schedOut];
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
    $rows = db()->query(
      "SELECT id, created_by, name, event_date, place, status, archived,
              join_code, live_phase, config, created_at, launched_at, closed_at
      FROM vbo_tournaments ORDER BY created_at DESC"
    )->fetchAll();
    foreach ($rows as &$r) {
      $r['archived'] = (bool)$r['archived'];
      $r['config']   = $r['config'] !== null ? json_decode($r['config']) : null;
    }
    ok($rows);
    break;
  }

  case 'tournaments.save': {
    require_admin();
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
      $teamNameToId = [];
      $insTeam = $pdo->prepare("INSERT INTO vbo_teams (id, tournament_id, name, level) VALUES (?,?,?,?)");
      foreach ($teams as $t) {
        $teid = uuid4();
        $lvl = isset($t['levelW']) ? (int)$t['levelW'] : null;
        $insTeam->execute([$teid, $tid, $t['name'] ?? '', $lvl]);
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
    require_admin();
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
    $sets = field('sets', []);
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
    ok(['name' => $t['name'], 'pools' => $s['pools'], 'schedule' => $s['schedule']]);
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

  default:
    fail(404, "Action inconnue : " . ($action ?: '(vide)'));
}
