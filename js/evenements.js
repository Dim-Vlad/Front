/* ── Modale ajout ──────────────────────────────────────────────── */

function openAddModal() {
    document.getElementById('event-form').reset();
    document.getElementById('ev-status').textContent = '';
    document.getElementById('eventModal').classList.add('open');
}

function closeAddModal() {
    document.getElementById('eventModal').classList.remove('open');
}

document.getElementById('event-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const status = document.getElementById('ev-status');
    status.textContent = 'Enregistrement…';
    status.className = 'modal-status';

    const fd = new FormData(this);
    try {
        const res  = await fetch('/php/evenements/add_evenement.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            status.textContent = data.error || 'Erreur inconnue';
            status.className = 'modal-status error';
        }
    } catch {
        status.textContent = 'Erreur réseau';
        status.className = 'modal-status error';
    }
});

/* ── Suppression ───────────────────────────────────────────────── */

async function deleteEvent(id) {
    if (!confirm('Supprimer cet événement ?')) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res  = await fetch('/php/evenements/delete_evenement.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.querySelector(`.ev-card[data-id="${id}"]`)?.remove();
        } else {
            alert(data.error || 'Erreur lors de la suppression');
        }
    } catch {
        alert('Erreur réseau');
    }
}

/* ── Marquer terminé / restaurer ───────────────────────────────── */

async function toggleTermine(id) {
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res  = await fetch('/php/evenements/toggle_evenement_termine.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Erreur');
        }
    } catch {
        alert('Erreur réseau');
    }
}

/* ── Fermer modale au clic extérieur ───────────────────────────── */

document.getElementById('eventModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});
