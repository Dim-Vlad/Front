document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeTileModal();
    });

    document.getElementById('tileModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('tileModal')) closeTileModal();
    });

    document.getElementById('tile-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('tile-status');
        status.textContent = 'Enregistrement…'; status.className = 'modal-status';
        const fd = new FormData(e.target);
        try {
            const res  = await fetch('/php/dashboard/save_tile.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(() => location.reload(), 600);
            } else {
                status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            }
        } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
    });
});

function openTileModal(item, presetSection) {
    const isEdit = !!item;
    document.getElementById('tileModalTitle').textContent = isEdit ? 'Modifier la tuile' : 'Ajouter une tuile';
    document.getElementById('tile-id').value          = isEdit ? item.dataset.id : '0';
    document.getElementById('tile-section').value     = isEdit ? item.dataset.section : (presetSection || 'jeux');
    document.getElementById('tile-icone').value       = isEdit ? item.dataset.icone : '';
    document.getElementById('tile-titre').value       = isEdit ? item.dataset.titre : '';
    document.getElementById('tile-description').value = isEdit ? item.dataset.description : '';
    document.getElementById('tile-url').value         = isEdit ? item.dataset.url : '';
    document.getElementById('tile-delete-btn').style.display = isEdit ? '' : 'none';
    document.getElementById('tile-status').textContent = '';
    document.getElementById('tile-status').className  = 'modal-status';
    document.getElementById('tileModal').classList.add('open');
}

function closeTileModal() {
    document.getElementById('tileModal')?.classList.remove('open');
}

async function deleteTile() {
    const titre = document.getElementById('tile-titre').value;
    if (!confirm('Supprimer complètement la tuile « ' + titre + ' » ?')) return;
    const status = document.getElementById('tile-status');
    status.textContent = 'Suppression…'; status.className = 'modal-status';
    const fd = new FormData();
    fd.set('id', document.getElementById('tile-id').value);
    try {
        const res  = await fetch('/php/dashboard/delete_tile.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            status.textContent = 'Supprimé ✓'; status.className = 'modal-status success';
            setTimeout(() => location.reload(), 500);
        } else {
            status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
        }
    } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
}
