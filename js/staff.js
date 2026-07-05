// ── Utilitaires ───────────────────────────────────────────────────

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Modale "Voir plus" commissions (publique) ─────────────────────

function openViewModal(card) {
    const id       = card.dataset.id;
    const title    = card.dataset.sous_titre || '';
    const subtitle = card.dataset.nom        || '';
    const photo    = card.dataset.photo      || '';

    document.getElementById('modal-image').src     = photo;
    document.getElementById('modal-image').alt     = title;
    document.getElementById('modal-title').textContent    = title;
    document.getElementById('modal-subtitle').textContent = subtitle;

    const rawDesc = (typeof STAFF_DESC !== 'undefined' && STAFF_DESC[id]) ? STAFF_DESC[id] : '<p>Description à venir.</p>';
    const descEl  = document.getElementById('modal-description');
    descEl.innerHTML = '';
    const tmp = document.createElement('template');
    tmp.innerHTML = rawDesc;
    tmp.content.querySelectorAll('*').forEach(el => {
        for (const attr of [...el.attributes]) {
            if (/^on/i.test(attr.name) || (attr.name === 'href' && /^javascript:/i.test(attr.value))) el.removeAttribute(attr.name);
        }
    });
    descEl.appendChild(tmp.content);

    const links = document.getElementById('modal-links');
    links.innerHTML = '';
    if (title === 'Devenir membre') {
        links.innerHTML = '<a href="/pages/leClub/commissions.html">En savoir plus</a>'
                        + '<a href="/pages/nousContacter.html">Contactez-nous</a>';
    }

    document.getElementById('myModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function closeViewModal() {
    document.getElementById('myModal').style.display = 'none';
    document.body.classList.remove('modal-open');
}

// ── Modale Membre (ajout / édition) ──────────────────────────────

function _updateMemberFields(section) {
    const isEdit = !!document.getElementById('mb-id').value;
    document.getElementById('mb-vcard-row').style.display = section === 'bureau'       ? '' : 'none';
    document.getElementById('mb-desc-row').style.display  = section === 'commissions'  ? '' : 'none';
    document.getElementById('mb-delete-zone').style.display = isEdit ? '' : 'none';
    if (!isEdit) {
        document.getElementById('mb-delete-confirm').style.display = 'none';
        document.getElementById('btn-delete-member').style.display  = 'block';
    }
}

const PRESET_PHOTOS = [
    '/images/icones/Asset 8@3x.png',
    '/images/icones/Asset 1@3x.png',
    '/images/icones/Asset 3@3x.png',
    '/images/icones/interrogation-point.png',
];

function updatePhotoPreview(src) {
    const img = document.getElementById('mb-photo-preview');
    if (img) img.src = src || '/images/icones/interrogation-point.png';
}

function _setPhotoSelect(photoVal) {
    const sel    = document.getElementById('mb-photo-sel');
    const custom = document.getElementById('mb-photo-custom');
    const upload = document.getElementById('mb-upload-zone');
    const status = document.getElementById('mb-upload-status');

    if (PRESET_PHOTOS.includes(photoVal)) {
        sel.value = photoVal;
        custom.style.display = 'none';
        upload.style.display = 'none';
    } else if (photoVal.startsWith('/images/staff/')) {
        sel.value = '_upload';
        upload.style.display = '';
        custom.style.display = 'none';
        if (status) { status.textContent = '✓ Photo existante'; status.style.color = '#2e7d32'; }
    } else {
        sel.value = '_custom';
        custom.value = photoVal;
        custom.style.display = '';
        upload.style.display = 'none';
    }
    document.getElementById('mb-photo').value = photoVal;
    updatePhotoPreview(photoVal);
}

function onPhotoSelectChange() {
    const val    = document.getElementById('mb-photo-sel').value;
    const custom = document.getElementById('mb-photo-custom');
    const upload = document.getElementById('mb-upload-zone');
    const status = document.getElementById('mb-upload-status');

    custom.style.display = val === '_custom' ? '' : 'none';
    upload.style.display = val === '_upload' ? '' : 'none';

    if (val === '_custom') {
        custom.focus();
        document.getElementById('mb-photo').value = custom.value;
        updatePhotoPreview(custom.value);
    } else if (val !== '_upload') {
        document.getElementById('mb-photo').value = val;
        updatePhotoPreview(val);
        if (status) status.textContent = '';
    }
}

function syncCustomPhoto(input) {
    document.getElementById('mb-photo').value = input.value;
    updatePhotoPreview(input.value);
}

async function uploadPhotoPreview(input) {
    if (!input.files || !input.files[0]) return;
    const status = document.getElementById('mb-upload-status');
    status.textContent = 'Envoi en cours…';
    status.style.color = '#666';

    const fd = new FormData();
    fd.append('photo', input.files[0]);
    try {
        const res  = await fetch('/php/staff/upload_staff_photo.php', { method:'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            document.getElementById('mb-photo').value = json.path;
            updatePhotoPreview(json.path);
            status.textContent = '✓ Photo importée';
            status.style.color = '#2e7d32';
        } else {
            status.textContent = json.error || 'Erreur.';
            status.style.color = '#c62828';
        }
    } catch {
        status.textContent = 'Erreur réseau.';
        status.style.color = '#c62828';
    }
}

function openAddModal(section) {
    document.getElementById('mb-modal-title').textContent = 'Ajouter un membre';
    document.getElementById('mb-id').value      = '';
    document.getElementById('mb-section').value = section;
    document.getElementById('mb-nom').value       = '';
    document.getElementById('mb-sous_titre').value = '';
    document.getElementById('mb-vcard').value     = '';
    document.getElementById('mb-desc').value      = '';
    document.getElementById('mb-status').textContent = '';
    _setPhotoSelect('/images/icones/interrogation-point.png');
    _updateMemberFields(section);
    document.getElementById('memberModal').classList.add('open');
}

function openEditModal(card) {
    const d = card.dataset;
    document.getElementById('mb-modal-title').textContent  = 'Modifier le membre';
    document.getElementById('mb-id').value       = d.id;
    document.getElementById('mb-section').value  = d.section;
    document.getElementById('mb-nom').value       = d.nom       || '';
    document.getElementById('mb-sous_titre').value= d.sous_titre|| '';
    document.getElementById('mb-vcard').value     = d.vcard     || '';
    document.getElementById('mb-desc').value      = (typeof STAFF_DESC !== 'undefined' && STAFF_DESC[d.id]) ? STAFF_DESC[d.id] : '';
    document.getElementById('mb-status').textContent = '';
    _setPhotoSelect(d.photo || '/images/icones/interrogation-point.png');
    _updateMemberFields(d.section);
    document.getElementById('memberModal').classList.add('open');
}

function closeMemberModal() {
    document.getElementById('memberModal').classList.remove('open');
}

// ── Suppression membre ────────────────────────────────────────────

function confirmDeleteMember() {
    document.getElementById('btn-delete-member').style.display  = 'none';
    document.getElementById('mb-delete-confirm').style.display  = 'flex';
}
function cancelDeleteMember() {
    document.getElementById('btn-delete-member').style.display  = 'block';
    document.getElementById('mb-delete-confirm').style.display  = 'none';
}

async function deleteMember() {
    const id     = document.getElementById('mb-id').value;
    const status = document.getElementById('mb-status');
    status.textContent = 'Suppression…'; status.className = 'modal-status';
    const fd = new FormData(); fd.append('id', id);
    try {
        const res  = await fetch('/php/staff/delete_staff_membre.php', { method:'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            document.querySelector(`.position[data-id="${id}"]`)?.remove();
            closeMemberModal();
        } else {
            status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            cancelDeleteMember();
        }
    } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; cancelDeleteMember(); }
}

// ── Visibilité entraîneur ─────────────────────────────────────────

async function toggleCoachVisible(card) {
    const id  = card.dataset.id;
    const fd  = new FormData(); fd.append('id', id);
    const btn = card.querySelector('.btn-coach-toggle');
    try {
        const res  = await fetch('/php/staff/toggle_staff_visible.php', { method:'POST', body: fd });
        const json = await res.json();
        if (!json.success) return;
        const visible = json.visible;
        card.dataset.visible = visible;
        if (visible) {
            card.classList.remove('coach-hidden');
            btn.classList.replace('is-hidden','is-visible');
            btn.title = 'Masquer aux visiteurs';
        } else {
            card.classList.add('coach-hidden');
            btn.classList.replace('is-visible','is-hidden');
            btn.title = 'Rendre visible';
        }
    } catch {}
}

// ── Modale PDF (visionneuse) ─────────────────────────────────────

const IS_IOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
               (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

function openPdfModal(path, label) {
    document.getElementById('pdf-modal-title').textContent = label;
    document.getElementById('pdf-download-btn').href       = path;
    document.getElementById('pdf-download-btn').setAttribute('download', label + '.pdf');

    const iframe    = document.getElementById('pdf-iframe');
    const fallback  = document.getElementById('pdf-fallback');
    const openLink  = document.getElementById('pdf-open-link');

    if (IS_IOS) {
        iframe.src             = '';
        iframe.style.display   = 'none';
        fallback.style.display = 'flex';
        openLink.href          = path;
    } else {
        iframe.src             = path;
        iframe.style.display   = 'block';
        fallback.style.display = 'none';
    }

    document.getElementById('pdfModal').classList.add('open');
    document.body.classList.add('modal-open');
}

function closePdfModal() {
    document.getElementById('pdfModal').classList.remove('open');
    document.body.classList.remove('modal-open');
    document.getElementById('pdf-iframe').src = '';
}

function printPdf() {
    if (IS_IOS) {
        window.open(document.getElementById('pdf-download-btn').href, '_blank');
        return;
    }
    const iframe = document.getElementById('pdf-iframe');
    try { iframe.contentWindow.print(); } catch { window.open(iframe.src, '_blank'); }
}

// ── Modale Document (ajout PV AG) ────────────────────────────────

function openDocModal(type) {
    document.getElementById('doc-type').value  = type;
    document.getElementById('doc-label').value = '';
    document.getElementById('doc-fichier').value = '';
    document.getElementById('doc-status').textContent = '';
    const titles = { pvag: 'Ajouter un PV d\'AG', statuts: 'Ajouter un statut / règlement' };
    document.getElementById('doc-modal-title').textContent = titles[type] || 'Ajouter un document';
    document.getElementById('docModal').classList.add('open');
}
function closeDocModal() {
    document.getElementById('docModal').classList.remove('open');
}

async function deleteDocument(id, type) {
    if (!confirm('Supprimer ce document ?')) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const res  = await fetch('/php/staff/delete_staff_document.php', { method:'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            const item      = document.querySelector(`.pvag-item[data-id="${id}"]`);
            const container = item?.parentElement;
            item?.remove();
            if (container) _refreshMoveButtons(container);
        } else {
            alert(json.error || 'Erreur lors de la suppression.');
        }
    } catch { alert('Erreur réseau.'); }
}

// ── Réordonnancement documents ────────────────────────────────────

function _refreshMoveButtons(container) {
    const items = [...container.querySelectorAll('.pvag-item')];
    items.forEach((item, i) => {
        const btns = item.querySelectorAll('.btn-doc-move');
        if (btns.length < 2) return;
        btns[0].disabled = (i === 0);
        btns[1].disabled = (i === items.length - 1);
    });
}

async function moveDoc(btn, direction) {
    const item      = btn.closest('.pvag-item');
    const container = item.parentElement;
    const sibling   = direction === -1 ? item.previousElementSibling : item.nextElementSibling;
    if (!sibling) return;

    if (direction === -1) container.insertBefore(item, sibling);
    else                  container.insertBefore(sibling, item);

    _refreshMoveButtons(container);

    const ids = [...container.querySelectorAll('.pvag-item')].map(el => el.dataset.id);
    const fd  = new FormData();
    ids.forEach(id => fd.append('ids[]', id));
    try {
        await fetch('/php/staff/reorder_documents.php', { method: 'POST', body: fd });
    } catch {}
}

// ── Soumission formulaires ─────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {

    // Formulaire membre
    document.getElementById('member-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('mb-status');
        status.textContent = 'Enregistrement…'; status.className = 'modal-status';

        const fd = new FormData(e.target);
        const id = fd.get('id');
        const url = id ? '/php/staff/update_staff_membre.php' : '/php/staff/add_staff_membre.php';
        try {
            const res  = await fetch(url, { method:'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                if (id) {
                    updateCardInDOM(json.data);
                } else {
                    addCardToDOM(json.data);
                }
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeMemberModal, 700);
            } else {
                status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            }
        } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
    });

    // Formulaire document
    document.getElementById('doc-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('doc-status');
        status.textContent = 'Envoi…'; status.className = 'modal-status';
        const fd = new FormData(e.target);
        try {
            const res  = await fetch('/php/staff/add_staff_document.php', { method:'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                addDocToDOM(json.data);
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeDocModal, 700);
            } else {
                status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            }
        } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
    });

    // État initial des boutons ▲▼
    ['pvag-container','statuts-container'].forEach(id => {
        const c = document.getElementById(id);
        if (c) _refreshMoveButtons(c);
    });

    // Fermeture clavier
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeViewModal(); closeMemberModal(); closeDocModal(); closePdfModal(); }
    });

    // Fermeture clic fond
    document.getElementById('myModal')?.addEventListener('click', e => { if (e.target.id === 'myModal') closeViewModal(); });
    document.getElementById('memberModal')?.addEventListener('click', e => { if (e.target.id === 'memberModal') closeMemberModal(); });
    document.getElementById('docModal')?.addEventListener('click',   e => { if (e.target.id === 'docModal')   closeDocModal(); });
});

