document.addEventListener('DOMContentLoaded', () => {
    loadHTML('/commun/menu.html', 'menu');
    loadHTML('/commun/footer.php', 'footer');

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSlotModal(); closeSaisonModal(); }
    });

    document.getElementById('slotModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('slotModal')) closeSlotModal();
    });
    document.getElementById('saisonModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('saisonModal')) closeSaisonModal();
    });

    // Sélecteur de disposition des terrains
    document.getElementById('layout-picker')?.addEventListener('click', e => {
        const btn = e.target.closest('.layout-opt');
        if (!btn) return;
        setLayout(btn.dataset.colspan);
    });

    // Calculateur de catégorie
    document.getElementById('cat-form')?.addEventListener('submit', e => {
        e.preventDefault();
        const year   = parseInt(document.getElementById('birth-year').value);
        const gender = document.getElementById('gender').value;
        showCategory(year, gender);
    });

    // Formulaire créneau (add + edit)
    document.getElementById('slot-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('slot-status');
        status.textContent = 'Enregistrement…'; status.className = 'modal-status';

        // Synchroniser les champs visuels vers les hidden textareas
        const colspan = document.getElementById('slot-colspan').value;
        const vals = { t1: '', t2: '', t3: '' };
        document.querySelectorAll('#terrain-teams .team-visual-input').forEach(ta => {
            const target = ta.dataset.target;
            if (target in vals) vals[target] = ta.value;
        });
        document.getElementById('slot-t1').value = vals.t1;
        document.getElementById('slot-t2').value = vals.t2;
        document.getElementById('slot-t3').value = vals.t3;

        // Vider les champs non utilisés selon le layout
        if (colspan !== '111') document.getElementById('slot-t3').value = '';
        if (colspan === '3')   document.getElementById('slot-t2').value = '';

        const fd  = new FormData(e.target);
        const id  = fd.get('id');
        const url = id ? '/php/entrainements/update_entrainement.php' : '/php/entrainements/add_entrainement.php';
        try {
            const res  = await fetch(url, { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                if (id) {
                    updateRowInDOM(json.data);
                } else {
                    addRowToDOM(json.data);
                }
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeSlotModal, 800);
            } else {
                status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            }
        } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
    });

    // Formulaire saison
    document.getElementById('saison-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('saison-status');
        status.textContent = 'Enregistrement…'; status.className = 'modal-status';
        const fd = new FormData(e.target);
        try {
            const res  = await fetch('/php/entrainements/update_entrainements_config.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                document.querySelector('.table-section-header h2').textContent = 'Saison ' + json.valeur;
                document.querySelector('.cat-saison').textContent = 'Saison ' + json.valeur;
                const newEnd = parseInt((json.valeur || '').split('-')[1]);
                if (newEnd && newEnd !== END_YEAR) {
                    END_YEAR = newEnd;
                    rebuildBirthYearSelect(END_YEAR);
                }
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeSaisonModal, 800);
            } else {
                status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            }
        } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
    });

    updateMoveButtons();
});

// ── Calculateur de catégorie ──────────────────────

// Source : récapitulatif FFvolley des catégories d'âge (saison dynamique)
const CAT_MAP = [
    { age: 6,  label: 'M7(1)',         base: 'M7',  gendered: false },
    { age: 7,  label: 'M7(2)',        base: 'M7',  gendered: false },
    { age: 8,  label: 'M9(1)',        base: 'M9',  gendered: false },
    { age: 9,  label: 'M9(2)',        base: 'M9',  gendered: false },
    { age: 10, label: 'M11(1)',       base: 'M11', gendered: true },
    { age: 11, label: 'M11(2)',       base: 'M11', gendered: true },
    { age: 12, label: 'M13(1)',       base: 'M13', gendered: true  },
    { age: 13, label: 'M13(2)',       base: 'M13', gendered: true  },
    { age: 14, label: 'M15(1)',       base: 'M15', gendered: true  },
    { age: 15, label: 'M15(2)',       base: 'M15', gendered: true  },
    { age: 16, label: 'M18(1)',       base: 'M18', gendered: true  },
    { age: 17, label: 'M18(2)',       base: 'M18', gendered: true  },
    { age: 18, label: 'M18(3)',       base: 'M18', gendered: true  },
    { age: 19, label: 'M21(1)',       base: 'M21', gendered: true  },
    { age: 20, label: 'M21(2)',       base: 'M21', gendered: true  },
    { age: 21, label: 'M21(3)',       base: 'M21', gendered: true  },
];

