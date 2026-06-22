document.addEventListener('DOMContentLoaded', () => {
    loadHTML('/commun/menu.html', 'menu');
    loadHTML('/commun/footer.html', 'footer');

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSlotModal(); closeSaisonModal(); }
    });

    document.getElementById('slotModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('slotModal')) closeSlotModal();
    });
    document.getElementById('saisonModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('saisonModal')) closeSaisonModal();
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

        // Calculer colspan depuis les cases cochées et vider les inactives
        const c1 = document.getElementById('cb-t1').checked;
        const c2 = document.getElementById('cb-t2').checked;
        const c3 = document.getElementById('cb-t3').checked;

        let colspan = '111';
        if      (c1 && c2 && !c3) colspan = '21';
        else if (c1 && !c2 && !c3) colspan = '3';

        if (!c1) document.getElementById('slot-t1').value = '';
        if (!c2) document.getElementById('slot-t2').value = '';
        if (!c3) document.getElementById('slot-t3').value = '';
        document.getElementById('slot-colspan').value = colspan;

        // Réactiver pour que FormData les inclue (disabled n'est pas envoyé)
        ['t1','t2','t3'].forEach(t => { document.getElementById('slot-' + t).disabled = false; });

        const fd  = new FormData(e.target);
        refreshTerrainVisibility();
        const id  = fd.get('id');
        const url = id ? '/php/update_entrainement.php' : '/php/add_entrainement.php';
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
            const res  = await fetch('/php/update_entrainements_config.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                document.querySelector('.table-section-header h2').textContent = 'Saison ' + json.valeur;
                document.querySelector('.cat-saison').textContent = 'Saison ' + json.valeur;
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeSaisonModal, 800);
            } else {
                status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            }
        } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
    });
});

// ── Calculateur de catégorie ──────────────────────

const categories = [
    { max: 0, min: -1,   label: 'Baby',   desc: 'Les tout-petits commencent le volley !' },
    { age: 7,            label: 'M7',     desc: '' },
    { age: 9,            label: 'M9',     desc: '' },
    { age: 11,           label: 'M11',    desc: '' },
    { age: 13,           label: 'M13',    gendered: true, desc: '' },
    { age: 15,           label: 'M15',    gendered: true, desc: '' },
    { age: 18,           label: 'M18',    gendered: true, desc: '' },
    { age: 21,           label: 'M21',    gendered: true, desc: '' },
];

function showCategory(birthYear, gender) {
    const result  = document.getElementById('cat-result');
    const contact = document.getElementById('cat-contact');
    const endYear = typeof END_YEAR !== 'undefined' ? END_YEAR : new Date().getFullYear();
    const age     = endYear - birthYear;
    let label = '';

    if (age <= 4)       label = 'Baby';
    else if (age <= 6)  label = 'M7';   // correction: M7 = jusqu'à 6 ans inclus
    else if (age <= 8)  label = 'M9';
    else if (age <= 10) label = 'M11';
    else if (age <= 12) label = 'M13';
    else if (age <= 14) label = 'M15';
    else if (age <= 17) label = 'M18';
    else if (age <= 20) label = 'M21';
    else                label = 'Sénior';

    // Ajouter le genre pour les catégories concernées
    const gendered = ['M13','M15','M18','M21','Sénior'];
    if (gendered.includes(label)) {
        label += ' ' + (gender === 'female' ? 'Féminin' : 'Masculin');
    }

    result.innerHTML = `
        <div class="cat-badge">${escHtml(label)}</div>
        <p class="cat-info">Consultez le planning ou contactez le club pour les horaires de la saison.</p>
    `;
    result.style.display  = 'block';
    contact.style.display = 'block';

    // Tenter de mettre en évidence les lignes correspondantes
    highlightRows(label);
}

