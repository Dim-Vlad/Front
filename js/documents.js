// ── Utilitaires ───────────────────────────────────────────────────

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Modale PDF (visionneuse) ─────────────────────────────────────
// openPdfModal/closePdfModal/printPdf fournis par js/pdf-modal.js

// ── Modale Document (ajout PV AG / statuts) ──────────────────────

function openDocModal(type) {
    document.getElementById('doc-type').value  = type;
    document.getElementById('doc-label').value = '';
    document.getElementById('doc-fichier').value = '';
    document.getElementById('doc-status').textContent = '';

    const titles = { pvag: 'Ajouter un PV d\'AG', statuts: 'Ajouter un statut / règlement' };
    document.getElementById('doc-modal-title').textContent = titles[type] || 'Ajouter un document';

    const labels      = { pvag: 'Nom du PV', statuts: 'Nom du document' };
    const placeholders = { pvag: 'PV AG 2025-2026', statuts: 'Statuts 2025' };
    document.getElementById('doc-label-lbl').textContent = labels[type] || 'Label';
    document.getElementById('doc-label').placeholder     = placeholders[type] || '';

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

// ── Soumission formulaire ────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {

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
        if (e.key === 'Escape') { closeDocModal(); closePdfModal(); }
    });

    // Fermeture clic fond
    document.getElementById('docModal')?.addEventListener('click', e => { if (e.target.id === 'docModal') closeDocModal(); });
});
