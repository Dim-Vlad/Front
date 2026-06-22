document.addEventListener('DOMContentLoaded', () => {
    loadHTML('/commun/menu.html', 'menu');
    loadHTML('/commun/footer.html', 'footer');

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeDocModal(); closeLienModal(); closeVideoModal(); closeSaisonModal();
        }
    });

    ['docModal','lienModal','videoModal','saisonModal'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', e => {
            if (e.target === document.getElementById(id)) {
                closeDocModal(); closeLienModal(); closeVideoModal(); closeSaisonModal();
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
            const res  = await fetch('/php/update_licence_document.php', { method: 'POST', body: fd });
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
            const res  = await fetch('/php/update_licence_lien.php', { method: 'POST', body: fd });
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

    // Formulaire vidéo
    document.getElementById('video-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const status = document.getElementById('video-status');
        status.textContent = 'Enregistrement…'; status.className = 'modal-status';
        const fd = new FormData(e.target);
        try {
            const res  = await fetch('/php/update_licence_config.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                updateVideoInDOM(json.valeur);
                // Mettre à jour la valeur dans l'input
                document.getElementById('video-url-input').value = json.valeur;
                status.textContent = 'Enregistré ✓'; status.className = 'modal-status success';
                setTimeout(closeVideoModal, 900);
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
            const res  = await fetch('/php/update_licence_config.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                // Mettre à jour le titre de section
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

// ── Modale vidéo ───────────────────────────────────

function openVideoModal() {
    document.getElementById('video-status').textContent = '';
    document.getElementById('video-status').className  = 'modal-status';
    document.getElementById('videoModal').classList.add('open');
}

function closeVideoModal() {
    document.getElementById('videoModal')?.classList.remove('open');
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

function updateVideoInDOM(rawUrl) {
    const wrap = document.getElementById('video-wrap');
    if (!wrap) return;

    const btn = document.querySelector('.btn-edit-video');
    if (btn) btn.textContent = rawUrl ? '✏️ Changer la vidéo' : '✏️ Ajouter une vidéo';

    if (!rawUrl) {
        wrap.innerHTML = `<div class="video-placeholder" id="video-placeholder"><span>📹</span><p>La vidéo tutoriel sera disponible prochainement.</p></div>`;
        return;
    }

    const embedUrl = toEmbedUrl(rawUrl);
    wrap.innerHTML = `<iframe id="video-iframe" src="${escHtml(embedUrl)}" allowfullscreen loading="lazy" title="Tutoriel myFFvolley"></iframe>`;
}

function toEmbedUrl(url) {
    if (!url) return '';
    if (url.includes('/embed/')) return url;
    const m = url.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{11})/);
    if (m) return 'https://www.youtube.com/embed/' + m[1] + '?rel=0';
    return url;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