// ── Manipulation DOM ─────────────────────────────────────────────

const SECTION_SELECTORS = {
    bureau:       '.level.executives',
    membres:      '.level.members',
    commissions:  '.trombinoscope#cible-commissions .level-coaches',
    entraineurs:  '.trombinoscope:not(#cible-commissions) .level-coaches',
};

function buildCard(d) {
    const isCommission = d.section === 'commissions';
    const isCoach      = d.section === 'entraineurs';
    const hiddenClass  = (isCoach && !d.visible) ? ' coach-hidden' : '';

    const editBtn = CAN_EDIT ? (isCommission
        ? `<button class="staff-edit-btn" onclick="event.stopPropagation();openEditModal(this.closest('.position'))" title="Modifier">✏️</button>`
        : isCoach
            ? `<div class="coach-edit-group">
                <button class="btn-coach-toggle is-visible" onclick="toggleCoachVisible(this.closest('.position'))" title="Masquer aux visiteurs">👁</button>
                <button class="staff-edit-btn-inline" onclick="openEditModal(this.closest('.position'))" title="Modifier">✏️</button>
               </div>`
            : `<button class="staff-edit-btn" onclick="openEditModal(this.closest('.position'))" title="Modifier">✏️</button>`)
        : '';

    const clickAttr = isCommission ? 'role="button" tabindex="0" onclick="openViewModal(this)"' : '';

    let inner = '';
    inner += `<img src="${escHtml(d.photo)}" alt="${escHtml(d.nom)}">`;
    if (isCommission) {
        inner += `<p class="name-commission">${escHtml(d.sous_titre)}</p>`;
        if (d.nom) inner += `<p class="categorie">${escHtml(d.nom)}</p>`;
        inner += `<p class="see-more">Voir plus</p>`;
    } else if (isCoach) {
        inner += `<p class="name">${escHtml(d.nom)}</p>`;
        inner += `<p class="categorie"><u>Catégorie :</u><br>${escHtml(d.sous_titre)}</p>`;
    } else {
        if (d.sous_titre) inner += `<p class="categorie">${escHtml(d.sous_titre)}</p>`;
        inner += `<p class="name">${escHtml(d.nom)}</p>`;
        if (d.lien_vcard) inner += `<a href="${escHtml(d.lien_vcard)}" class="see-more" onclick="event.stopPropagation()">Voir plus</a>`;
    }

    return `<div class="position${hiddenClass}"
        data-id="${d.id}" data-section="${d.section}"
        data-nom="${escHtml(d.nom)}" data-sous_titre="${escHtml(d.sous_titre)}"
        data-photo="${escHtml(d.photo)}" data-vcard="${escHtml(d.lien_vcard||'')}"
        data-visible="${d.visible}" ${clickAttr}>
        ${editBtn}${inner}
    </div>`;
}

