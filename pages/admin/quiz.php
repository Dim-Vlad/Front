<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_any_role(['admin', 'moderateur'])) {
    header('Location: /pages/auth/tableau-de-bord.php'); exit;
}

$pdo = get_pdo();

$questions = $pdo->query(
    'SELECT q.*, u.prenom, u.nom,
            (SELECT COUNT(*) FROM quiz_reponses r WHERE r.question_id = q.id) AS nb_reponses
    FROM quiz_questions q
    JOIN users u ON u.id = q.created_by
    ORDER BY q.actif DESC, q.created_at DESC'
)->fetchAll();

$nbTotal  = count($questions);
$nbActifs = count(array_filter($questions, fn($q) => $q['actif']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Quiz - VBO</title>
    <link href="/css/styles.css?v=20261005" rel="stylesheet">
    <link href="/css/quiz.css?v=20260703" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Gestion du Quiz</h1>
                <p><?= $nbActifs ?> question<?= $nbActifs > 1 ? 's' : '' ?> active<?= $nbActifs > 1 ? 's' : '' ?> · <?= $nbTotal ?> au total</p>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap">
    <?php if (has_role('admin')): ?>
    <a href="/pages/admin/index.php" class="back-btn">← Administration</a>
    <?php else: ?>
    <a href="/pages/moderateur/index.php" class="back-btn">← Modération</a>
    <?php endif; ?>
    <a href="/php/quiz/export_reponses.php" class="back-btn">⬇ Exporter les réponses (CSV)</a>
    </div>

    <div class="quiz-admin-container">

        <h2 class="quiz-admin-section-title quiz-admin-section-title--toggle" onclick="toggleCreateForm()">
            <span>➕ Créer une question</span>
            <span id="create-form-arrow">Déplier ▾</span>
        </h2>
        <div class="quiz-create-form" id="create-form" style="display:none">
            <div class="quiz-form-grid">

                <div class="quiz-form-group full-width">
                    <label>Question *</label>
                    <textarea id="f-question" placeholder="Ex : Combien de joueurs par équipe en volleyball de plage ?"></textarea>
                </div>

                <div class="quiz-form-group">
                    <label>Choix A *</label>
                    <input id="f-choix_a" type="text" placeholder="Réponse A">
                </div>
                <div class="quiz-form-group">
                    <label>Choix B *</label>
                    <input id="f-choix_b" type="text" placeholder="Réponse B">
                </div>
                <div class="quiz-form-group">
                    <label>Choix C</label>
                    <input id="f-choix_c" type="text" placeholder="Réponse C (optionnel)">
                </div>
                <div class="quiz-form-group">
                    <label>Choix D</label>
                    <input id="f-choix_d" type="text" placeholder="Réponse D (optionnel)">
                </div>

                <div class="quiz-form-group">
                    <label>Bonne réponse *</label>
                    <select id="f-reponse_correcte">
                        <option value="">-- Sélectionner --</option>
                        <option value="a">A</option>
                        <option value="b">B</option>
                        <option value="c">C</option>
                        <option value="d">D</option>
                    </select>
                </div>
                <div class="quiz-form-group">
                    <label>Points</label>
                    <select id="f-points">
                        <option value="1">1 point</option>
                        <option value="2">2 points</option>
                        <option value="3">3 points</option>
                    </select>
                </div>

                <div class="quiz-form-group full-width">
                    <label>Explication (affichée après la réponse)</label>
                    <textarea id="f-explication" placeholder="Ex : En volleyball intérieur, chaque équipe aligne 6 joueurs sur le terrain." rows="2"></textarea>
                </div>

                <div class="quiz-form-actions">
                    <button class="quiz-btn-create" onclick="createQuestion()">+ Créer la question</button>
                </div>
            </div>
        </div>

        <h2 class="quiz-admin-section-title">Importer un quiz préparé à l'avance</h2>
        <div class="quiz-create-form">
            <p style="font-size:.82rem;color:#666;margin:0 0 14px;line-height:1.5">
                Pratique pour remplacer tout le quiz en une fois (ex. nouvelle saison) : préparez vos questions dans
                un tableau, exportez-le au format CSV, puis importez-le ici. Chaque ligne devient une nouvelle question —
                pensez à supprimer les anciennes avant si vous voulez repartir de zéro.
            </p>
            <div class="quiz-form-actions" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <a href="/php/quiz/modele_import.php" class="back-btn" style="margin:0">📥 Télécharger le modèle CSV</a>
                <div style="display:flex;align-items:center;gap:10px">
                    <input type="file" id="f-import" accept=".csv">
                    <button class="quiz-btn-create" onclick="importQuestions()">⬆ Importer</button>
                </div>
            </div>
            <div id="import-feedback" style="margin-top:12px;font-size:.82rem;line-height:1.6"></div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
            <h2 class="quiz-admin-section-title" style="margin:0">Questions (<?= $nbTotal ?>)</h2>
            <?php if ($nbTotal > 0): ?>
            <button class="btn-delete-quiz" style="padding:8px 16px" onclick="deleteAllQuestions()">🗑 Supprimer toutes les questions</button>
            <?php endif; ?>
        </div>

        <?php if (empty($questions)): ?>
        <div class="quiz-empty">Aucune question créée pour le moment.</div>
        <?php else: ?>
        <div id="question-list">
        <?php foreach ($questions as $q): ?>
        <div class="quiz-question-item<?= !$q['actif'] ? ' quiz-question-item--inactive' : '' ?>" id="qitem-<?= $q['id'] ?>">
            <div class="quiz-q-body">
                <div class="quiz-q-text"><?= htmlspecialchars($q['question']) ?></div>
                <div class="quiz-q-meta">
                    <span><?= $q['points'] ?> pt<?= $q['points'] > 1 ? 's' : '' ?></span>
                    <span>Bonne réponse : <span class="quiz-q-badge-correct"><?= strtoupper($q['reponse_correcte']) ?></span></span>
                    <span><?= (int)$q['nb_reponses'] ?> réponse<?= $q['nb_reponses'] > 1 ? 's' : '' ?></span>
                    <?php if (!$q['actif']): ?><span class="quiz-inactive-label">Inactif</span><?php endif; ?>
                </div>
            </div>
            <div class="quiz-q-actions">
                <button class="btn-toggle-quiz <?= $q['actif'] ? 'btn-toggle-quiz--on' : 'btn-toggle-quiz--off' ?>"
                        id="toggle-<?= $q['id'] ?>"
                        onclick="toggleQuestion(<?= $q['id'] ?>)">
                    <?= $q['actif'] ? 'Actif' : 'Inactif' ?>
                </button>
                <button class="btn-edit-quiz" onclick="toggleEditQuestion(<?= $q['id'] ?>)">✏️ Modifier</button>
                <button class="btn-delete-quiz"
                        onclick="deleteQuestion(<?= $q['id'] ?>, <?= htmlspecialchars(json_encode(mb_substr($q['question'], 0, 50)), ENT_QUOTES) ?>)">
                    🗑
                </button>
            </div>

            <div class="quiz-edit-form" id="editform-<?= $q['id'] ?>">
                <div class="quiz-form-group full-width">
                    <label>Question *</label>
                    <textarea id="e-question-<?= $q['id'] ?>"><?= htmlspecialchars($q['question']) ?></textarea>
                </div>
                <div class="quiz-form-group">
                    <label>Choix A *</label>
                    <input id="e-choix_a-<?= $q['id'] ?>" type="text" value="<?= htmlspecialchars($q['choix_a']) ?>">
                </div>
                <div class="quiz-form-group">
                    <label>Choix B *</label>
                    <input id="e-choix_b-<?= $q['id'] ?>" type="text" value="<?= htmlspecialchars($q['choix_b']) ?>">
                </div>
                <div class="quiz-form-group">
                    <label>Choix C</label>
                    <input id="e-choix_c-<?= $q['id'] ?>" type="text" value="<?= htmlspecialchars($q['choix_c'] ?? '') ?>">
                </div>
                <div class="quiz-form-group">
                    <label>Choix D</label>
                    <input id="e-choix_d-<?= $q['id'] ?>" type="text" value="<?= htmlspecialchars($q['choix_d'] ?? '') ?>">
                </div>
                <div class="quiz-form-group">
                    <label>Bonne réponse *</label>
                    <select id="e-reponse-<?= $q['id'] ?>">
                        <?php foreach (['a','b','c','d'] as $l): ?>
                        <option value="<?= $l ?>"<?= $q['reponse_correcte'] === $l ? ' selected' : '' ?>><?= strtoupper($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="quiz-form-group">
                    <label>Points</label>
                    <select id="e-points-<?= $q['id'] ?>">
                        <?php foreach ([1,2,3] as $p): ?>
                        <option value="<?= $p ?>"<?= (int)$q['points'] === $p ? ' selected' : '' ?>><?= $p ?> point<?= $p > 1 ? 's' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="quiz-form-group full-width">
                    <label>Explication</label>
                    <textarea id="e-explication-<?= $q['id'] ?>" rows="2"><?= htmlspecialchars($q['explication'] ?? '') ?></textarea>
                </div>
                <div class="quiz-form-actions">
                    <button class="btn-edit-quiz" onclick="toggleEditQuestion(<?= $q['id'] ?>)">Annuler</button>
                    <button class="quiz-btn-create" onclick="saveQuestion(<?= $q['id'] ?>)">Enregistrer</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <div id="footer"></div>
    <script src="/js/main.js?v=20260705"></script>
    <script>

    function toggleCreateForm() {
        const form  = document.getElementById('create-form');
        const arrow = document.getElementById('create-form-arrow');
        const open  = form.style.display !== 'none';
        form.style.display = open ? 'none' : 'block';
        arrow.textContent  = open ? 'Déplier ▾' : 'Replier ▴';
    }

    function createQuestion() {
        const question = document.getElementById('f-question').value.trim();
        const choix_a  = document.getElementById('f-choix_a').value.trim();
        const choix_b  = document.getElementById('f-choix_b').value.trim();
        const choix_c  = document.getElementById('f-choix_c').value.trim();
        const choix_d  = document.getElementById('f-choix_d').value.trim();
        const reponse  = document.getElementById('f-reponse_correcte').value;
        const points   = document.getElementById('f-points').value;
        const explication = document.getElementById('f-explication').value.trim();

        if (!question || !choix_a || !choix_b || !reponse) {
            alert('Veuillez remplir la question, les choix A et B, et la bonne réponse.');
            return;
        }

        const fd = new FormData();
        fd.append('question', question);
        fd.append('choix_a', choix_a);
        fd.append('choix_b', choix_b);
        fd.append('choix_c', choix_c);
        fd.append('choix_d', choix_d);
        fd.append('reponse_correcte', reponse);
        fd.append('points', points);
        fd.append('explication', explication);

        fetch('/php/quiz/create_question.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert(data.error || 'Erreur'); return; }
                location.reload();
            })
            .catch(() => alert('Erreur réseau'));
    }

    function importQuestions() {
        const input = document.getElementById('f-import');
        const feedback = document.getElementById('import-feedback');
        if (!input.files.length) { feedback.innerHTML = '<span style="color:#c0392b">Choisissez d\'abord un fichier CSV.</span>'; return; }

        const fd = new FormData();
        fd.append('fichier', input.files[0]);
        feedback.textContent = 'Import en cours…';

        fetch('/php/quiz/import_questions.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { feedback.innerHTML = '<span style="color:#c0392b">' + (data.error || 'Erreur') + '</span>'; return; }
                let html = '<span style="color:#2d6a2d;font-weight:600">' + data.creees + ' question(s) importée(s).</span>';
                if (data.erreurs && data.erreurs.length) {
                    html += '<br><span style="color:#c0392b">' + data.erreurs.length + ' ligne(s) ignorée(s) :</span>';
                    html += '<ul style="margin:4px 0 0;padding-left:18px;color:#c0392b">' + data.erreurs.map(e => '<li>' + e + '</li>').join('') + '</ul>';
                }
                feedback.innerHTML = html;
                if (data.creees > 0) setTimeout(() => location.reload(), 1500);
            })
            .catch(() => { feedback.innerHTML = '<span style="color:#c0392b">Erreur réseau</span>'; });
    }

    function toggleQuestion(id) {
        const fd = new FormData();
        fd.append('id', id);

        fetch('/php/quiz/toggle_question.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert(data.error || 'Erreur'); return; }
                const btn  = document.getElementById('toggle-' + id);
                const item = document.getElementById('qitem-' + id);
                if (data.actif) {
                    btn.textContent = 'Actif';
                    btn.className = 'btn-toggle-quiz btn-toggle-quiz--on';
                    item.classList.remove('quiz-question-item--inactive');
                } else {
                    btn.textContent = 'Inactif';
                    btn.className = 'btn-toggle-quiz btn-toggle-quiz--off';
                    item.classList.add('quiz-question-item--inactive');
                }
            })
            .catch(() => alert('Erreur réseau'));
    }

    function deleteAllQuestions() {
        if (!confirm('Supprimer TOUTES les questions du quiz ?\n\nToutes les réponses des utilisateurs seront aussi supprimées, et leurs points de quiz repartiront à zéro.\n\nPensez à avoir exporté les réponses avant si vous voulez garder une trace.')) return;
        if (!confirm('Dernière confirmation : cette action est irréversible. Continuer ?')) return;

        fetch('/php/quiz/delete_all_questions.php', { method: 'POST', body: new FormData() })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert(data.error || 'Erreur'); return; }
                location.reload();
            })
            .catch(() => alert('Erreur réseau'));
    }

    function deleteQuestion(id, texte) {
        if (!confirm('Supprimer la question :\n"' + texte + '…"\n\nToutes les réponses associées seront également supprimées.')) return;

        const fd = new FormData();
        fd.append('id', id);

        fetch('/php/quiz/delete_question.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert(data.error || 'Erreur'); return; }
                const item = document.getElementById('qitem-' + id);
                item.style.transition = 'opacity .2s';
                item.style.opacity = '0';
                setTimeout(() => item.remove(), 200);
            })
            .catch(() => alert('Erreur réseau'));
    }

    function toggleEditQuestion(id) {
        document.getElementById('editform-' + id).classList.toggle('open');
    }

    function saveQuestion(id) {
        const question = document.getElementById('e-question-' + id).value.trim();
        const choix_a   = document.getElementById('e-choix_a-' + id).value.trim();
        const choix_b   = document.getElementById('e-choix_b-' + id).value.trim();
        const choix_c   = document.getElementById('e-choix_c-' + id).value.trim();
        const choix_d   = document.getElementById('e-choix_d-' + id).value.trim();
        const reponse   = document.getElementById('e-reponse-' + id).value;
        const points    = document.getElementById('e-points-' + id).value;
        const explication = document.getElementById('e-explication-' + id).value.trim();

        if (!question || !choix_a || !choix_b || !reponse) {
            alert('Veuillez remplir la question, les choix A et B, et la bonne réponse.');
            return;
        }

        const fd = new FormData();
        fd.append('id', id);
        fd.append('question', question);
        fd.append('choix_a', choix_a);
        fd.append('choix_b', choix_b);
        fd.append('choix_c', choix_c);
        fd.append('choix_d', choix_d);
        fd.append('reponse_correcte', reponse);
        fd.append('points', points);
        fd.append('explication', explication);

        fetch('/php/quiz/update_question.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert(data.error || 'Erreur'); return; }
                location.reload();
            })
            .catch(() => alert('Erreur réseau'));
    }
    </script>
</body>
</html>
