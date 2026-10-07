'use strict';

let _vPreviewUrl = null;

function _vClearPreview() {
    if (_vPreviewUrl) { URL.revokeObjectURL(_vPreviewUrl); _vPreviewUrl = null; }
    const preview = document.getElementById('v-file-preview');
    preview.style.display = 'none';
    preview.removeAttribute('src');
}

function openVideoModal() {
    document.getElementById('videoForm').reset();
    document.getElementById('v-status').textContent = '';
    document.getElementById('v-file-label').textContent = '📁 Cliquer pour choisir un fichier';
    _vClearPreview();
    document.querySelector('input[name="v-kind"][value="video"]').checked = true;
    toggleVideoKind();
    document.getElementById('videoModal').classList.add('open');
}

function closeVideoModal() {
    document.getElementById('videoModal').classList.remove('open');
    _vClearPreview();
}

function toggleVideoKind() {
    const kind = document.querySelector('input[name="v-kind"]:checked').value;
    document.getElementById('vUrlGroup').style.display  = kind === 'video' ? '' : 'none';
    document.getElementById('vFileGroup').style.display = kind === 'video' ? 'none' : '';
    const file = document.getElementById('v-file');
    const hint = document.getElementById('v-file-hint');
    const text = document.getElementById('v-file-text');
    file.value = '';
    document.getElementById('v-file-label').textContent = '📁 Cliquer pour choisir un fichier';
    _vClearPreview();
    if (kind === 'image') {
        file.accept = '.jpg,.jpeg,.png,.webp,.gif';
        hint.textContent = 'Image : jpg, png, webp ou gif (5 Mo max)';
        text.textContent = 'Image';
    } else if (kind === 'pdf') {
        file.accept = '.pdf';
        hint.textContent = 'Fichier PDF (10 Mo max)';
        text.textContent = 'Fichier PDF';
    }
}

function updateVideoFileLabel() {
    const file  = document.getElementById('v-file').files[0];
    const label = document.getElementById('v-file-label');
    label.textContent = file ? `📄 ${file.name}` : '📁 Cliquer pour choisir un fichier';

    const kind = document.querySelector('input[name="v-kind"]:checked').value;
    const preview = document.getElementById('v-file-preview');
    if (kind === 'image' && file) {
        if (_vPreviewUrl) URL.revokeObjectURL(_vPreviewUrl);
        _vPreviewUrl = URL.createObjectURL(file);
        preview.src = _vPreviewUrl;
        preview.style.display = 'block';
    } else {
        _vClearPreview();
    }
}

async function submitVideo(e) {
    e.preventDefault();
    const status = document.getElementById('v-status');
    const kind   = document.querySelector('input[name="v-kind"]:checked').value;

    if (kind === 'video' && !document.getElementById('v-url').value.trim()) {
        status.textContent = 'Le lien de la vidéo est requis.';
        return;
    }
    if (kind !== 'video' && !document.getElementById('v-file').files[0]) {
        status.textContent = 'Veuillez choisir un fichier.';
        return;
    }

    status.textContent = 'Enregistrement…';

    const fd = new FormData();
    fd.append('type', kind);
    fd.append('titre', document.getElementById('v-titre').value.trim());
    fd.append('description', document.getElementById('v-description').value.trim());
    if (kind === 'video') {
        fd.append('url', document.getElementById('v-url').value.trim());
    } else {
        fd.append('fichier', document.getElementById('v-file').files[0]);
    }

    try {
        const res  = await fetch('/php/espace-entraineur/save_video.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Erreur inconnue');
        location.reload();
    } catch (err) {
        status.textContent = err.message;
    }
}

// ── Visionneuse plein écran pour les images partagées ──
// Reprend le style (classes .lb-*) de la lightbox de la galerie, déjà
// chargé par styles.css sur toutes les pages, mais avec sa propre logique
// pour ne jamais toucher aux liens vidéo (YouTube, etc.) du reste de la page.
let _vidLbPhotos = [];
let _vidLbIdx    = 0;
let _vidLbTouchX = null;