function showCategory(birthYear, gender) {
    const result  = document.getElementById('cat-result');
    const contact = document.getElementById('cat-contact');
    const endYear = END_YEAR;
    const age     = endYear - birthYear;
    const genderLabel = gender === 'female' ? 'Féminin' : 'Masculin';

    let catLabel = '', baseLabel = '', gendered = false;

    const exact = CAT_MAP.find(c => c.age === age);
    if (age >= 4 && age <= 5) {
        catLabel  = 'BABY';
        baseLabel = 'BABY';
        gendered  = false;
    } else if (exact) {
        catLabel  = exact.label;
        baseLabel = exact.base;
        gendered  = exact.gendered;
    } else if (age >= 22 && age < 40) {
        catLabel  = 'Sénior';
        baseLabel = 'Sénior';
        gendered  = true;
    } else if (age >= 40) {
        catLabel  = 'Masters';
        baseLabel = 'Masters';
        gendered  = false;
    } else {
        result.innerHTML = '<p class="cat-info">Trop jeune pour les catégories officielles.</p>';
        result.style.display  = 'block';
        contact.style.display = 'none';
        return;
    }

    const displayLabel = gendered ? `${catLabel} ${genderLabel}` : catLabel;
    result.innerHTML = `
        <div class="cat-badge">${escHtml(displayLabel)}</div>
        <p class="cat-info">Consultez le planning ou contactez le club pour avoir plus d'informations.</p>
    `;
    result.style.display  = 'block';
    contact.style.display = 'block';

    highlightRows(baseLabel, gendered ? genderLabel : null);
}

function highlightRows(base, genderLabel) {
    document.querySelectorAll('.entr-table tbody td.cell-highlighted').forEach(td => td.classList.remove('cell-highlighted'));

    const searchBase   = base.toLowerCase();
    const searchGender = genderLabel ? genderLabel.toLowerCase() : null;

    document.querySelectorAll('.entr-table tbody td:not(.day-cell):not(.horaire-cell):not(.actions-cell)').forEach(td => {
        const text = td.textContent.toLowerCase();
        let matches = text.includes(searchBase);
        if (matches && searchGender) matches = text.includes(searchGender);
        if (matches) td.classList.add('cell-highlighted');
    });
}

function rebuildBirthYearSelect(endYear) {
    const sel = document.getElementById('birth-year');
    if (!sel) return;
    while (sel.options.length > 1) sel.remove(1);
    for (let y = endYear - 4; y >= endYear - 40; y--) {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y + (y === endYear - 40 ? ' ou avant' : '');
        sel.appendChild(opt);
    }
    const result = document.getElementById('cat-result');
    const contact = document.getElementById('cat-contact');
    if (result)  { result.style.display = 'none'; result.innerHTML = ''; }
    if (contact) contact.style.display = 'none';
}

// ── Disposition des terrains ──────────────────────

const LAYOUT_CONFIG = {
    '111': [
        { badge: 'T1',       target: 't1' },
        { badge: 'T2',       target: 't2' },
        { badge: 'T3',       target: 't3' },
    ],
    '21': [
        { badge: 'T1 + T2',  target: 't1' },
        { badge: 'T3',       target: 't2' },
    ],
    '12': [
        { badge: 'T1',       target: 't1' },
        { badge: 'T2 + T3',  target: 't2' },
    ],
    '3': [
        { badge: 'T1 + T2 + T3', target: 't1' },
    ],
};

function setLayout(colspan, values) {
    document.querySelectorAll('.layout-opt').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.colspan === colspan);
    });
    document.getElementById('slot-colspan').value = colspan;
    renderTerrainTeams(colspan, values);
}

