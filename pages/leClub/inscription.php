<?php
require_once __DIR__ . '/../../php/auth.php';

$canEdit = is_logged_in() && has_any_role(['moderateur', 'admin']);

// Defaults
$saison  = '2026-2027';
$tarifs  = [
    'jeunes' => [
        ['value' => 'M7',      'label' => 'M7 – Baby Volley', 'prix' => '140 € + 47 € (maillot & short)', 'cheques' => '2 chèques'],
        ['value' => 'M9-M15',  'label' => 'M9 à M15',         'prix' => '160 € + 47 € (maillot & short)', 'cheques' => '2 chèques'],
        ['value' => 'M18-M21', 'label' => 'M18 et M21',       'prix' => '190 € + 47 € (maillot & short)', 'cheques' => '2 chèques'],
    ],
    'seniors' => [
        ['value' => 'Seniors',        'label' => 'Seniors',        'prix' => '200 € + 47 € (maillot & short)', 'cheques' => '2 chèques'],
        ['value' => 'Seniors Loisirs','label' => 'Seniors Loisirs','prix' => '100 € + 47 € (maillot & short)', 'cheques' => 'Maillot non obligatoire'],
        ['value' => 'Compet Lib',     'label' => 'Compet Lib',     'prix' => '130 € + 47 € (maillot & short)', 'cheques' => 'Maillot non obligatoire'],
    ],
];

