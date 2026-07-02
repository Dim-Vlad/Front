document.addEventListener('DOMContentLoaded', () => {
    loadHTML('/commun/menu.html', 'menu');
    loadHTML('/commun/footer.php', 'footer');

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeDocModal(); closeLienModal(); closeTutoModal(); closeSaisonModal();
        }
    });

    ['docModal','lienModal','tutoModal','saisonModal'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', e => {
            if (e.target === document.getElementById(id)) {
                closeDocModal(); closeLienModal(); closeTutoModal(); closeSaisonModal();
            }
        });
    });

    // Formulaire document
    document.getElementById('doc-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('doc-status');
        status.textContent = 'Enregistrement…'; status.className = 'modal-status';
        const fd = new FormData(e.target);
        try {
            const res  = await fetch('/php/licence/update_licence_document.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                updateDocInDOM(json.data);
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeDocModal, 900);
            } else {
                status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            }
        } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
    });

    // Formulaire lien
    document.getElementById('lien-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('lien-status');
        status.textContent = 'Enregistrement…'; status.className = 'modal-status';
        const fd = new FormData(e.target);
        try {
            const res  = await fetch('/php/licence/update_licence_lien.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                updateLienInDOM(json.data);
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeLienModal, 900);
            } else {
                status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            }
        } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
    });

    // Formulaire tutoriel
    document.getElementById('tuto-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('tuto-status');
        status.textContent = 'Enregistrement…'; status.className = 'modal-status';
        const fd = new FormData(e.target);
        try {
            const res  = await fetch('/php/licence/update_licence_tuto.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                updateTutoInDOM(json.num, json.titre, json.url);
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeTutoModal, 900);
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
            const res  = await fetch('/php/licence/update_licence_config.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                document.querySelectorAll('.doc-section-header h2').forEach(h => {
                    if (h.textContent.startsWith('Documents licence')) {
                        h.textContent = 'Documents licence ' + json.valeur;
                    }
                });
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeSaisonModal, 900);
            } else {
                status.textContent = json.error || 'Erreur.'; status.className = 'modal-status error';
            }
        } catch { status.textContent = 'Erreur réseau.'; status.className = 'modal-status error'; }
    });
});

// ── Modales documents ──────────────────────────────

function openDocModal(item) {
    document.getElementById('doc-id').value          = item.dataset.id;
    document.getElementById('doc-label').value       = item.dataset.label;
    document.getElementById('doc-current-path').textContent = item.dataset.path || '(aucun fichier)';
    document.getElementById('doc-file').value        = '';
    document.getElementById('doc-status').textContent = '';
    document.getElementById('doc-status').className  = 'modal-status';
    document.getElementById('docModal').classList.add('open');
}

function closeDocModal() {
    document.getElementById('docModal')?.classList.remove('open');
}

// ── Modales liens ──────────────────────────────────

function openLienModal(card) {
    document.getElementById('lien-id').value          = card.dataset.id;
    document.getElementById('lien-label').value       = card.dataset.label;
    document.getElementById('lien-url').value         = card.dataset.url;
    document.getElementById('lien-status').textContent = '';
    document.getElementById('lien-status').className  = 'modal-status';
    document.getElementById('lienModal').classList.add('open');
}

function closeLienModal() {
    document.getElementById('lienModal')?.classList.remove('open');
}

// ── Tutoriels ──────────────────────────────────────

function playTuto(thumb, videoId) {
    if (!videoId) return;
    thumb.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0"
        allowfullscreen allow="autoplay; encrypted-media" loading="lazy"></iframe>`;
}


function openTutoModal(num) {
    const card = document.querySelector(`.tuto-card[data-tuto-id="${num}"]`);
    if (!card) return;
    document.getElementById('tuto-modal-num').textContent = '#' + num;
    document.getElementById('tuto-num-input').value       = num;
    document.getElementById('tuto-titre-input').value     = card.dataset.titre;
    document.getElementById('tuto-url-input').value       = card.dataset.url;
    document.getElementById('tuto-status').textContent    = '';
    document.getElementById('tuto-status').className      = 'modal-status';
    document.getElementById('tutoModal').classList.add('open');
}

function closeTutoModal() {
    document.getElementById('tutoModal')?.classList.remove('open');
}

function updateTutoInDOM(num, titre, url) {
    const card = document.querySelector(`.tuto-card[data-tuto-id="${num}"]`);
    if (!card) return;
    card.dataset.titre = titre;
    card.dataset.url   = url;
    card.querySelector('.tuto-titre').textContent = titre;

    // Reconstruire la miniature avec la nouvelle URL
    const vid   = extractYoutubeId(url);
    const thumb = card.querySelector('.tuto-thumb');
    if (thumb) {
        if (vid) {
            thumb.setAttribute('onclick', `playTuto(this,'${vid}')`);
            thumb.innerHTML = `<img src="https://img.youtube.com/vi/${vid}/mqdefault.jpg" alt="${escHtml(titre)}" loading="lazy">
                <button class="tuto-play-btn" type="button" aria-hidden="true">▶</button>`;
        } else {
            thumb.setAttribute('onclick', '');
            thumb.innerHTML = '<div class="tuto-no-video">📹 Vidéo à configurer</div>';
        }
    }
}

// ── Modale saison ──────────────────────────────────

function openSaisonModal() {
    document.getElementById('saison-status').textContent = '';
    document.getElementById('saison-status').className  = 'modal-status';
    document.getElementById('saisonModal').classList.add('open');
}

function closeSaisonModal() {
    document.getElementById('saisonModal')?.classList.remove('open');
}

// ── Manipulation DOM ──────────────────────────────

function updateDocInDOM(data) {
    const item = document.querySelector(`.doc-item[data-id="${data.id}"]`);
    if (!item) return;
    item.dataset.label = data.label;
    item.dataset.path  = data.path;
    item.querySelector('.doc-label').textContent = data.label;
    const dl = item.querySelector('.doc-dl');
    if (dl && data.path) dl.href = data.path;
}

function updateLienInDOM(data) {
    const card = document.querySelector(`.action-card[data-id="${data.id}"]`);
    if (!card) return;
    card.dataset.label = data.label;
    card.dataset.url   = data.url;
    card.querySelector('.action-card-title').textContent = data.label;
    const btn = card.querySelector('.action-card-btn');
    if (btn) {
        if (data.url) {
            btn.href = data.url;
            btn.target = data.url.startsWith('http') ? '_blank' : '_self';
            btn.style.display = '';
        } else {
            btn.style.display = 'none';
        }
    }
}

// ── Utilitaires ───────────────────────────────────

function extractYoutubeId(url) {
    const m = (url || '').match(/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{11})/);
    return m ? m[1] : '';
}

function escHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