function highlightRows(categoryLabel) {
    // Reset highlights
    document.querySelectorAll('.entr-table tbody tr').forEach(tr => tr.classList.remove('row-highlighted'));

    const key = categoryLabel.toLowerCase();
    document.querySelectorAll('.entr-table tbody td').forEach(td => {
        const text = td.textContent.toLowerCase();
        if (text.includes(key.split(' ')[0]) && (key.split(' ').length < 2 || text.includes(key.split(' ')[1] || ''))) {
            const tr = td.closest('tr');
            if (tr) tr.classList.add('row-highlighted');
        }
    });
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
    document.getElementById('slot-t1').value = '';
    document.getElementById('slot-t2').value = '';
    document.getElementById('slot-t3').value = '';
    document.getElementById('cb-t1').checked = true;
    document.getElementById('cb-t2').checked = true;
    document.getElementById('cb-t3').checked = true;
    refreshTerrainVisibility();
    document.getElementById('slot-status').textContent = '';
    document.getElementById('slot-delete-zone').style.display = 'none';
    document.getElementById('slotModal').classList.add('open');
}

function openEditModal(tr) {
    if (!CAN_EDIT) return;
    _editingId = tr.dataset.id;
    document.getElementById('slot-modal-title').textContent = 'Modifier le créneau';
    document.getElementById('slot-id').value = _editingId;

    // Récupérer les données depuis les cellules du tableau
    const jour    = tr.dataset.jour;
    const cells   = tr.querySelectorAll('td');
    // La cellule horaire est toujours présente (1re ou 2e td selon rowspan)
    const horaireTd = tr.querySelector('.horaire-cell');
    document.getElementById('slot-jour').value    = jour;
    document.getElementById('slot-horaire').value = horaireTd?.querySelector('.horaire-time')?.textContent.trim() || '';
    document.getElementById('slot-lieu').value    = horaireTd?.querySelector('.horaire-lieu')?.textContent.trim() || '';

    // Lire les terrains depuis les data-attributes du formulaire (pas disponibles ici)
    // On fait une requête PHP séparée pour récupérer les données complètes
    loadSlotData(_editingId);

    document.getElementById('slot-status').textContent = '';
    document.getElementById('slot-delete-zone').style.display = 'block';
    document.getElementById('btn-delete-slot').style.display = 'block';
    document.getElementById('slot-delete-confirm').style.display = 'none';
    document.getElementById('slotModal').classList.add('open');
}

async function loadSlotData(id) {
    try {
        const res  = await fetch('/php/get_entrainement.php?id=' + id);
        const json = await res.json();
        if (json.data) {
            const d = json.data;
            document.getElementById('slot-jour').value    = d.jour;
            document.getElementById('slot-horaire').value = d.horaire;
            document.getElementById('slot-lieu').value    = d.lieu;
            // Normalise pour les cases à cocher : on répartit t1/t2/t3
            // selon le colspan d'origine pour retrouver les bons contenus
            let v1 = d.t1, v2 = d.t2, v3 = d.t3;
            if (d.colspan === '21') { v2 = ''; v3 = d.t2; }  // t2 était en terrain 3
            if (d.colspan === '12') { v2 = ''; v3 = d.t2; }  // t2 était en terrain 2+3
            if (d.colspan === '3')  { v2 = ''; v3 = ''; }

            document.getElementById('slot-t1').value = v1;
            document.getElementById('slot-t2').value = v2;
            document.getElementById('slot-t3').value = v3;
            document.getElementById('cb-t1').checked = v1.trim() !== '';
            document.getElementById('cb-t2').checked = v2.trim() !== '';
            document.getElementById('cb-t3').checked = v3.trim() !== '';
            refreshTerrainVisibility();
        }
    } catch {}
}