function renderTerrainTeams(colspan, values) {
    const container = document.getElementById('terrain-teams');
    if (!container) return;

    // Sauvegarder les valeurs des champs visuels actuels si aucune valeur fournie
    if (!values) {
        values = {};
        container.querySelectorAll('.team-visual-input').forEach(ta => {
            values[ta.dataset.target] = ta.value;
        });
    }

    const config = LAYOUT_CONFIG[colspan] || LAYOUT_CONFIG['111'];
    container.innerHTML = config.map(({ badge, target }) => {
        const val = escHtml(values[target] ?? '');
        return `<div class="team-input-group">
            <label><span class="team-terrain-badge">${badge}</span> Équipe</label>
            <textarea class="team-visual-input" data-target="${target}" rows="2" placeholder="Équipe&#10;Coach">${val}</textarea>
        </div>`;
    }).join('');
}

// ── Modales ───────────────────────────────────────

let _editingId = null;

function openAddModal(jour) {
    if (!CAN_EDIT) return;
    _editingId = null;
    document.getElementById('slot-modal-title').textContent = 'Ajouter un créneau';
    document.getElementById('slot-id').value    = '';
    document.getElementById('slot-jour').value  = jour;
    document.getElementById('slot-horaire').value = '';
    document.getElementById('slot-lieu').value    = '';
    setLayout('111', { t1: '', t2: '', t3: '' });
    document.getElementById('slot-status').textContent = '';
    document.getElementById('slot-delete-zone').style.display = 'none';
    document.getElementById('slotModal').classList.add('open');
}

function openEditModal(tr) {
    if (!CAN_EDIT) return;
    _editingId = tr.dataset.id;
    document.getElementById('slot-modal-title').textContent = 'Modifier le créneau';
    document.getElementById('slot-id').value = _editingId;

    // Pré-remplir jour/horaire/lieu depuis le DOM
    const horaireTd = tr.querySelector('.horaire-cell');
    document.getElementById('slot-jour').value    = tr.dataset.jour;
    document.getElementById('slot-horaire').value = horaireTd?.querySelector('.horaire-time')?.textContent.trim() || '';
    document.getElementById('slot-lieu').value    = horaireTd?.querySelector('.horaire-lieu')?.textContent.trim() || '';

    // Réinitialiser le layout en attendant le chargement
    setLayout('111', { t1: '', t2: '', t3: '' });

    document.getElementById('slot-status').textContent = 'Chargement…';
    document.getElementById('slot-status').className   = 'modal-status';
    document.getElementById('slot-delete-zone').style.display   = 'block';
    document.getElementById('btn-delete-slot').style.display    = 'block';
    document.getElementById('slot-delete-confirm').style.display = 'none';
    document.getElementById('slotModal').classList.add('open');

    loadSlotData(_editingId);
}

async function loadSlotData(id) {
    const statusEl = document.getElementById('slot-status');
    try {
        const res  = await fetch('/php/entrainements/get_entrainement.php?id=' + id);
        const json = await res.json();
        if (!json.success || !json.data) {
            throw new Error(json.error || 'Données introuvables');
        }
        const d = json.data;
        document.getElementById('slot-jour').value    = d.jour;
        document.getElementById('slot-horaire').value = d.horaire;
        document.getElementById('slot-lieu').value    = d.lieu;

        // Appliquer le layout et pré-remplir les valeurs
        // DB: t1=equipe1, t2=equipe2 (pour '21' et '12'), t3=equipe3 (pour '111' seulement)
        setLayout(d.colspan, { t1: d.t1 ?? '', t2: d.t2 ?? '', t3: d.t3 ?? '' });

        statusEl.textContent = '';
    } catch (err) {
        statusEl.textContent = 'Erreur lors du chargement : ' + (err.message || 'inconnu');
        statusEl.className   = 'modal-status error';
    }
}

