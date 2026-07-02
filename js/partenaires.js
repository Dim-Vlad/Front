document.addEventListener('DOMContentLoaded', () => {
    loadHTML('/commun/menu.html', 'menu');
    loadHTML('/commun/footer.php', 'footer');

    // Fermeture modales sur Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeEditModal();
            closeAddModal();
        }
    });

    // Fermeture modales sur clic backdrop
    document.getElementById('editModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('editModal')) closeEditModal();
    });
    document.getElementById('addModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('addModal')) closeAddModal();
    });

    // Formulaire édition
    document.getElementById('edit-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('edit-status');
        status.textContent = 'Enregistrement…';
        status.className = 'modal-status';
        const fd = new FormData(e.target);
        try {
            const res = await fetch('/php/partenaires/update_partenaire.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                updateCardInDOM(json.data);
                status.textContent = 'Enregistré ✓';
                status.className = 'modal-status success';
                setTimeout(closeEditModal, 900);
            } else {
                status.textContent = json.error || 'Erreur.';
                status.className = 'modal-status error';
            }
        } catch {
            status.textContent = 'Erreur réseau.';
            status.className = 'modal-status error';
        }
    });

    // Formulaire ajout
    document.getElementById('add-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('add-status');
        status.textContent = 'Ajout en cours…';
        status.className = 'modal-status';
        const fd = new FormData(e.target);
        try {
            const res = await fetch('/php/partenaires/add_partenaire.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                addCardToDOM(json.data);
                status.textContent = 'Partenaire ajouté ✓';
                status.className = 'modal-status success';
                setTimeout(closeAddModal, 900);
            } else {
                status.textContent = json.error || 'Erreur.';
                status.className = 'modal-status error';
            }
        } catch {
            status.textContent = 'Erreur réseau.';
            status.className = 'modal-status error';
        }
    });

    // Formulaire dossier PDF
    document.getElementById('dossier-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('dossier-status');
        const btn = e.target.querySelector('button[type="submit"]');
        status.textContent = 'Envoi…';
        status.className = 'dossier-status';
        btn.disabled = true;
        const fd = new FormData(e.target);
        try {
            const res = await fetch('/php/licence/update_dossier.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                status.textContent = 'Dossier mis à jour ✓';
                status.className = 'dossier-status success';
                e.target.reset();
            } else {
                status.textContent = json.error || 'Erreur.';
                status.className = 'dossier-status error';
            }
        } catch {
            status.textContent = 'Erreur réseau.';
            status.className = 'dossier-status error';
        } finally {
            btn.disabled = false;
        }
    });

    // Aperçu photo edit
    document.getElementById('edit-logo-file')?.addEventListener('change', function () {
        previewImage(this, 'edit-logo-preview');
    });

    // Aperçu photo add
    document.getElementById('add-logo-file')?.addEventListener('change', function () {
        previewImage(this, 'add-logo-preview');
    });
});

// ── Gestion modales ──────────────────────────────

const categoryLabels = {
    partenaire:    'Partenaires',
    institutionnel: 'Institutions & Collectivités',
    federation:    'Fédérations',
};

function openEditModal(card) {
    document.getElementById('edit-id').value          = card.dataset.id;
    document.getElementById('edit-nom').value         = card.dataset.nom;
    document.getElementById('edit-url').value         = card.dataset.url;
    document.getElementById('edit-nom-display').textContent = card.dataset.nom;

    const selCat = document.getElementById('edit-categorie');
    if (selCat) selCat.value = card.dataset.categorie;

    const preview = document.getElementById('edit-logo-preview');
    if (card.dataset.logo) {
        preview.src = card.dataset.logo;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }

    // Reset delete zone
    document.getElementById('btn-delete-partner').style.display = 'block';
    document.getElementById('delete-confirm').style.display     = 'none';

    document.getElementById('edit-status').textContent = '';
    document.getElementById('edit-status').className   = 'modal-status';
    document.getElementById('edit-logo-file').value    = '';

    document.getElementById('editModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal')?.classList.remove('open');
}

function openAddModal(categorie) {
    document.getElementById('add-categorie').value = categorie;
    document.getElementById('add-categorie-display').textContent = categoryLabels[categorie] || categorie;
    document.getElementById('add-nom').value  = '';
    document.getElementById('add-url').value  = '';
    document.getElementById('add-logo-file').value = '';

    const preview = document.getElementById('add-logo-preview');
    preview.style.display = 'none';
    preview.src = '';

    document.getElementById('add-status').textContent = '';
    document.getElementById('add-status').className   = 'modal-status';

    document.getElementById('addModal').classList.add('open');
}

