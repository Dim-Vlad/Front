// Ajout d'un écouteur pour fermer la modale si on clique à l'extérieur du contenu
document.getElementById('infoModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeModal();
    }
});

// Optionnel: fermeture de la modale en appuyant sur la touche "Échap"
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    loadHTML('/commun/menu.html', 'menu');
    loadHTML('/commun/footer.html', 'footer');
});

function openModal(name, info, link, imgSrc) {
    const modal = document.getElementById('infoModal');
    const modalContent = modal.querySelector('.modal-content');
    
    document.getElementById('modal-title').textContent = name;
    document.getElementById('modal-body').textContent = info;
    document.getElementById('modal-link').href = link;
    document.getElementById('modal-image').src = imgSrc;

    modal.style.display = 'block';

    // Déclenche l'effet d'ouverture en ajoutant la classe 'show' après un court délai
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

// Fonction pour fermer la modale
function closeModal(event) {
    const modal = document.getElementById('infoModal');

    // Retirer la classe 'show' pour commencer l'animation de fermeture
    modal.classList.remove('show');

    // Attendre la fin de l'animation avant de cacher complètement la modale
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300); // Ce délai doit correspondre à la durée de l'animation CSS
}

// Ajoute un écouteur d'événements pour fermer la modale avec Échap
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal(event);
    }
});

// ── Ouverture modale info depuis les data-attributes de la carte ──
function openModalFromCard(card) {
    const { nom, coach, lien, photo } = card.dataset;
    openModal(nom, 'Entraîneur : ' + coach, lien, photo);
}

// ── Modale d'édition (moderateur/admin) ──
function openEditModal(card) {
    const { id, nom, coach, lien, photo } = card.dataset;
    document.getElementById('edit-id').value              = id;
    document.getElementById('edit-nom-display').textContent = nom;
    document.getElementById('edit-coach').value           = coach;
    document.getElementById('edit-lien').value            = lien;
    const preview = document.getElementById('edit-photo-preview');
    if (preview) {
        preview.src          = photo || '';
        preview.style.display = photo ? 'block' : 'none';
    }
    const fileInput = document.getElementById('edit-file');
    if (fileInput) fileInput.value = '';
    document.getElementById('edit-status').textContent = '';
    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    const m = document.getElementById('editModal');
    if (m) m.classList.remove('show');
}

document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('editModal');
    if (!editModal) return;

    editModal.addEventListener('click', e => { if (e.target === editModal) closeEditModal(); });

    // Aperçu photo avant upload
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

    // Soumission AJAX
    document.getElementById('edit-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = this.querySelector('.btn-save');
        const status    = document.getElementById('edit-status');
        submitBtn.disabled  = true;
        status.style.color  = 'var(--secondary-color)';
        status.textContent  = 'Enregistrement en cours…';

        try {
            const res  = await fetch('/php/update_equipe.php', { method: 'POST', body: new FormData(this) });
            const json = await res.json();
            if (json.success) {
                status.textContent = 'Modifications enregistrées !';
                const id   = document.getElementById('edit-id').value;
                const card = document.querySelector(`.team-card[data-id="${id}"]`);
                if (card) {
                    const { coach, lien, photo } = json.data;
                    card.dataset.coach = coach;
                    card.dataset.lien  = lien;
                    if (photo) {
                        card.dataset.photo = photo;
                        card.querySelector('.card-photo').src = photo;
                    }
                }
                setTimeout(() => closeEditModal(), 1200);
            } else {
                status.style.color = '#e53935';
                status.textContent  = json.error || "Erreur lors de l'enregistrement.";
            }
        } catch {
            status.style.color = '#e53935';
            status.textContent  = 'Erreur réseau.';
        }
        submitBtn.disabled = false;
    });
});