function confirmRowDelete(tr) {
    openEditModal(tr);
    setTimeout(() => {
        document.getElementById('slot-delete-zone').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

function closeSlotModal() {
    document.getElementById('slotModal')?.classList.remove('open');
}

// ── Visibilité du tableau ─────────────────────────

async function toggleTableVisibility() {
    const newVal = TABLE_VISIBLE ? '0' : '1';
    const btn    = document.getElementById('btn-toggle-visibility');
    const label  = document.getElementById('toggle-label');

    btn.disabled = true;

    const fd = new FormData();
    fd.append('cle',    'visible');
    fd.append('valeur', newVal);

    try {
        const res  = await fetch('/php/entrainements/update_entrainements_config.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) { btn.disabled = false; return; }

        TABLE_VISIBLE = newVal === '1';

        if (TABLE_VISIBLE) {
            btn.classList.replace('is-hidden', 'is-visible');
            btn.title         = 'Masquer aux visiteurs';
            label.textContent = 'Visible';

            const banner = document.getElementById('table-hidden-banner');
            if (banner) banner.remove();

            const placeholder = document.querySelector('.table-placeholder');
            if (placeholder) {
                placeholder.remove();
                const section = document.querySelector('.table-section');
                if (section && !document.getElementById('table-scroll-wrap')) {
                    section.insertAdjacentHTML('beforeend',
                        '<div class="table-scroll-wrap" id="table-scroll-wrap">' +
                        '<table class="entr-table" id="entr-table">' +
                        '<thead><tr>' +
                        '<th>Jour</th><th>Horaire / Lieu</th>' +
                        '<th>Terrain 1</th><th>Terrain 2</th><th>Terrain 3</th>' +
                        (CAN_EDIT ? '<th class="no-print actions-th">Actions</th>' : '') +
                        '</tr></thead>' +
                        '<tbody id="entr-tbody"></tbody>' +
                        '</table></div>'
                    );
                }
            }
        } else {
            btn.classList.replace('is-visible', 'is-hidden');
            btn.title         = 'Rendre visible aux visiteurs';
            label.textContent = 'Masqué';

            let banner = document.getElementById('table-hidden-banner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id        = 'table-hidden-banner';
                banner.className = 'table-hidden-banner';
                banner.textContent = '⚠ Tableau masqué pour les visiteurs — seuls les modérateurs et admins peuvent le voir';
                const header = document.querySelector('.table-section-header');
                if (header) header.after(banner);
            } else {
                banner.style.display = '';
            }
        }
    } catch {
        btn.title = TABLE_VISIBLE ? 'Masquer aux visiteurs' : 'Rendre visible aux visiteurs';
    } finally {
        btn.disabled = false;
    }
}

function openSaisonModal() {
    document.getElementById('saison-status').textContent = '';
    document.getElementById('saison-status').className  = 'modal-status';
    document.getElementById('saisonModal').classList.add('open');
}

function closeSaisonModal() {
    document.getElementById('saisonModal')?.classList.remove('open');
}

// ── Déplacement des créneaux ──────────────────────

async function reorderSlot(id1, id2) {
    const fd = new FormData();
    fd.append('id1', id1);
    fd.append('id2', id2);
    try {
        const res  = await fetch('/php/entrainements/reorder_entrainement.php', { method: 'POST', body: fd });
        const json = await res.json();
        return json.success === true;
    } catch { return false; }
}

function swapAdjacentRows(rowAbove, rowBelow) {
    // rowAbove est actuellement avant rowBelow dans le DOM
    const dayCell = rowAbove.querySelector('.day-cell');
    if (dayCell) {
        // rowAbove est la première ligne du jour ; après l'échange, rowBelow prend la position
        rowAbove.removeChild(dayCell);
        rowBelow.prepend(dayCell);
    }
    rowAbove.parentNode.insertBefore(rowBelow, rowAbove);
}

async function moveSlot(tr, dir) {
    const jour = tr.dataset.jour;
    const rows = Array.from(document.querySelectorAll(`tr.row-${jour}[data-id]`));
    const idx  = rows.indexOf(tr);
    const targetIdx = idx + dir;
    if (targetIdx < 0 || targetIdx >= rows.length) return;

    const other   = rows[targetIdx];
    const success = await reorderSlot(tr.dataset.id, other.dataset.id);
    if (!success) return;

    if (dir < 0) {
        swapAdjacentRows(other, tr);   // other était au-dessus
    } else {
        swapAdjacentRows(tr, other);   // tr était au-dessus
    }
    updateMoveButtons();
}

function updateMoveButtons() {
    ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'].forEach(jour => {
        const rows = Array.from(document.querySelectorAll(`tr.row-${jour}[data-id]`));
        rows.forEach((tr, i) => {
            const up   = tr.querySelector('.btn-row-up');
            const down = tr.querySelector('.btn-row-down');
            if (up)   up.disabled   = (i === 0);
            if (down) down.disabled = (i === rows.length - 1);
        });
    });
}

// ── Suppression créneau ───────────────────────────

function confirmDeleteSlot() {
    document.getElementById('btn-delete-slot').style.display    = 'none';
    document.getElementById('slot-delete-confirm').style.display = 'flex';
}

function cancelDeleteSlot() {
    document.getElementById('btn-delete-slot').style.display    = 'block';
    document.getElementById('slot-delete-confirm').style.display = 'none';
}

async function deleteSlot() {
    const id     = document.getElementById('slot-id').value;
    const status = document.getElementById('slot-status');
    status.textContent = 'Suppression…'; status.className = 'modal-status';
    const fd = new FormData(); fd.append('id', id);
    try {
        const res  = await fetch('/php/entrainements/delete_entrainement.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            removeRowFromDOM(id);
            closeSlotModal();
        } else {
            status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            cancelDeleteSlot();
        }
    } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; cancelDeleteSlot(); }
}

// ── Manipulation DOM ──────────────────────────────

const dayLabels = { lundi:'Lundi', mardi:'Mardi', mercredi:'Mercredi', jeudi:'Jeudi', vendredi:'Vendredi', samedi:'Samedi', dimanche:'Dimanche' };
const colTotal  = typeof CAN_EDIT !== 'undefined' && CAN_EDIT ? 6 : 5;

function buildRowHtml(d) {
    let terrainHtml = '';
    switch (d.colspan) {
        case '111':
            terrainHtml = `<td>${cellContent(d.t1)}</td><td>${cellContent(d.t2)}</td><td>${cellContent(d.t3)}</td>`; break;
        case '21':
            terrainHtml = `<td colspan="2">${cellContent(d.t1)}</td><td>${cellContent(d.t2)}</td>`; break;
        case '12':
            terrainHtml = `<td>${cellContent(d.t1)}</td><td colspan="2">${cellContent(d.t2)}</td>`; break;
        case '3':
            terrainHtml = `<td colspan="3">${cellContent(d.t1)}</td>`; break;
    }
    const actionsHtml = CAN_EDIT ? `<td class="actions-cell no-print">
        <div class="action-btns">
            <button class="btn-row-move btn-row-up" onclick="moveSlot(this.closest('tr'),-1)" title="Monter">▲</button>
            <button class="btn-row-edit" onclick="openEditModal(this.closest('tr'))" title="Modifier">✏️</button>
            <button class="btn-row-delete" onclick="confirmRowDelete(this.closest('tr'))" title="Supprimer">🗑</button>
            <button class="btn-row-move btn-row-down" onclick="moveSlot(this.closest('tr'),1)" title="Descendre">▼</button>
        </div>
    </td>` : '';
    return `<tr class="row-${d.jour}" data-jour="${d.jour}" data-id="${d.id}">
        <td class="horaire-cell">
            <span class="horaire-time">${escHtml(d.horaire)}</span>
            <span class="horaire-lieu">${escHtml(d.lieu)}</span>
        </td>
        ${terrainHtml}
        ${actionsHtml}
    </tr>`;
}

function cellContent(t) {
    if (!t) return '<span class="cell-empty">—</span>';
    const parts = t.split('\n');
    return `<span class="cell-team">${escHtml(parts[0])}</span>${parts[1] ? '<span class="cell-coach">' + escHtml(parts[1]) + '</span>' : ''}`;
}

function addRowToDOM(data) {
    const addRow = document.querySelector(`.add-row-tr[data-jour="${data.jour}"]`);
    if (!addRow) { location.reload(); return; }

    const existing = document.querySelector(`tr.row-${data.jour}[data-id]`);
    const newTr    = document.createElement('tr');
    newTr.className    = `row-${data.jour}`;
    newTr.dataset.jour = data.jour;
    newTr.dataset.id   = data.id;

    if (!existing) {
        const dayTd = document.createElement('td');
        dayTd.className   = 'day-cell';
        dayTd.rowSpan     = 1;
        dayTd.textContent = dayLabels[data.jour] || data.jour;
        newTr.appendChild(dayTd);
    } else {
        const allRows  = document.querySelectorAll(`tr.row-${data.jour}[data-id]`);
        const firstRow = allRows[0];
        if (firstRow) {
            const dayCell = firstRow.querySelector('.day-cell');
            if (dayCell) dayCell.rowSpan = (parseInt(dayCell.rowSpan) || 1) + 1;
        }
    }

    newTr.insertAdjacentHTML('beforeend', buildRowHtml(data).replace(/<tr[^>]*>|<\/tr>/g, '').replace(/<td class="horaire/, '<td class="horaire'));
    location.reload();
}

function updateRowInDOM(data) {
    const tr = document.querySelector(`tr[data-id="${data.id}"]`);
    if (!tr) return;

    const horaireTd = tr.querySelector('.horaire-cell');
    if (horaireTd) {
        horaireTd.querySelector('.horaire-time').textContent = data.horaire;
        horaireTd.querySelector('.horaire-lieu').textContent = data.lieu;
    }

    const actionsTd = tr.querySelector('.actions-cell');
    tr.querySelectorAll('td:not(.day-cell):not(.horaire-cell):not(.actions-cell)').forEach(td => td.remove());

    const tmp = document.createElement('template');
    let terrainHtml = '';
    switch (data.colspan) {
        case '111': terrainHtml = `<td>${cellContent(data.t1)}</td><td>${cellContent(data.t2)}</td><td>${cellContent(data.t3)}</td>`; break;
        case '21':  terrainHtml = `<td colspan="2">${cellContent(data.t1)}</td><td>${cellContent(data.t2)}</td>`; break;
        case '12':  terrainHtml = `<td>${cellContent(data.t1)}</td><td colspan="2">${cellContent(data.t2)}</td>`; break;
        case '3':   terrainHtml = `<td colspan="3">${cellContent(data.t1)}</td>`; break;
    }
    tmp.innerHTML = terrainHtml;
    if (actionsTd) {
        tmp.content.childNodes.forEach(n => tr.insertBefore(n.cloneNode(true), actionsTd));
    } else {
        tmp.content.childNodes.forEach(n => tr.appendChild(n.cloneNode(true)));
    }
}

function removeRowFromDOM(id) {
    const tr = document.querySelector(`tr[data-id="${id}"]`);
    if (!tr) return;
    const jour    = tr.dataset.jour;
    const allRows = document.querySelectorAll(`tr.row-${jour}[data-id]`);
    if (allRows.length === 1) {
        location.reload();
        return;
    }
    if (tr.querySelector('.day-cell')) {
        const nextRow = tr.nextElementSibling;
        if (nextRow && nextRow.classList.contains(`row-${jour}`)) {
            const dayTd    = tr.querySelector('.day-cell');
            const newSpan  = (parseInt(dayTd.rowSpan) || 1) - 1;
            dayTd.rowSpan  = newSpan;
            nextRow.prepend(dayTd);
        }
    } else {
        const firstRow = document.querySelector(`tr.row-${jour}[data-id] .day-cell`);
        if (firstRow) firstRow.rowSpan = (parseInt(firstRow.rowSpan) || 2) - 1;
    }
    tr.remove();
    updateMoveButtons();
}

// ── Utilitaires ───────────────────────────────────

function escHtml(str) {
    return String(str || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