function closeAddModal() {
    document.getElementById('addModal')?.classList.remove('open');
}

// ── Suppression deux étapes ──────────────────────

function confirmDeletePartner() {
    document.getElementById('btn-delete-partner').style.display = 'none';
    document.getElementById('delete-confirm').style.display     = 'flex';
}

function cancelDeletePartner() {
    document.getElementById('btn-delete-partner').style.display = 'block';
    document.getElementById('delete-confirm').style.display     = 'none';
}

async function deletePartner() {
    const id = document.getElementById('edit-id').value;
    const status = document.getElementById('edit-status');
    status.textContent = 'Suppression…';
    status.className = 'modal-status';

    try {
        const fd = new FormData();
        fd.append('id', id);
        const res  = await fetch('/php/partenaires/delete_partenaire.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            removeCardFromDOM(id);
            closeEditModal();
        } else {
            status.textContent = json.error || 'Erreur lors de la suppression.';
            status.className = 'modal-status error';
            cancelDeletePartner();
        }
    } catch {
        status.textContent = 'Erreur réseau.';
        status.className = 'modal-status error';
        cancelDeletePartner();
    }
}

// ── Manipulation DOM ─────────────────────────────

function buildCard(data) {
    const logoHtml = data.logo
        ? `<img class="partner-logo" src="${escHtml(data.logo)}" alt="${escHtml(data.nom)}">`
        : `<div class="partner-logo-placeholder">${escHtml(data.nom[0] || '?')}</div>`;

    const linkHtml = data.url
        ? `<a class="partner-link" href="${escHtml(data.url)}" target="_blank" rel="noopener" onclick="event.stopPropagation()">Visiter →</a>`
        : '';

    const div = document.createElement('div');
    div.className = 'partner-card';
    div.dataset.id        = data.id;
    div.dataset.nom       = data.nom;
    div.dataset.url       = data.url || '';
    div.dataset.logo      = data.logo || '';
    div.dataset.categorie = data.categorie;
    div.innerHTML = `
        <div class="partner-logo-wrap">${logoHtml}</div>
        <div class="partner-footer">
            <span class="partner-name">${escHtml(data.nom)}</span>
            ${linkHtml}
        </div>
        <button class="partner-edit-btn"
            onclick="event.stopPropagation(); openEditModal(this.closest('.partner-card'))"
            title="Modifier ce partenaire">✏️</button>
    `;
    return div;
}

function addCardToDOM(data) {
    // Trouver la grille de la bonne catégorie
    const sections = document.querySelectorAll('.partners-section');
    for (const section of sections) {
        const btn = section.querySelector('.btn-add-partner');
        if (btn && btn.getAttribute('onclick').includes(`'${data.categorie}'`)) {
            const grid = section.querySelector('.partners-grid');
            // Supprimer le placeholder vide s'il existe
            const empty = grid.querySelector('.partners-empty');
            if (empty) empty.remove();
            grid.appendChild(buildCard(data));
            return;
        }
    }
}

function updateCardInDOM(data) {
    const card = document.querySelector(`.partner-card[data-id="${data.id}"]`);
    if (!card) return;

    card.dataset.nom       = data.nom;
    card.dataset.url       = data.url || '';
    card.dataset.logo      = data.logo || '';
    card.dataset.categorie = data.categorie;

    const logoWrap = card.querySelector('.partner-logo-wrap');
    logoWrap.innerHTML = data.logo
        ? `<img class="partner-logo" src="${escHtml(data.logo)}" alt="${escHtml(data.nom)}">`
        : `<div class="partner-logo-placeholder">${escHtml(data.nom[0] || '?')}</div>`;

    const footer = card.querySelector('.partner-footer');
    footer.innerHTML = `
        <span class="partner-name">${escHtml(data.nom)}</span>
        ${data.url ? `<a class="partner-link" href="${escHtml(data.url)}" target="_blank" rel="noopener" onclick="event.stopPropagation()">Visiter →</a>` : ''}
    `;
}

function removeCardFromDOM(id) {
    document.querySelector(`.partner-card[data-id="${id}"]`)?.remove();
}

// ── Utilitaires ──────────────────────────────────

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