function addCardToDOM(d) {
    const selector = SECTION_SELECTORS[d.section];
    if (!selector) return;
    const container = document.querySelector(selector);
    if (!container) return;
    container.insertAdjacentHTML('beforeend', buildCard(d));
    if (d.section === 'commissions' && d.description) {
        STAFF_DESC[d.id] = d.description;
    }
}

function updateCardInDOM(d) {
    const card = document.querySelector(`.position[data-id="${d.id}"]`);
    if (!card) return;
    card.dataset.nom        = d.nom;
    card.dataset.sous_titre = d.sous_titre;
    card.dataset.photo      = d.photo;
    card.dataset.vcard      = d.lien_vcard || '';

    const img = card.querySelector('img');
    if (img) { img.src = d.photo; img.alt = d.nom; }

    const nameEl      = card.querySelector('.name');
    const catEl       = card.querySelector('.categorie');
    const nameCommEl  = card.querySelector('.name-commission');

    if (nameCommEl) nameCommEl.textContent = d.sous_titre;
    if (nameEl)    nameEl.textContent      = d.nom;
    if (catEl && card.dataset.section === 'commissions') catEl.textContent = d.nom;
    else if (catEl) catEl.innerHTML = card.dataset.section === 'entraineurs'
        ? `<u>Catégorie :</u><br>${escHtml(d.sous_titre)}`
        : escHtml(d.sous_titre);

    if (d.description) STAFF_DESC[d.id] = d.description;
}

function addDocToDOM(d) {
    const containerId = d.type === 'statuts' ? 'statuts-container' : 'pvag-container';
    const container = document.getElementById(containerId);
    if (!container) return;
    const docEl = `<button class="btn" onclick="openPdfModal('${escHtml(d.path)}','${escHtml(d.label)}')">${escHtml(d.label)}<br><u>Consulter</u></button>`;
    container.insertAdjacentHTML('afterbegin',
        `<div class="pvag-item" data-id="${d.id}">
            ${docEl}
            <div class="doc-admin-btns">
                <button class="btn-doc-move" onclick="moveDoc(this,-1)" title="Monter">▲</button>
                <button class="btn-doc-move" onclick="moveDoc(this,1)" title="Descendre">▼</button>
                <button class="btn-pvag-delete" onclick="deleteDocument(${d.id},'${d.type}')" title="Supprimer">🗑</button>
            </div>
         </div>`
    );
    _refreshMoveButtons(container);
}
