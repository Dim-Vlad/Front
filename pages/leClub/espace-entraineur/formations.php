<?php
require_once __DIR__ . '/../../../php/auth.php';
require_login();
if (!has_any_role(['entraineur', 'arbitre', 'bureau', 'moderateur', 'admin'])) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$isMod = has_any_role(['admin', 'moderateur']);
$pdo   = get_pdo();

function h(string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$formations = [];
try {
    $formations = $pdo->query("SELECT * FROM formations ORDER BY ordre ASC, id ASC")->fetchAll();
} catch (Exception) {}

$categorieLabels = [
    'entraineur' => 'Entraîneur',
    'arbitre'    => 'Arbitre',
    'marqueur'   => 'Marqueur',
    'joueurs'    => 'Joueurs',
    'detection'  => 'Détection',
];
$typeIcons = [
    'pdf'   => '📄',
    'word'  => '📝',
    'excel' => '📊',
    'lien'  => '🔗',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur.css?v=20260729" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur/formations.css?v=20260732" rel="stylesheet">
    <title>Formations - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Formations</h1>
                <p class="explication">Ressources de formation par public : entraîneurs, arbitres, marqueurs, joueurs et détection.</p>
            </div>
        </div>
    </div>

    <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    <main class="ent-main">
        <?php if ($isMod): ?>
        <div class="ps-formations-toolbar">
            <button type="button" class="btn-save" onclick="openFormationModal()">＋ Ajouter une formation</button>
        </div>
        <?php endif; ?>

        <div class="ps-formations-grid" id="ps-formations-grid">
            <?php foreach ($formations as $f): ?>
            <div class="ps-formation-card" data-id="<?= (int)$f['id'] ?>">
                <span class="ps-formation-type ps-cat-<?= h($f['categorie']) ?>"><?= h($categorieLabels[$f['categorie']] ?? $f['categorie']) ?></span>
                <h3><?= $f['url'] !== '' ? ($typeIcons[$f['type']] ?? '') . ' ' : '' ?><?= h($f['label']) ?></h3>
                <?php if ($f['description']): ?><p><?= h($f['description']) ?></p><?php endif; ?>
                <div class="ps-formation-actions">
                    <?php if ($f['url'] === ''): ?>
                    <span></span>
                    <?php elseif ($f['type'] === 'pdf'): ?>
                    <button type="button" class="ps-formation-link" onclick="openPdfModal('<?= h($f['url']) ?>','<?= h($f['label']) ?>')">Consulter →</button>
                    <?php elseif (in_array($f['type'], ['word', 'excel'], true)): ?>
                    <a class="ps-formation-link" href="<?= h($f['url']) ?>" target="_blank" rel="noopener">Télécharger →</a>
                    <?php else: ?>
                    <a class="ps-formation-link" href="<?= h($f['url']) ?>" target="_blank" rel="noopener">Voir →</a>
                    <?php endif; ?>
                    <?php if ($isMod): ?>
                    <div class="ps-formation-mod-btns">
                        <button type="button" class="ps-equipe-edit-btn" onclick='openFormationModal(<?= json_encode($f, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Modifier">✏️</button>
                        <button type="button" class="ps-equipe-edit-btn" onclick='deleteFormation(<?= (int)$f['id'] ?>, <?= json_encode($f['label'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Supprimer">🗑</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($formations)): ?>
            <p class="ps-docs-empty">Aucune formation disponible pour le moment.</p>
            <?php endif; ?>
        </div>
    </main>

    <div id="pdf-modal-slot"></div>

    <?php if ($isMod): ?>
    <div id="formationModal" class="ent-modal" onclick="if(event.target===this)closeFormationModal()">
        <div class="ent-modal-content">
            <h2 class="ent-modal-title" id="formationModalTitle">Ajouter une formation</h2>
            <form id="formationForm" onsubmit="submitFormation(event)" enctype="multipart/form-data">
                <input type="hidden" id="f-id">
                <div class="form-group">
                    <label for="f-label">Titre</label>
                    <input type="text" id="f-label" required placeholder="Ex : Formation initiateur FFVB">
                </div>
                <div class="form-group">
                    <label for="f-description">Description</label>
                    <textarea id="f-description" rows="3" placeholder="Courte description (facultatif)"></textarea>
                </div>
                <div class="form-group">
                    <label for="f-categorie">Catégorie</label>
                    <select id="f-categorie" required>
                        <option value="entraineur">Entraîneur</option>
                        <option value="arbitre">Arbitre</option>
                        <option value="marqueur">Marqueur</option>
                        <option value="joueurs">Joueurs</option>
                        <option value="detection">Détection</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ressource</label>
                    <div class="ps-radio-group">
                        <label><input type="radio" name="f-kind" value="fichier" checked onchange="toggleFormationKind()"> Fichier</label>
                        <label><input type="radio" name="f-kind" value="lien" onchange="toggleFormationKind()"> Lien externe</label>
                    </div>
                </div>
                <div class="form-group" id="fDocGroup">
                    <label>Fichier (PDF, Word ou Excel)</label>
                    <div class="ps-upload-zone" onclick="document.getElementById('f-file').click()">
                        <div class="ps-upload-label" id="f-file-label">📁 Cliquer pour choisir un fichier</div>
                        <p class="ps-upload-hint">PDF, Word ou Excel</p>
                        <input type="file" id="f-file" accept=".pdf,.doc,.docx,.xls,.xlsx" onchange="updateFormationFileLabel()">
                    </div>
                    <small id="fKeepHint" style="display:none">Laissez vide pour conserver le fichier actuel.</small>
                </div>
                <div class="form-group" id="fUrlGroup" style="display:none">
                    <label for="f-url">Lien</label>
                    <input type="url" id="f-url" placeholder="https://…">
                </div>
                <p class="form-status" id="f-status"></p>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeFormationModal()">Annuler</button>
                    <button type="submit" class="btn-save">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div id="footer"></div>

    <?php if ($isMod): ?><script>const IS_MOD = true;</script><?php endif; ?>
    <script src="/js/pdf-modal.js?v=20260721"></script>
    <script src="/js/espace-entraineur/formations.js?v=20260728"></script>
    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
