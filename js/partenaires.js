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
                status.textContent = 'Enregistré ✓';
                status.className = 'modal-status success';
                // Rechargement : la mise en avant peut déplacer la carte vers/hors de la section "en vedette".
                setTimeout(() => location.reload(), 700);
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
                status.textContent = 'Partenaire ajouté ✓';
                status.className = 'modal-status success';
                setTimeout(() => location.reload(), 700);
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

    document.getElementById('edit-mis-en-avant').checked = card.dataset.misEnAvant === '1';
    document.getElementById('edit-date-fin').value       = card.dataset.dateFin || '';
    document.getElementById('edit-description').value    = card.dataset.description || '';
    toggleFeaturedFields('edit');

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
    document.getElementById('add-mis-en-avant').checked = false;
    document.getElementById('add-date-fin').value       = '';
    document.getElementById('add-description').value    = '';
    document.getElementById('add-logo-file').value = '';
    toggleFeaturedFields('add');

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

// La fin de mise en avant et le texte de présentation n'ont de sens
// que si la case "mis en avant" est cochée.
function toggleFeaturedFields(prefix) {
    const checked = document.getElementById(prefix + '-mis-en-avant').checked;
    document.getElementById(prefix + '-date-fin-group').style.display     = checked ? '' : 'none';
    document.getElementById(prefix + '-description-group').style.display = checked ? '' : 'none';
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

function removeCardFromDOM(id) {
    // Un partenaire "en vedette" a deux cartes dans la page (la section vedette
    // et sa catégorie) : on les retire toutes les deux.
    document.querySelectorAll(`.partner-card[data-id="${id}"]`).forEach(el => el.remove());
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
