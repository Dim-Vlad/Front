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

document.getElementById('eventModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});

/* ── Modale édition ─────────────────────────────────────────────── */

function openEditModal(cardEl) {
    const ev = JSON.parse(cardEl.dataset.ev);

    document.getElementById('edit-ev-id').value             = ev.id;
    document.getElementById('edit-ev-titre').value          = ev.titre        || '';
    document.getElementById('edit-ev-desc').value           = ev.description  || '';
    document.getElementById('edit-ev-date-debut').value     = ev.date_debut   || '';
    document.getElementById('edit-ev-date-fin').value       = ev.date_fin     || '';
    document.getElementById('edit-ev-lieu').value           = ev.lieu         || '';
    document.getElementById('edit-ev-lien').value           = ev.lien_url     || '';
    document.getElementById('edit-ev-lien-label').value     = ev.lien_label   || '';
    document.getElementById('edit-ev-termine').checked      = ev.termine == 1;
    document.getElementById('edit-ev-image-existing').value = ev.image_url    || '';
    document.getElementById('edit-ev-image').value          = '';

    const preview = document.getElementById('edit-ev-img-preview');
    const thumb   = document.getElementById('edit-ev-img-thumb');
    if (ev.image_url) {
        thumb.src = ev.image_url;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }

    document.getElementById('edit-ev-status').textContent = '';
    document.getElementById('editEventModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editEventModal').classList.remove('open');
}

document.getElementById('edit-event-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const status = document.getElementById('edit-ev-status');
    status.textContent = 'Enregistrement…';
    status.className   = 'modal-status';

    const fd = new FormData(this);
    try {
        const res  = await fetch('/php/evenements/update_evenement.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            status.textContent = data.error || 'Erreur inconnue';
            status.className   = 'modal-status error';
        }
    } catch {
        status.textContent = 'Erreur réseau';
        status.className   = 'modal-status error';
    }
});

document.getElementById('editEventModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

/* ── Réorganisation ─────────────────────────────────────────────── */

async function moveEvent(id, direction) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('direction', direction);
    try {
        const res  = await fetch('/php/evenements/reorder_evenements.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.error || 'Erreur');
    } catch {
        alert('Erreur réseau');
    }
}

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
