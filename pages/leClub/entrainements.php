<?php
require_once __DIR__ . '/../../php/auth.php';

$canEdit = is_logged_in() && has_any_role(['moderateur', 'admin']);

$slots  = [];
$saison = '2026-2027';

try {
    $pdo    = get_pdo();
    $slots  = $pdo->query("SELECT * FROM entrainements ORDER BY FIELD(jour,'lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'), ordre")->fetchAll();
    $cfg    = $pdo->query("SELECT cle, valeur FROM entrainements_config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $saison  = $cfg['saison']  ?? '2026-2027';
    $visible = (bool)(int)($cfg['visible'] ?? 1);
} catch (Exception $e) { $visible = true; }

$dayOrder  = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
$dayLabels = ['lundi'=>'Lundi','mardi'=>'Mardi','mercredi'=>'Mercredi','jeudi'=>'Jeudi','vendredi'=>'Vendredi','samedi'=>'Samedi','dimanche'=>'Dimanche'];

$byDay = array_fill_keys($dayOrder, []);
foreach ($slots as $s) $byDay[$s['jour']][] = $s;

// Année fin de saison pour le calculateur de catégories
$saisonParts = explode('-', $saison);
$endYear     = (int)($saisonParts[1] ?? 2027);

$colTotal = $canEdit ? 6 : 5;

function renderCell(string $text): string {
    if ($text === '') return '<span class="cell-empty">—</span>';
    $parts = explode("\n", $text, 2);
    $html  = '<span class="cell-team">' . htmlspecialchars($parts[0]) . '</span>';
    if (isset($parts[1]) && $parts[1] !== '') {
        $html .= '<span class="cell-coach">' . htmlspecialchars($parts[1]) . '</span>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning des entraînements - VBO</title>
    <link href="/css/styles.css" rel="stylesheet">
    <link href="/css/leClub/entrainements.css" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/x-icon">
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
                <h1>Planning des entraînements</h1>
                <p>Retrouvez les créneaux de la saison selon la catégorie et le lieu.</p>
            </div>
        </div>
    </div>

    <main class="entr-main">
        <div class="entr-layout">

            <!-- ── Tableau ── -->
            <section class="table-section">
                <div class="table-section-header">
                    <div class="table-title-wrap">
                        <h2>Saison <?= htmlspecialchars($saison) ?></h2>
                        <?php if ($canEdit): ?>
                        <button class="btn-edit-inline" onclick="openSaisonModal()" title="Modifier la saison">✏️</button>
                        <?php endif; ?>
                    </div>
                    <div class="header-right-group">
                        <?php if ($canEdit): ?>
                        <button class="btn-toggle-visibility <?= $visible ? 'is-visible' : 'is-hidden' ?>"
                            id="btn-toggle-visibility"
                            onclick="toggleTableVisibility()"
                            title="<?= $visible ? 'Masquer aux visiteurs' : 'Rendre visible aux visiteurs' ?>">
                            <span class="toggle-dot"></span>
                            <span class="toggle-label" id="toggle-label"><?= $visible ? 'Visible' : 'Masqué' ?></span>
                        </button>
                        <?php endif; ?>
                        <button class="btn-print" onclick="window.print()" title="Imprimer">🖨 Imprimer</button>
                    </div>
                </div>

                <?php if ($canEdit && !$visible): ?>
                <div class="table-hidden-banner" id="table-hidden-banner">
                    ⚠ Tableau masqué pour les visiteurs — seuls les modérateurs et admins peuvent le voir
                </div>
                <?php endif; ?>

                <?php if (!$visible && !$canEdit): ?>
                <div class="table-placeholder">
                    <span class="placeholder-icon">📋</span>
                    <p class="placeholder-title">Planning en cours d'élaboration</p>
                    <p class="placeholder-text">Le planning officiel des entraînements de la saison <?= htmlspecialchars($saison) ?> n'est pas encore établi.<br>Il sera disponible ici dès qu'il sera confirmé.</p>
                    <a href="/pages/nousContacter.html" class="btn-cat-contact">Nous contacter pour plus d'informations →</a>
                </div>
                <?php else: ?>
                <div class="table-scroll-wrap" id="table-scroll-wrap">
                    <table class="entr-table" id="entr-table">
                        <thead>
                            <tr>
                                <th scope="col">Jour</th>
                                <th scope="col">Horaire / Lieu</th>
                                <th scope="col">Terrain 1</th>
                                <th scope="col">Terrain 2</th>
                                <th scope="col">Terrain 3</th>
                                <?php if ($canEdit): ?><th scope="col" class="no-print actions-th">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="entr-tbody">
                        <?php foreach ($dayOrder as $jour):
                            $daySlots = $byDay[$jour] ?? [];
                            if (empty($daySlots) && !$canEdit) continue;
                        ?>
                            <?php if (!empty($daySlots)):
                                $rowspan = count($daySlots);
                                foreach ($daySlots as $idx => $s):
                            ?>
                            <tr class="row-<?= $jour ?>" data-jour="<?= $jour ?>" data-id="<?= $s['id'] ?>">
                                <?php if ($idx === 0): ?>
                                <td class="day-cell" rowspan="<?= $rowspan ?>"><?= $dayLabels[$jour] ?></td>
                                <?php endif; ?>
                                <td class="horaire-cell">
                                    <span class="horaire-time"><?= htmlspecialchars($s['horaire']) ?></span>
                                    <span class="horaire-lieu"><?= htmlspecialchars($s['lieu']) ?></span>
                                </td>
                                <?php switch ($s['colspan']):
                                    case '111': ?>
                                <td><?= renderCell($s['t1']) ?></td>
                                <td><?= renderCell($s['t2']) ?></td>
                                <td><?= renderCell($s['t3']) ?></td>
                                    <?php break; case '21': ?>
                                <td colspan="2"><?= renderCell($s['t1']) ?></td>
                                <td><?= renderCell($s['t2']) ?></td>
                                    <?php break; case '12': ?>
                                <td><?= renderCell($s['t1']) ?></td>
                                <td colspan="2"><?= renderCell($s['t2']) ?></td>
                                    <?php break; case '3': ?>
                                <td colspan="3"><?= renderCell($s['t1']) ?></td>
                                    <?php break; endswitch; ?>
                                <?php if ($canEdit): ?>
                                <td class="actions-cell no-print">
                                    <button class="btn-row-edit" onclick="openEditModal(this.closest('tr'))" title="Modifier">✏️</button>
                                    <button class="btn-row-delete" onclick="confirmRowDelete(this.closest('tr'))" title="Supprimer">🗑</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; // slots

                            if ($canEdit): ?>
                            <tr class="add-row-tr no-print" data-jour="<?= $jour ?>">
                                <td colspan="<?= $colTotal - 1 ?>"></td>
                                <td class="add-row-td">
                                    <button class="btn-add-slot" onclick="openAddModal('<?= $jour ?>')">＋ Ajouter</button>
                                </td>
                            </tr>
                            <?php endif;

                            elseif ($canEdit): ?>
                            <tr class="row-<?= $jour ?> no-print">
                                <td class="day-cell"><?= $dayLabels[$jour] ?></td>
                                <td colspan="<?= $colTotal - 1 ?>" class="day-empty">
                                    <button class="btn-add-slot" onclick="openAddModal('<?= $jour ?>')">＋ Ajouter un créneau</button>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($jour !== 'dimanche'): ?>
                            <tr class="day-sep no-print-hide"><td colspan="<?= $colTotal ?>"></td></tr>
                            <?php endif; ?>

                        <?php endforeach; // days ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; // visible || canEdit ?>
            </section><!-- /table-section -->

            <!-- ── Calculateur de catégorie ── -->
            <aside class="category-aside">
                <div class="category-card">
                    <h2>Ma catégorie</h2>
                    <p class="cat-saison">Saison <?= htmlspecialchars($saison) ?></p>
                    <form id="cat-form">
                        <div class="cat-field">
                            <label for="birth-year">Année de naissance</label>
                            <select id="birth-year" name="birth-year" required>
                                <option value="" disabled selected>Choisir…</option>
                                <?php for ($y = $endYear - 2; $y >= $endYear - 22; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?><?= $y === $endYear - 22 ? ' ou avant' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="cat-field">
                            <label for="gender">Genre</label>
                            <select id="gender" name="gender" required>
                                <option value="" disabled selected>Choisir…</option>
                                <option value="female">Féminin</option>
                                <option value="male">Masculin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-cat-submit">Déterminer</button>
                    </form>
                    <div id="cat-result" class="cat-result" style="display:none"></div>
                    <a href="/pages/nousContacter.html" class="btn-cat-contact" id="cat-contact" style="display:none">Nous contacter →</a>
                </div>
            </aside>

        </div><!-- /entr-layout -->
    </main>

    <?php if ($canEdit): ?>
    <!-- ── Modale ajout / édition ── -->
    <div id="slotModal" class="entr-modal">
        <div class="entr-modal-content">
            <div class="entr-modal-header">
                <h3 id="slot-modal-title">Ajouter un créneau</h3>
                <span class="close" onclick="closeSlotModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="entr-modal-body">
                <form id="slot-form">
                    <input type="hidden" name="id" id="slot-id">
                    <div class="slot-form-grid">
                        <div class="modal-form-group">
                            <label for="slot-jour">Jour</label>
                            <select name="jour" id="slot-jour" required>
                                <?php foreach ($dayLabels as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="slot-horaire">Horaire</label>
                            <input type="text" name="horaire" id="slot-horaire" placeholder="18h00 - 20h00" required>
                        </div>
                        <div class="modal-form-group">
                            <label for="slot-lieu">Lieu</label>
                            <input type="text" name="lieu" id="slot-lieu" placeholder="Piemontesi">
                        </div>
                    </div>
                    <input type="hidden" name="colspan" id="slot-colspan" value="111">
                    <div class="terrain-checkboxes">
                        <div class="terrain-cb-group">
                            <label class="terrain-cb-header">
                                <input type="checkbox" id="cb-t1" onchange="toggleTerrain('t1')" checked>
                                <span>Terrain 1</span>
                            </label>
                            <textarea name="t1" id="slot-t1" rows="2" placeholder="Équipe&#10;Coach"></textarea>
                        </div>
                        <div class="terrain-cb-group">
                            <label class="terrain-cb-header">
                                <input type="checkbox" id="cb-t2" onchange="toggleTerrain('t2')" checked>
                                <span>Terrain 2</span>
                            </label>
                            <textarea name="t2" id="slot-t2" rows="2" placeholder="Équipe&#10;Coach"></textarea>
                        </div>
                        <div class="terrain-cb-group">
                            <label class="terrain-cb-header">
                                <input type="checkbox" id="cb-t3" onchange="toggleTerrain('t3')" checked>
                                <span>Terrain 3</span>
                            </label>
                            <textarea name="t3" id="slot-t3" rows="2" placeholder="Équipe&#10;Coach"></textarea>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeSlotModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="slot-status"></p>
                    <div id="slot-delete-zone" class="modal-delete-zone" style="display:none">
                        <button type="button" class="btn-delete-slot" id="btn-delete-slot" onclick="confirmDeleteSlot()">🗑 Supprimer ce créneau</button>
                        <div id="slot-delete-confirm" class="delete-confirm" style="display:none">
                            <span>Confirmer la suppression ?</span>
                            <button type="button" class="btn-delete-confirm" onclick="deleteSlot()">Oui</button>
                            <button type="button" class="btn-cancel-delete" onclick="cancelDeleteSlot()">Annuler</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modale saison ── -->
    <div id="saisonModal" class="entr-modal">
        <div class="entr-modal-content">
            <div class="entr-modal-header">
                <h3>Saison en cours</h3>
                <span class="close" onclick="closeSaisonModal()" role="button" tabindex="0" aria-label="Fermer">&times;</span>
            </div>
            <div class="entr-modal-body">
                <form id="saison-form">
                    <div class="modal-form-group">
                        <label for="saison-input">Saison (ex. 2026-2027)</label>
                        <input type="text" name="valeur" id="saison-input"
                            value="<?= htmlspecialchars($saison, ENT_QUOTES) ?>"
                            pattern="\d{4}-\d{4}" placeholder="AAAA-AAAA" required>
                        <input type="hidden" name="cle" value="saison">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save">Enregistrer</button>
                        <button type="button" class="btn-cancel-modal" onclick="closeSaisonModal()">Annuler</button>
                    </div>
                    <p class="modal-status" id="saison-status"></p>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="footer"></div>

    <script>
        const END_YEAR    = <?= $endYear ?>;
        const CAN_EDIT    = <?= $canEdit ? 'true' : 'false' ?>;
        let   TABLE_VISIBLE = <?= $visible ? 'true' : 'false' ?>;
    </script>
    <script src="/js/main.js"></script>
    <script src="/js/entrainements.js"></script>
</body>
</html>
