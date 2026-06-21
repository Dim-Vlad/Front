document.getElementById('infoModal').addEventListener('click', function(event) {
    if (event.target === this) closeModal();
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
        closeEditModal();
        closeAddModal();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    loadHTML('/commun/menu.html', 'menu');
    loadHTML('/commun/footer.html', 'footer');
});

// ── Modale info ──
function openModal(name, info, link, imgSrc) {
    const modal = document.getElementById('infoModal');
    document.getElementById('modal-title').textContent = name;
    document.getElementById('modal-body').textContent = info;
    document.getElementById('modal-link').href = link;
    document.getElementById('modal-image').src = imgSrc;
    modal.style.display = 'block';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeModal() {
    const modal = document.getElementById('infoModal');
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

function openModalFromCard(card) {
    const { nom, coach, lien, photo } = card.dataset;
    openModal(nom, 'Entraîneur : ' + coach, lien, photo);
}

// ── Modale édition ──
function openEditModal(card) {
    const { id, nom, coach, lien, photo, poule } = card.dataset;
    document.getElementById('edit-id').value               = id;
    document.getElementById('edit-nom-display').textContent = nom;
    document.getElementById('edit-poule').value            = poule || '';
    document.getElementById('edit-coach').value            = coach;
    document.getElementById('edit-lien').value             = lien;
    const preview = document.getElementById('edit-photo-preview');
    if (preview) {
        preview.src          = photo || '';
        preview.style.display = photo ? 'block' : 'none';
    }
    document.getElementById('edit-file').value   = '';
    document.getElementById('edit-status').textContent = '';
    cancelDeleteEquipe();
    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    const m = document.getElementById('editModal');
    if (m) m.classList.remove('show');
}

// ── Modale ajout ──
function openAddModal(groupe) {
    document.getElementById('add-groupe').value          = groupe;
    document.getElementById('add-groupe-display').textContent = groupe === 'seniors' ? 'Seniors' : 'Jeunes';
    document.getElementById('add-form').reset();
    const preview = document.getElementById('add-photo-preview');
    if (preview) { preview.src = ''; preview.style.display = 'none'; }
    document.getElementById('add-status').textContent = '';
    document.getElementById('addModal').classList.add('show');
}

function closeAddModal() {
    const m = document.getElementById('addModal');
    if (m) m.classList.remove('show');
}

// ── Suppression ──
function confirmDeleteEquipe() {
    document.getElementById('btn-delete-equipe').style.display = 'none';
    document.getElementById('delete-confirm').style.display    = 'flex';
}

function cancelDeleteEquipe() {
    const btn = document.getElementById('btn-delete-equipe');
    const confirm = document.getElementById('delete-confirm');
    if (btn)     btn.style.display     = '';
    if (confirm) confirm.style.display = 'none';
}

async function deleteEquipe() {
    const id     = document.getElementById('edit-id').value;
    const status = document.getElementById('edit-status');
    status.style.color = 'var(--secondary-color)';
    status.textContent = 'Suppression en cours…';
    try {
        const fd = new FormData();
        fd.append('id', id);
        const res  = await fetch('/php/delete_equipe.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            document.querySelector(`.team-card[data-id="${id}"]`)?.remove();
            closeEditModal();
        } else {
            status.style.color = '#e53935';
            status.textContent = json.error || 'Erreur lors de la suppression.';
            cancelDeleteEquipe();
        }
    } catch {
        status.style.color = '#e53935';
        status.textContent = 'Erreur réseau.';
        cancelDeleteEquipe();
    }
}

// ── Ajout d'une carte au DOM ──
function addCardToDOM(data) {
    const groupDivs = document.querySelectorAll('.teams-group');
    let targetGrid = null;
    groupDivs.forEach(div => {
        const title = div.querySelector('.group-title');
        if (title && title.textContent.trim().toLowerCase() === data.groupe) {
            targetGrid = div.querySelector('.teams-grid');
        }
    });
    if (!targetGrid) return;

    const esc = v => (v || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

    const card = document.createElement('div');
    card.className = 'team-card';
    card.setAttribute('role', 'button');
    card.setAttribute('tabindex', '0');
    card.dataset.id    = data.id;
    card.dataset.nom   = data.nom   || '';
    card.dataset.coach = data.coach || '';
    card.dataset.lien  = data.lien  || '';
    card.dataset.photo = data.photo || '';
    card.dataset.poule = data.niveau || '';
    card.setAttribute('onclick', 'openModalFromCard(this)');
    card.setAttribute('onkeypress', "if(event.key==='Enter') openModalFromCard(this)");
    card.innerHTML = `
        <div class="card-photo-wrap">
            <img class="card-photo" src="${esc(data.photo)}" alt="${esc(data.nom)}">
        </div>
        <div class="card-footer">
            <div class="card-name">${esc(data.nom)}</div>
            <div class="card-level">${esc(data.niveau)}</div>
        </div>
        <button class="card-edit-btn"
            onclick="event.stopPropagation(); openEditModal(this.closest('.team-card'))"
            title="Modifier cette équipe"
            aria-label="Modifier ${esc(data.nom)}">✏️</button>`;
    targetGrid.appendChild(card);
}

document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('editModal');
    const addModal  = document.getElementById('addModal');
    if (!editModal) return;

    editModal.addEventListener('click', e => { if (e.target === editModal) closeEditModal(); });
    addModal?.addEventListener('click',  e => { if (e.target === addModal)  closeAddModal();  });

    // Aperçu photo — édition
    document.getElementById('edit-file')?.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            const p = document.getElementById('edit-photo-preview');
            p.src = ev.target.result;
            p.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    // Aperçu photo — ajout
    document.getElementById('add-file')?.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            const p = document.getElementById('add-photo-preview');
            p.src = ev.target.result;
            p.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    // Soumission — édition
    document.getElementById('edit-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = this.querySelector('.btn-save');
        const status    = document.getElementById('edit-status');
        submitBtn.disabled = true;
        status.style.color = 'var(--secondary-color)';
        status.textContent = 'Enregistrement en cours…';
        try {
            const res  = await fetch('/php/update_equipe.php', { method: 'POST', body: new FormData(this) });
            const json = await res.json();
            if (json.success) {
                status.textContent = 'Modifications enregistrées !';
                const id   = document.getElementById('edit-id').value;
                const card = document.querySelector(`.team-card[data-id="${id}"]`);
                if (card) {
                    const { poule, coach, lien, photo } = json.data;
                    card.dataset.poule = poule;
                    card.dataset.coach = coach;
                    card.dataset.lien  = lien;
                    card.querySelector('.card-level').textContent = poule;
                    if (photo) {
                        card.dataset.photo = photo;
                        card.querySelector('.card-photo').src = photo;
                    }
                }
                setTimeout(() => closeEditModal(), 1200);
            } else {
                status.style.color = '#e53935';
                status.textContent = json.error || "Erreur lors de l'enregistrement.";
            }
        } catch {
            status.style.color = '#e53935';
            status.textContent = 'Erreur réseau.';
        }
        submitBtn.disabled = false;
    });

    // Soumission — ajout
    document.getElementById('add-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = this.querySelector('.btn-save');
        const status    = document.getElementById('add-status');
        submitBtn.disabled = true;
        status.style.color = 'var(--secondary-color)';
        status.textContent = 'Ajout en cours…';
        try {
            const res  = await fetch('/php/add_equipe.php', { method: 'POST', body: new FormData(this) });
            const json = await res.json();
            if (json.success) {
                status.textContent = 'Équipe ajoutée !';
                addCardToDOM(json.data);
                setTimeout(() => closeAddModal(), 1000);
            } else {
                status.style.color = '#e53935';
                status.textContent = json.error || "Erreur lors de l'ajout.";
            }
        } catch {
            status.style.color = '#e53935';
            status.textContent = 'Erreur réseau.';
        }
        submitBtn.disabled = false;
    });
});