try {
    $pdo = get_pdo();
    $row = $pdo->query("SELECT cle, valeur FROM parametres WHERE cle IN ('inscription_saison','inscription_tarifs')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($row['inscription_saison'])) $saison = $row['inscription_saison'];
    if (!empty($row['inscription_tarifs'])) {
        $decoded = json_decode($row['inscription_tarifs'], true);
        if ($decoded) $tarifs = $decoded;
    }
} catch (Exception $e) {}

// Calcul automatique des années depuis l'année de fin de saison
$parts   = explode('-', $saison);
$endYear = (int)($parts[1] ?? 2027);
$years   = [
    'M7_debut'    => $endYear - 7,
    'M7_fin'      => $endYear - 6,
    'M9_debut'    => $endYear - 15,
    'M9_fin'      => $endYear - 8,
    'M18_debut'   => $endYear - 21,
    'M18_fin'     => $endYear - 16,
    'seniors_max' => $endYear - 22,
];

$annees = [
    'M7'      => 'Né(e) en ' . $years['M7_debut'] . ', ' . $years['M7_fin'] . ' et après',
    'M9-M15'  => 'Né(e) entre ' . $years['M9_debut'] . ' et ' . $years['M9_fin'],
    'M18-M21' => 'Né(e) entre ' . $years['M18_debut'] . ' et ' . $years['M18_fin'],
    'Seniors' => 'Né(e) en ' . $years['seniors_max'] . ' et avant',
];

// Saison précédente pour les "pièces à fournir"
$startYear  = (int)($parts[0] ?? 2026);
$saisonPrev = ($startYear - 1) . '/' . ($endYear - 1);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche de renseignements - VBO</title>
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/leClub/inscription.css?v=20260626" rel="stylesheet">
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        window.INSCRIPTION_SAISON      = <?= json_encode($saison) ?>;
        window.INSCRIPTION_SAISON_PREV = <?= json_encode($saisonPrev) ?>;
    </script>
</head>

<body>
    <div id="menu"></div>

    <div id="content">

        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo VBO">
            <div class="text-content">
                <h1>Fiche de renseignements<br>
                    Saison <?= h($saison) ?></h1>
                <p>Remplissez votre fiche en ligne et téléchargez-la en PDF, prête à remettre au club.</p>
            </div>
        </div>

        <!-- ── Onglets de navigation ── -->
        <div class="inscription-tabs">
            <div class="inscription-toggle">
                <div class="inscription-tab active" id="tab-jeunes" onclick="switchTab('jeunes')">
                    <span class="toggle-icon">&#x1F3D0;</span>
                    <span class="toggle-title">Jeunes</span>
                    <span class="toggle-sub">M7 &middot; M9&#8209;M15 &middot; M18&#8209;M21</span>
                </div>
                <div class="inscription-tab" id="tab-seniors" onclick="switchTab('seniors')">
                    <span class="toggle-icon">&#x1F3D0;</span>
                    <span class="toggle-title">Seniors</span>
                    <span class="toggle-sub">Seniors &middot; Loisirs &middot; Compet&nbsp;Lib</span>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════ -->
        <!--               FORMULAIRE JEUNES             -->
        <!-- ════════════════════════════════════════════ -->
        <div class="inscription-main">
            <div id="form-jeunes" class="form-panel active">

                <!-- Catégorie -->
                <div class="form-section">
                    <div class="form-section-title">Catégorie</div>
                    <div class="form-section-body">
                        <div class="tarifs-grid">
                            <?php foreach ($tarifs['jeunes'] as $t):
                                $ann = $annees[$t['value']] ?? '';
                                $idRadio = 'j_' . strtolower(str_replace(['-','–',' '], '_', $t['value']));
                            ?>
                            <div class="tarif-card" onclick="selectTarif('j', this)">
                                <div class="tarif-header">
                                    <input type="radio" name="catJ" id="<?= h($idRadio) ?>" value="<?= h($t['value']) ?>">
                                    <label for="<?= h($idRadio) ?>"><?= h($t['label']) ?></label>
                                </div>
                                <div class="tarif-body">
                                    <?php if ($ann): ?><div class="annees"><?= h($ann) ?></div><?php endif; ?>
                                    <div class="prix"><?= h($t['prix']) ?></div>
                                    <div class="cheques"><?= h($t['cheques']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Identité licencié -->
                <div class="form-section">
                    <div class="form-section-title">Identité du licencié</div>
                    <div class="form-section-body">
                        <div class="fields-grid spacer">
                            <div class="field"><label>NOM</label><input id="j_nom" type="text" placeholder="Nom de famille"></div>
                            <div class="field"><label>Prénom</label><input id="j_prenom" type="text" placeholder="Prénom"></div>
                            <div class="field"><label>Date de naissance</label><input id="j_ddn" type="date"></div>
                            <div class="field"><label>Nationalité</label><input id="j_nat" type="text" placeholder="Ex : Française"></div>
                            <div class="field"><label>Téléphone</label><input id="j_tel" type="tel" placeholder="06 xx xx xx xx"></div>
                            <div class="field"><label>Email</label><input id="j_email" type="email" placeholder="email@exemple.fr"></div>
                        </div>
                        <div class="fields-grid cols-1 spacer">
                            <div class="field"><label>Adresse</label><input id="j_adresse" type="text" placeholder="Numéro et nom de rue"></div>
                        </div>
                        <div class="fields-grid cols-4">
                            <div class="field"><label>Code postal</label><input id="j_cp" type="text" placeholder="83190" maxlength="5"></div>
                            <div class="field"><label>Ville</label><input id="j_ville" type="text" placeholder="Ollioules"></div>
                            <div class="field"><label>Taille maillot</label>
                                <select id="j_tshirt">
                                    <option value="">—</option>
                                    <option>XS</option><option>S</option><option>M</option><option>L</option>
                                    <option>XL</option><option>XXL</option><option>XXXL</option><option>XXXXL</option>
                                </select>
                            </div>
                            <div class="field"><label>Taille short</label>
                                <select id="j_short">
                                    <option value="">—</option>
                                    <option>XS</option><option>S</option><option>M</option><option>L</option>
                                    <option>XL</option><option>XXL</option><option>XXXL</option><option>XXXXL</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Responsable légal -->
                <div class="form-section">
                    <div class="form-section-title">Responsable légal</div>
                    <div class="form-section-body">
                        <div class="fields-grid cols-1 spacer">
                            <div class="field"><label>Nom et prénom</label><input id="j_rl_nom" type="text" placeholder="Nom et prénom du responsable légal"></div>
                        </div>
                        <p class="parent-note">Au moins un parent doit être renseigné</p>
                        <div class="parent-block">
                            <div class="parent-block-title">Père</div>
                            <div class="fields-grid">
                                <div class="field"><label>Téléphone</label><input id="j_tel_pere" type="tel" placeholder="06 xx xx xx xx"></div>
                                <div class="field"><label>Email</label><input id="j_email_pere" type="email" placeholder="email@exemple.fr"></div>
                            </div>
                        </div>
                        <div class="parent-block">
                            <div class="parent-block-title">Mère</div>
                            <div class="fields-grid">
                                <div class="field"><label>Téléphone</label><input id="j_tel_mere" type="tel" placeholder="06 xx xx xx xx"></div>
                                <div class="field"><label>Email</label><input id="j_email_mere" type="email" placeholder="email@exemple.fr"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pièces à fournir -->
                <div class="form-section">
                    <div class="form-section-title">Pièces à fournir</div>
                    <div class="form-section-body">
                        <div class="pieces-grid">
                            <div class="pieces-col">
                                <h4>Licencié saison <?= h($saisonPrev) ?> au VBO</h4>
                                <ul>
                                    <li>La présente fiche de renseignement</li>
                                    <li>Cotisation par chèque (échelonnable) + chèque 47 € maillot à l'ordre de : Volley Ball Ollioulais ou paiement HelloAsso</li>
                                    <li>Le formulaire de demande de licence complété</li>
                                    <li>Certificat médical si QS non OK</li>
                                </ul>
                            </div>
                            <div class="pieces-col">
                                <h4>Nouveau licencié</h4>
                                <ul>
                                    <li>La présente fiche de renseignement</li>
                                    <li>Cotisation par chèque (échelonnable) + chèque 47 € maillot à l'ordre de : Volley Ball Ollioulais ou paiement HelloAsso</li>
                                    <li>Le formulaire de demande de licence complété</li>
                                    <li>Certificat médical si QS non OK</li>
                                    <li>1 photo d'identité</li>
                                    <li>Photocopie carte d'identité ou livret famille (page du licencié)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Autorisations -->
                <div class="form-section">
                    <div class="form-section-title">Autorisations</div>
                    <div class="form-section-body">
                        <div class="checks">
                            <label class="check-item"><input type="checkbox" id="j_aut1"> J'autorise mon enfant à participer aux entraînements, matchs et déplacements organisés par le club.</label>
                            <label class="check-item"><input type="checkbox" id="j_aut2"> J'autorise le club à prendre toute disposition médicale en cas d'accident.</label>
                            <label class="check-item"><input type="checkbox" id="j_aut3"> J'autorise l'utilisation de l'image de mon enfant dans le cadre des activités du club, sauf opposition écrite.</label>
                            <label class="check-item"><input type="checkbox" id="j_aut4"> J'autorise l'utilisation des données personnelles de mon enfant pour la gestion interne du club.</label>
                        </div>
                    </div>
                </div>

                <!-- Engagement & Signature -->
                <div class="form-section">
                    <div class="form-section-title">Engagement &amp; Signature</div>
                    <div class="form-section-body">
                        <div class="checks" style="margin-bottom: 18px;">
                            <label class="check-item">
                                <input type="checkbox" id="j_eng">
                                Je reconnais avoir pris connaissance des statuts et du règlement intérieur du Volley Ball Ollioulais et les accepter sans réserve. Je comprends que l'adhésion est soumise à l'agrément du comité directeur.
                            </label>
                        </div>
                        <div class="fields-grid spacer">
                            <div class="field"><label>Fait à</label><input id="j_fait_a" type="text" placeholder="Ville"></div>
                            <div class="field"><label>Le</label><input id="j_date_sign" type="date"></div>
                        </div>
                        <div class="field">
                            <label>Signature du représentant légal</label>
                            <div class="sign-area">
                                <canvas id="j_canvas" height="130"></canvas>
                                <div class="sign-hint" id="j_hint">Signez ici avec votre doigt ou votre souris</div>
                            </div>
                            <div class="sign-controls">
                                <button class="btn-clear" onclick="clearCanvas('j')">&#x2715; Effacer</button>
                                <span class="sign-info">La signature sera intégrée dans le PDF</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn-pdf" onclick="generatePDF('jeunes')">&#11015; Télécharger ma fiche Jeunes en PDF</button>

            </div><!-- /form-jeunes -->


            <!-- ════════════════════════════════════════════ -->
            <!--              FORMULAIRE SENIORS             -->
            <!-- ════════════════════════════════════════════ -->
            <div id="form-seniors" class="form-panel">

                <!-- Catégorie -->
                <div class="form-section">
                    <div class="form-section-title">Catégorie</div>
                    <div class="form-section-body">
                        <div class="tarifs-grid">
                            <?php foreach ($tarifs['seniors'] as $i => $t):
                                $idRadio = 's_' . strtolower(str_replace([' ', '-'], '_', $t['value']));
                                $ann = ($t['value'] === 'Seniors') ? $annees['Seniors'] : '';
                            ?>
                            <div class="tarif-card" onclick="selectTarif('s', this)">
                                <div class="tarif-header">
                                    <input type="radio" name="catS" id="<?= h($idRadio) ?>" value="<?= h($t['value']) ?>">
                                    <label for="<?= h($idRadio) ?>"><?= h($t['label']) ?></label>
                                </div>
                                <div class="tarif-body">
                                    <?php if ($ann): ?><div class="annees"><?= h($ann) ?></div><?php endif; ?>
                                    <div class="prix"><?= h($t['prix']) ?></div>
                                    <div class="cheques"><?= h($t['cheques']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Identité -->
                <div class="form-section">
                    <div class="form-section-title">Identité du licencié</div>
                    <div class="form-section-body">
                        <div class="fields-grid spacer">
                            <div class="field"><label>NOM</label><input id="s_nom" type="text" placeholder="Nom de famille"></div>
                            <div class="field"><label>Prénom</label><input id="s_prenom" type="text" placeholder="Prénom"></div>
                            <div class="field"><label>Date de naissance</label><input id="s_ddn" type="date"></div>
                            <div class="field"><label>Nationalité</label><input id="s_nat" type="text" placeholder="Ex : Française"></div>
                            <div class="field"><label>Téléphone</label><input id="s_tel" type="tel" placeholder="06 xx xx xx xx"></div>
                            <div class="field"><label>Email</label><input id="s_email" type="email" placeholder="email@exemple.fr"></div>
                        </div>
                        <div class="fields-grid cols-1 spacer">
                            <div class="field"><label>Adresse</label><input id="s_adresse" type="text" placeholder="Numéro et nom de rue"></div>
                        </div>
                        <div class="fields-grid cols-4 spacer">
                            <div class="field"><label>Code postal</label><input id="s_cp" type="text" placeholder="83190" maxlength="5"></div>
                            <div class="field"><label>Ville</label><input id="s_ville" type="text" placeholder="Ollioules"></div>
                            <div class="field"><label>Taille maillot</label>
                                <select id="s_tshirt">
                                    <option value="">—</option>
                                    <option>XS</option><option>S</option><option>M</option><option>L</option>
                                    <option>XL</option><option>XXL</option><option>XXXL</option><option>XXXXL</option>
                                </select>
                            </div>
                            <div class="field"><label>Taille short</label>
                                <select id="s_short">
                                    <option value="">—</option>
                                    <option>XS</option><option>S</option><option>M</option><option>L</option>
                                    <option>XL</option><option>XXL</option><option>XXXL</option><option>XXXXL</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0;">
                            <div class="oui-non-row">
                                <span class="q">Je suis intéressé(e) par une formation d'arbitre ou marqueur</span>
                                <div class="oui-non">
                                    <label><input type="radio" name="s_arb" value="OUI"> OUI</label>
                                    <label><input type="radio" name="s_arb" value="NON" checked> NON</label>
                                </div>
                            </div>
                            <div class="oui-non-row">
                                <span class="q">Je suis intéressé(e) par une formation d'entraîneur</span>
                                <div class="oui-non">
                                    <label><input type="radio" name="s_ent" value="OUI"> OUI</label>
                                    <label><input type="radio" name="s_ent" value="NON" checked> NON</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pièces à fournir -->
                <div class="form-section">
                    <div class="form-section-title">Pièces à fournir</div>
                    <div class="form-section-body">
                        <div class="pieces-grid">
                            <div class="pieces-col">
                                <h4>Licencié saison <?= h($saisonPrev) ?> au VBO</h4>
                                <ul>
                                    <li>La présente fiche de renseignement</li>
                                    <li>Cotisation par chèque (échelonnable) + chèque 47 € maillot à l'ordre de : Volley Ball Ollioulais ou paiement HelloAsso</li>
                                    <li>Le formulaire de demande de licence complété</li>
                                    <li>Certificat médical si QS non OK</li>
                                </ul>
                            </div>
                            <div class="pieces-col">
                                <h4>Nouveau licencié</h4>
                                <ul>
                                    <li>La présente fiche de renseignement</li>
                                    <li>Cotisation par chèque (échelonnable) + chèque 47 € maillot à l'ordre de : Volley Ball Ollioulais ou paiement HelloAsso</li>
                                    <li>Le formulaire de demande de licence complété</li>
                                    <li>Certificat médical si QS non OK</li>
                                    <li>1 photo d'identité</li>
                                    <li>Photocopie de la carte d'identité</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Engagement & Signature -->
                <div class="form-section">
                    <div class="form-section-title">Engagement &amp; Signature</div>
                    <div class="form-section-body">
                        <div class="checks" style="margin-bottom: 18px;">
                            <label class="check-item"><input type="checkbox" id="s_eng1"> Je reconnais avoir pris connaissance des statuts et du règlement intérieur du Volley Ball Ollioulais et les accepter sans réserve.</label>
                            <label class="check-item"><input type="checkbox" id="s_eng2"> J'autorise le club à utiliser mes données personnelles pour la gestion interne et les communications officielles.</label>
                            <label class="check-item"><input type="checkbox" id="s_eng3"> J'autorise l'utilisation de mon image sur les supports de communication du club.</label>
                            <label class="check-item"><input type="checkbox" id="s_eng4"> Je comprends que l'adhésion est soumise à l'agrément du comité directeur.</label>
                        </div>
                        <div class="fields-grid spacer">
                            <div class="field"><label>Fait à</label><input id="s_fait_a" type="text" placeholder="Ville"></div>
                            <div class="field"><label>Le</label><input id="s_date_sign" type="date"></div>
                        </div>
                        <div class="field">
                            <label>Signature du licencié</label>
                            <div class="sign-area">
                                <canvas id="s_canvas" height="130"></canvas>
                                <div class="sign-hint" id="s_hint">Signez ici avec votre doigt ou votre souris</div>
                            </div>
                            <div class="sign-controls">
                                <button class="btn-clear" onclick="clearCanvas('s')">&#x2715; Effacer</button>
                                <span class="sign-info">La signature sera intégrée dans le PDF</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn-pdf" onclick="generatePDF('seniors')">&#11015; Télécharger ma fiche Seniors en PDF</button>

            </div><!-- /form-seniors -->
        </div><!-- /inscription-main -->

    </div><!-- /content -->

    <?php if ($canEdit): ?>
    <!-- ── Bouton flottant + panneau d'édition ── -->
    <button id="insc-edit-fab" onclick="inscOpenEdit()" title="Modifier la saison et les tarifs">⚙ Modifier</button>

    <div id="insc-edit-overlay" onclick="inscCloseEdit(event)">
        <div id="insc-edit-panel">
            <div class="insc-edit-header">
                <h2>Saison &amp; Tarifs</h2>
                <button class="insc-edit-close" onclick="inscCloseEdit(null, true)">&times;</button>
            </div>
            <div class="insc-edit-body">
                <div id="insc-edit-msg" class="insc-msg" style="display:none"></div>

                <div class="insc-edit-section">
                    <label class="insc-edit-label">Saison</label>
                    <input type="text" id="ie_saison" class="insc-edit-input"
                            value="<?= h($saison) ?>" placeholder="2026-2027" pattern="\d{4}-\d{4}">
                    <small class="insc-edit-hint">Les dates de naissance des catégories sont recalculées automatiquement.</small>
                </div>

                <div class="insc-edit-section">
                    <div class="insc-edit-section-head">
                        <div class="insc-edit-group-title">Tarifs Jeunes</div>
                        <button type="button" class="ie-add-btn" onclick="addInscCard('jeunes')">+ Ajouter</button>
                    </div>
                    <div id="ie-jeunes-rows"></div>
                </div>

                <div class="insc-edit-section">
                    <div class="insc-edit-section-head">
                        <div class="insc-edit-group-title">Tarifs Seniors</div>
                        <button type="button" class="ie-add-btn" onclick="addInscCard('seniors')">+ Ajouter</button>
                    </div>
                    <div id="ie-seniors-rows"></div>
                </div>
            </div>
            <div class="insc-edit-footer">
                <button class="insc-btn-cancel" onclick="inscCloseEdit(null, true)">Annuler</button>
                <button class="insc-btn-save" onclick="inscSaveConfig()">Enregistrer</button>
            </div>
        </div>
    </div>

    <script>
    function inscOpenEdit() {
        ['jeunes', 'seniors'].forEach(group => {
            const container = document.getElementById('ie-' + group + '-rows');
            container.innerHTML = '';
            document.querySelectorAll('#form-' + group + ' .tarif-card').forEach(card => {
                addInscCard(group,
                    card.querySelector('label')?.textContent.trim() || '',
                    card.querySelector('.prix')?.textContent.trim()    || '',
                    card.querySelector('.cheques')?.textContent.trim() || ''
                );
            });
        });
        document.getElementById('insc-edit-overlay').classList.add('open');
    }
    function inscCloseEdit(e, force) {
        if (!force && e && e.target !== document.getElementById('insc-edit-overlay')) return;
        document.getElementById('insc-edit-overlay').classList.remove('open');
    }
    function addInscCard(group, label, prix, cheques) {
        const container = document.getElementById('ie-' + group + '-rows');
        const div = document.createElement('div');
        div.className = 'insc-edit-card';
        div.innerHTML =
            '<div class="insc-edit-card-head">' +
                '<input type="text" class="ie-label-input insc-edit-input" placeholder="Libellé (ex : M9 à M15)">' +
                '<button type="button" class="ie-delete-btn" title="Supprimer">✕</button>' +
            '</div>' +
            '<div class="insc-edit-row">' +
                '<label>Prix</label>' +
                '<input type="text" class="ie-prix-input insc-edit-input" placeholder="Ex : 160 € + 47 €">' +
            '</div>' +
            '<div class="insc-edit-row">' +
                '<label>Infos paiement</label>' +
                '<input type="text" class="ie-cheques-input insc-edit-input" placeholder="Ex : 2 chèques">' +
            '</div>';
        div.querySelector('.ie-label-input').value   = label   || '';
        div.querySelector('.ie-prix-input').value    = prix    || '';
        div.querySelector('.ie-cheques-input').value = cheques || '';
        div.querySelector('.ie-delete-btn').addEventListener('click', () => div.remove());
        container.appendChild(div);
    }
    async function inscSaveConfig() {
        const msg    = document.getElementById('insc-edit-msg');
        msg.style.display = 'none';
        const tarifs = { jeunes: [], seniors: [] };
        ['jeunes', 'seniors'].forEach(group => {
            document.querySelectorAll('#ie-' + group + '-rows .insc-edit-card').forEach(card => {
                const label   = card.querySelector('.ie-label-input')?.value.trim()   || '';
                const prix    = card.querySelector('.ie-prix-input')?.value.trim()    || '';
                const cheques = card.querySelector('.ie-cheques-input')?.value.trim() || '';
                if (label) tarifs[group].push({ value: label, label, prix, cheques });
            });
        });
        const fd = new FormData();
        fd.append('saison',      document.getElementById('ie_saison').value.trim());
        fd.append('tarifs_json', JSON.stringify(tarifs));
        try {
            const res  = await fetch('/php/inscription/save_config.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                msg.textContent = 'Enregistré. Rechargement…';
                msg.className = 'insc-msg insc-msg--ok';
                msg.style.display = 'block';
                setTimeout(() => location.reload(), 900);
            } else {
                msg.textContent = data.error || 'Erreur.';
                msg.className = 'insc-msg insc-msg--err';
                msg.style.display = 'block';
            }
        } catch {
            msg.textContent = 'Erreur réseau.';
            msg.className = 'insc-msg insc-msg--err';
            msg.style.display = 'block';
        }
    }
    </script>
    <?php endif; ?>

    <div id="footer"></div>

    <script src="/js/main.js?v=20260705"></script>
    <script src="/js/inscription.js?v=4"></script>
    <script>
        document.dispatchEvent(new Event("inscriptionLoaded"));
    </script>
</body>
</html>