function confirmRowDelete(tr) {
    openEditModal(tr);
    // Scroll to delete zone after modal opens
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
    const banner = document.getElementById('table-hidden-banner');

    const fd = new FormData();
    fd.append('cle',    'visible');
    fd.append('valeur', newVal);

    try {
        const res  = await fetch('/php/update_entrainements_config.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) return;

        TABLE_VISIBLE = newVal === '1';

        if (TABLE_VISIBLE) {
            btn.classList.replace('is-hidden', 'is-visible');
            btn.title    = 'Masquer aux visiteurs';
            label.textContent = 'Visible';
            if (banner) banner.style.display = 'none';
        } else {
            btn.classList.replace('is-visible', 'is-hidden');
            btn.title    = 'Rendre visible aux visiteurs';
            label.textContent = 'Masqué';
            if (banner) {
                banner.style.display = '';
            } else {
                // Créer le banner s'il n'existait pas (table était visible au chargement)
                const wrap = document.querySelector('.table-scroll-wrap') || document.querySelector('#entr-table')?.closest('section');
                if (wrap) {
                    const b = document.createElement('div');
                    b.id        = 'table-hidden-banner';
                    b.className = 'table-hidden-banner';
                    b.textContent = '⚠ Tableau masqué pour les visiteurs — seuls les modérateurs et admins peuvent le voir';
                    wrap.closest('section')?.querySelector('.table-section-header')?.after(b);
                }
            }
        }
    } catch {}
}

function openSaisonModal() {
    document.getElementById('saison-status').textContent = '';
    document.getElementById('saison-status').className  = 'modal-status';
    document.getElementById('saisonModal').classList.add('open');
}

function closeSaisonModal() {
    document.getElementById('saisonModal')?.classList.remove('open');
}

// ── Terrain fields (cases à cocher) ──────────────

function toggleTerrain(which) {
    const cb = document.getElementById('cb-' + which);
    const ta = document.getElementById('slot-' + which);
    const group = cb.closest('.terrain-cb-group');
    if (cb.checked) {
        group.classList.remove('terrain-inactive');
        ta.disabled = false;
    } else {
        group.classList.add('terrain-inactive');
        ta.disabled = true;
        ta.value = '';
    }
}

function refreshTerrainVisibility() {
    ['t1','t2','t3'].forEach(t => {
        const cb = document.getElementById('cb-' + t);
        const ta = document.getElementById('slot-' + t);
        const group = cb?.closest('.terrain-cb-group');
        if (!cb || !ta || !group) return;
        if (cb.checked) {
            group.classList.remove('terrain-inactive');
            ta.disabled = false;
        } else {
            group.classList.add('terrain-inactive');
            ta.disabled = true;
        }
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
        const res  = await fetch('/php/delete_entrainement.php', { method: 'POST', body: fd });
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
    const cell = (t) => `<td>${t ? `<span class="cell-team">${escHtml(t.split('\n')[0] || '')}</span>${t.split('\n')[1] ? '<span class="cell-coach">' + escHtml(t.split('\n')[1]) + '</span>' : ''}` : '<span class="cell-empty">—</span>'}</td>`;
    switch (d.colspan) {
        case '111':
            terrainHtml = cell(d.t1) + cell(d.t2) + cell(d.t3); break;
        case '21':
            terrainHtml = `<td colspan="2">${cellContent(d.t1)}</td><td>${cellContent(d.t2)}</td>`; break;
        case '12':
            terrainHtml = `<td>${cellContent(d.t1)}</td><td colspan="2">${cellContent(d.t2)}</td>`; break;
        case '3':
            terrainHtml = `<td colspan="3">${cellContent(d.t1)}</td>`; break;
    }
    const actionsHtml = CAN_EDIT ? `<td class="actions-cell no-print">
        <button class="btn-row-edit" onclick="openEditModal(this.closest('tr'))" title="Modifier">✏️</button>
        <button class="btn-row-delete" onclick="confirmRowDelete(this.closest('tr'))" title="Supprimer">🗑</button>
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
    // Trouver la ligne "ajouter" du bon jour
    const addRow = document.querySelector(`.add-row-tr[data-jour="${data.jour}"]`);
    if (!addRow) { location.reload(); return; }

    // Vérifier si la cellule "jour" existe déjà dans le tbody pour ce jour
    const existing = document.querySelector(`tr.row-${data.jour}[data-id]`);
    const newTr    = document.createElement('tr');
    newTr.className   = `row-${data.jour}`;
    newTr.dataset.jour = data.jour;
    newTr.dataset.id   = data.id;

    if (!existing) {
        // Première ligne du jour : ajouter la cellule jour
        const dayTd = document.createElement('td');
        dayTd.className = 'day-cell';
        dayTd.rowSpan   = 1;
        dayTd.textContent = dayLabels[data.jour] || data.jour;
        newTr.appendChild(dayTd);
    } else {
        // Incrémenter le rowspan de la cellule jour existante
        const dayCellExisting = document.querySelector(`.day-cell[rowspan]`);
        // Find the day-cell for this specific day
        const allRows  = document.querySelectorAll(`tr.row-${data.jour}[data-id]`);
        const firstRow = allRows[0];
        if (firstRow) {
            const dayCell = firstRow.querySelector('.day-cell');
            if (dayCell) dayCell.rowSpan = (parseInt(dayCell.rowSpan) || 1) + 1;
        }
    }

    // Ajouter les cellules horaire + terrains + actions
    newTr.insertAdjacentHTML('beforeend', buildRowHtml(data).replace(/<tr[^>]*>|<\/tr>/g, '').replace(/<td class="horaire/, '<td class="horaire'));
    // Simpler: just reload. The DOM manipulation for rowspan is complex.
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

    // Rebuild terrain cells
    const actionsTd = tr.querySelector('.actions-cell');
    // Remove existing terrain TDs (not day-cell, not horaire-cell, not actions-cell)
    tr.querySelectorAll('td:not(.day-cell):not(.horaire-cell):not(.actions-cell)').forEach(td => td.remove());

    const tmp = document.createElement('template');
    let terrainHtml = '';
    const cc = (t) => t
        ? `<span class="cell-team">${escHtml((t.split('\n')[0]) || '')}</span>${t.split('\n')[1] ? '<span class="cell-coach">' + escHtml(t.split('\n')[1]) + '</span>' : ''}`
        : '<span class="cell-empty">—</span>';

    switch (data.colspan) {
        case '111': terrainHtml = `<td>${cc(data.t1)}</td><td>${cc(data.t2)}</td><td>${cc(data.t3)}</td>`; break;
        case '21':  terrainHtml = `<td colspan="2">${cc(data.t1)}</td><td>${cc(data.t2)}</td>`; break;
        case '12':  terrainHtml = `<td>${cc(data.t1)}</td><td colspan="2">${cc(data.t2)}</td>`; break;
        case '3':   terrainHtml = `<td colspan="3">${cc(data.t1)}</td>`; break;
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
        // Dernière ligne du jour : recharger (pour nettoyer le rowspan)
        location.reload();
        return;
    }
    // Réattribuer la cellule jour si c'est la 1re ligne
    if (tr.querySelector('.day-cell')) {
        const nextRow = tr.nextElementSibling;
        if (nextRow && nextRow.classList.contains(`row-${jour}`)) {
            const dayTd    = tr.querySelector('.day-cell');
            const newSpan  = (parseInt(dayTd.rowSpan) || 1) - 1;
            dayTd.rowSpan  = newSpan;
            nextRow.prepend(dayTd);
        }
    } else {
        // Décrémenter rowspan de la cellule jour
        const firstRow = document.querySelector(`tr.row-${jour}[data-id] .day-cell`);
        if (firstRow) firstRow.rowSpan = (parseInt(firstRow.rowSpan) || 2) - 1;
    }
    tr.remove();
}

// ── Utilitaires ───────────────────────────────────

function escHtml(str) {
    return String(str || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