function openImageLightbox(url, e) {
    if (e) e.preventDefault();
    _vidLbPhotos = Array.from(document.querySelectorAll('.vid-thumb')).map(a => {
        const img = a.querySelector('img');
        return { src: a.getAttribute('href'), alt: img ? img.alt : '' };
    });
    _vidLbIdx = _vidLbPhotos.findIndex(p => p.src === url);
    if (_vidLbIdx < 0) _vidLbIdx = 0;
    _vidLbOpen();
    return false;
}

function _vidLbOpen() {
    if (document.getElementById('lb-overlay')) document.getElementById('lb-overlay').remove();

    const overlay = document.createElement('div');
    overlay.id = 'lb-overlay';
    overlay.innerHTML =
        '<button class="lb-close" id="lb-close" aria-label="Fermer">✕</button>'
        + '<button class="lb-nav lb-prev" id="lb-prev" aria-label="Photo précédente">‹</button>'
        + '<div class="lb-img-wrap" id="lb-img-wrap"><img id="lb-img" src="" alt=""></div>'
        + '<button class="lb-nav lb-next" id="lb-next" aria-label="Photo suivante">›</button>'
        + '<div class="lb-counter" id="lb-counter"></div>';

    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';

    document.getElementById('lb-close').addEventListener('click', () => _vidLbClose(false));
    document.getElementById('lb-prev').addEventListener('click', _vidLbPrev);
    document.getElementById('lb-next').addEventListener('click', _vidLbNext);
    overlay.addEventListener('click', e => { if (e.target === overlay) _vidLbClose(false); });
    document.addEventListener('keydown', _vidLbKey);

    const wrap = document.getElementById('lb-img-wrap');
    wrap.addEventListener('touchstart', e => { _vidLbTouchX = e.touches[0].clientX; }, { passive: true });
    wrap.addEventListener('touchend', e => {
        if (_vidLbTouchX === null) return;
        const dx = e.changedTouches[0].clientX - _vidLbTouchX;
        _vidLbTouchX = null;
        if (Math.abs(dx) > 45) { if (dx < 0) _vidLbNext(); else _vidLbPrev(); }
    }, { passive: true });

    requestAnimationFrame(() => overlay.classList.add('lb-visible'));
    _vidLbShow();
}

function _vidLbClose(instant) {
    const overlay = document.getElementById('lb-overlay');
    if (!overlay) return;
    document.removeEventListener('keydown', _vidLbKey);
    document.body.style.overflow = '';
    if (instant) { overlay.remove(); return; }
    overlay.classList.remove('lb-visible');
    setTimeout(() => { if (overlay.parentNode) overlay.remove(); }, 250);
}

function _vidLbShow() {
    const p   = _vidLbPhotos[_vidLbIdx];
    const img = document.getElementById('lb-img');
    const ctr = document.getElementById('lb-counter');
    if (!img || !p) return;

    img.style.opacity = '0';
    img.onload = () => { img.style.transition = 'opacity .25s'; img.style.opacity = '1'; };
    img.src = p.src;
    img.alt = p.alt;

    if (ctr) ctr.textContent = (_vidLbIdx + 1) + ' / ' + _vidLbPhotos.length;

    const prev = document.getElementById('lb-prev');
    const next = document.getElementById('lb-next');
    if (prev) prev.style.visibility = _vidLbIdx > 0                         ? 'visible' : 'hidden';
    if (next) next.style.visibility = _vidLbIdx < _vidLbPhotos.length - 1   ? 'visible' : 'hidden';
}

function _vidLbNext() { if (_vidLbIdx < _vidLbPhotos.length - 1) { _vidLbIdx++; _vidLbShow(); } }
function _vidLbPrev() { if (_vidLbIdx > 0)                       { _vidLbIdx--; _vidLbShow(); } }

function _vidLbKey(e) {
    if      (e.key === 'ArrowRight') _vidLbNext();
    else if (e.key === 'ArrowLeft')  _vidLbPrev();
    else if (e.key === 'Escape')     _vidLbClose(false);
}

async function deleteVideo(id, titre) {
    if (!confirm(`Supprimer « ${titre} » ?`)) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res  = await fetch('/php/espace-entraineur/delete_video.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.error);
        location.reload();
    } catch (err) {
        alert('Erreur : ' + err.message);
    }
}
