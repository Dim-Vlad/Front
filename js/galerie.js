/* ── Galerie VBO – gestion dynamique + panneau admin ─────────────── */

let _galSaisonActive = null;
let _galIsAdmin      = false;

document.addEventListener('DOMContentLoaded', function () {
    fetch('/php/session_status.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var roles = data.roles || [];
            _galIsAdmin = data.logged_in && (roles.includes('admin') || roles.includes('moderateur'));
            if (_galIsAdmin) {
                injectAdminBar();
                loadPendingCount();
            }
            loadSaisonActive();
            loadArchives();
        })
        .catch(function () {
            loadSaisonActive();
            loadArchives();
        });

    initSubmitForm();
});

/* ── Saison active ───────────────────────────────────────────────── */

function loadSaisonActive() {
    fetch('/php/galerie/get_saison_active.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            _galSaisonActive = data.saison;
            renderSaisonActive(data.saison, data.photos);
        })
        .catch(function () {});
}

function renderSaisonActive(saison, photos) {
    var titleEl = document.getElementById('gal-saison-title');
    var gridEl  = document.getElementById('gal-active-photos');
    if (!titleEl || !gridEl) return;

    if (!saison) {
        titleEl.textContent = 'Saison en cours';
        gridEl.innerHTML = _galIsAdmin
            ? '<p class="gal-empty">Aucune saison active. Cliquez sur <strong>+ Nouvelle saison</strong> pour en créer une.</p>'
            : '<p class="gal-empty">La saison sera bientôt disponible.</p>';
        return;
    }

    titleEl.textContent = 'Saison ' + saison.label;

    var html = '';
    photos.forEach(function (photo) {
        var src = '/' + photo.filepath;
        html += '<a href="' + src + '" target="_blank">';
        html += '<img src="' + src + '" alt="' + _he(photo.alt_text || '') + '">';
        if (_galIsAdmin) {
            html += '<button class="gal-photo-delete" onclick="event.preventDefault();event.stopPropagation();galDeletePhoto(' + photo.id + ',this)" title="Supprimer">✕</button>';
        }
        html += '</a>';
    });

    if (saison.flickr_url) {
        html += '<a href="' + saison.flickr_url + '" target="_blank" class="gal-more-tile">'
              + '<span class="gal-more-arrow">→</span>'
              + '<span class="gal-more-label">Voir plus sur Flickr</span>'
              + '</a>';
    }

    if (!html) {
        html = '<p class="gal-empty">' + (_galIsAdmin ? 'Aucune photo publiée. Utilisez <strong>⬆ Ajouter des photos</strong>.' : 'Photos à venir.') + '</p>';
    }

    gridEl.innerHTML = html;
}

/* ── Archives ────────────────────────────────────────────────────── */

function loadArchives() {
    fetch('/php/galerie/get_archives.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            renderArchives(data.saisons);
        })
        .catch(function () {});
}

function renderArchives(saisons) {
    var navEl = document.getElementById('gal-archive-nav');
    if (!navEl) return;

    var html = '';
    saisons.forEach(function (s) {
        html += '<a href="/pages/galerie/galerie-saison.php?slug=' + encodeURIComponent(s.slug) + '" class="archive-card">'
              + '<span class="archive-card-icon">📸</span>'
              + '<span class="archive-card-year">' + _he(s.label) + '</span>'
              + '<span class="archive-card-label">Voir la galerie archivée</span>'
              + '</a>';
    });

    var evenCard = navEl.querySelector('.archive-card-evenement');
    navEl.innerHTML = html;
    if (evenCard) navEl.appendChild(evenCard);
}

/* ── Formulaire de soumission parent ─────────────────────────────── */

function initSubmitForm() {
    var form = document.getElementById('gal-submit-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('.gal-btn-submit');
        var msg = document.getElementById('gal-form-msg');
        btn.disabled    = true;
        btn.textContent = 'Envoi en cours…';
        if (msg) { msg.className = ''; msg.textContent = ''; }

        var fd = new FormData(form);
        fetch('/php/galerie/submit_photo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    if (msg) {
                        msg.className   = 'gal-form-success';
                        msg.textContent = data.count + ' photo(s) envoyée(s) ! Un modérateur les examinera avant publication.';
                    }
                    form.reset();
                    if (_galIsAdmin) loadPendingCount();
                } else {
                    if (msg) {
                        msg.className   = 'gal-form-error';
                        msg.textContent = data.error || 'Une erreur est survenue.';
                    }
                }
            })
            .catch(function () {
                if (msg) { msg.className = 'gal-form-error'; msg.textContent = 'Erreur réseau, veuillez réessayer.'; }
            })
            .finally(function () {
                btn.disabled    = false;
                btn.textContent = 'Envoyer mes photos';
            });
    });
}

/* ── Admin : suppression photo depuis la grille ──────────────────── */

function galDeletePhoto(photoId, btn) {
    if (!confirm('Supprimer cette photo définitivement ?')) return;
    var tile = btn.closest('a');
    var fd   = new FormData();
    fd.append('photo_id', photoId);
    fetch('/php/galerie/delete_photo.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                tile.style.transition = 'opacity .3s';
                tile.style.opacity    = '0';
                setTimeout(function () { tile.remove(); }, 300);
            } else {
                alert(data.error || 'Erreur lors de la suppression');
            }
        });
}

/* ── Admin : barre d'outils ──────────────────────────────────────── */

function injectAdminBar() {
    var bar = document.createElement('div');
    bar.id        = 'gal-admin-bar';
    bar.className = 'gal-admin-bar';
    bar.innerHTML =
        '<span class="gal-admin-label">⚙ Administration galerie</span>'
        + '<button class="gal-admin-btn" onclick="openModalCreerSaison()">+ Nouvelle saison</button>'
        + '<button class="gal-admin-btn" onclick="openModalUpload()">⬆ Ajouter des photos</button>'
        + '<button class="gal-admin-btn gal-admin-btn-mod" id="gal-btn-mod" onclick="openModalModeration()">'
        + '🔍 Photos en attente <span id="gal-pending-badge" class="gal-pending-badge" style="display:none">0</span>'
        + '</button>';

    var main = document.querySelector('.gal-main');
    if (main) main.insertBefore(bar, main.firstChild);
}

function loadPendingCount() {
    fetch('/php/galerie/get_pending.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var badge = document.getElementById('gal-pending-badge');
            if (badge && data.success) {
                badge.textContent    = data.count;
                badge.style.display  = data.count > 0 ? 'inline-flex' : 'none';
            }
        });
}

/* ── Modal : créer une saison ────────────────────────────────────── */

function openModalCreerSaison() {
    _galOpenModal(
        '<h3>Créer une nouvelle saison</h3>'
        + '<div class="gal-mfrow"><label>Label <span class="gal-req">*</span></label>'
        + '<input type="text" id="gal-s-label" placeholder="Ex : 2026-2027" maxlength="20"></div>'
        + '<div class="gal-mfrow"><label>Slug <span class="gal-opt">(auto-généré)</span></label>'
        + '<input type="text" id="gal-s-slug" placeholder="Ex : saison-26-27" maxlength="60"></div>'
        + '<div class="gal-mfrow"><label>URL Flickr <span class="gal-opt">(optionnel)</span></label>'
        + '<input type="url" id="gal-s-flickr" placeholder="https://flic.kr/…"></div>'
        + '<div class="gal-mfrow gal-mfcheck">'
        + '<label><input type="checkbox" id="gal-s-activer" checked> Activer immédiatement (archive la saison en cours)</label>'
        + '</div>'
        + '<div id="gal-s-msg" class="gal-mfmsg"></div>'
        + '<button class="gal-admin-btn gal-btn-full" onclick="submitCreerSaison()">Créer la saison</button>'
    );

    document.getElementById('gal-s-label').addEventListener('input', function () {
        var slug = 'saison-' + this.value.trim().replace(/[^0-9]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
        document.getElementById('gal-s-slug').placeholder = slug;
    });
}

function submitCreerSaison() {
    var label   = document.getElementById('gal-s-label').value.trim();
    var slug    = document.getElementById('gal-s-slug').value.trim();
    var flickr  = document.getElementById('gal-s-flickr').value.trim();
    var activer = document.getElementById('gal-s-activer').checked;
    var msg     = document.getElementById('gal-s-msg');

    if (!label) { msg.className = 'gal-form-error'; msg.textContent = 'Le label est requis.'; return; }

    var fd = new FormData();
    fd.append('label', label);
    fd.append('slug', slug);
    fd.append('flickr_url', flickr);
    if (activer) fd.append('activer', '1');

    fetch('/php/galerie/create_saison.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                msg.className   = 'gal-form-success';
                msg.textContent = 'Saison créée !' + (data.active ? ' Elle est maintenant active.' : '');
                setTimeout(function () { _galCloseModal(); loadSaisonActive(); loadArchives(); }, 1500);
            } else {
                msg.className   = 'gal-form-error';
                msg.textContent = data.error || 'Erreur lors de la création.';
            }
        });
}

/* ── Modal : upload photos admin ─────────────────────────────────── */

function openModalUpload() {
    var info = _galSaisonActive
        ? '<p class="gal-minfo">Saison active : <strong>' + _he(_galSaisonActive.label) + '</strong></p>'
        : '<p class="gal-mwarn">⚠ Aucune saison active. Créez-en une d\'abord.</p>';

    _galOpenModal(
        '<h3>Ajouter des photos</h3>'
        + info
        + '<div class="gal-mfrow"><label>Photos <span class="gal-req">*</span> <span class="gal-opt">(max 30 · 15 Mo/photo · JPG/PNG/WEBP)</span></label>'
        + '<input type="file" id="gal-up-files" accept="image/jpeg,image/png,image/webp" multiple></div>'
        + '<div id="gal-up-msg" class="gal-mfmsg"></div>'
        + '<button class="gal-admin-btn gal-btn-full" onclick="submitUpload()" '
        + (_galSaisonActive ? '' : 'disabled') + '>⬆ Uploader</button>'
    );
}

function submitUpload() {
    var fileInput = document.getElementById('gal-up-files');
    var msg       = document.getElementById('gal-up-msg');
    if (!fileInput.files.length) { msg.className = 'gal-form-error'; msg.textContent = 'Sélectionnez au moins une photo.'; return; }

    var fd = new FormData();
    for (var i = 0; i < fileInput.files.length; i++) fd.append('photos[]', fileInput.files[i]);

    msg.className   = '';
    msg.textContent = 'Upload en cours…';

    fetch('/php/galerie/upload_photo.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.count > 0) {
                msg.className   = 'gal-form-success';
                msg.textContent = data.count + ' photo(s) publiée(s) !';
                setTimeout(function () { _galCloseModal(); loadSaisonActive(); }, 1500);
            } else {
                msg.className   = 'gal-form-error';
                msg.textContent = data.error || ('Aucune photo valide.' + (data.errors ? ' ' + data.errors.join(' / ') : ''));
            }
        });
}

/* ── Modal : modération ──────────────────────────────────────────── */

function openModalModeration() {
    _galOpenModal(
        '<h3>Photos en attente de modération</h3>'
        + '<div id="gal-mod-content"><p class="gal-empty">Chargement…</p></div>'
    );

    fetch('/php/galerie/get_pending.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var el = document.getElementById('gal-mod-content');
            if (!el) return;
            if (!data.success || !data.photos.length) {
                el.innerHTML = '<p class="gal-empty">Aucune photo en attente. ✓</p>';
                return;
            }
            var html = '<div class="gal-mod-grid">';
            data.photos.forEach(function (p) {
                var src = '/' + p.filepath;
                html += '<div class="gal-mod-card" id="gal-mc-' + p.id + '">'
                      + '<a href="' + src + '" target="_blank"><img src="' + src + '" alt="" class="gal-mod-thumb"></a>'
                      + '<div class="gal-mod-info">'
                      + '<strong>' + _he(p.soumise_par_nom || 'Anonyme') + '</strong>'
                      + (p.categorie ? ' · ' + _he(p.categorie) : '')
                      + (p.legende   ? '<br><em>' + _he(p.legende) + '</em>' : '')
                      + '<br><small>' + _he(p.saison_label || '') + ' · ' + _fmtDate(p.uploaded_at) + '</small>'
                      + '</div>'
                      + '<div class="gal-mod-actions" id="gal-act-' + p.id + '">'
                      + '<button class="gal-mod-btn gal-mod-ok" onclick="galModerate(' + p.id + ',\'approuver\')">✓ Approuver</button>'
                      + '<button class="gal-mod-btn gal-mod-ko" onclick="galShowRejectReason(' + p.id + ')">✕ Rejeter</button>'
                      + '</div>'
                      + '</div>';
            });
            html += '</div>';
            el.innerHTML = html;
        });
}

var _galRejectReasons = [
    'Mauvaise qualité (photo floue, sombre ou trop petite)',
    'Contenu non approprié',
    'Photo en double',
    'Hors sujet (ne concerne pas le club)',
    'Autre'
];

function galShowRejectReason(photoId) {
    var actEl = document.getElementById('gal-act-' + photoId);
    if (!actEl) return;

    var opts = _galRejectReasons.map(function (r) {
        return '<option value="' + _he(r) + '">' + _he(r) + '</option>';
    }).join('');

    actEl.innerHTML =
        '<select id="gal-reason-' + photoId + '" class="gal-mod-reason-select">' + opts + '</select>'
        + '<button class="gal-mod-btn gal-mod-ko"  onclick="galConfirmReject(' + photoId + ')">Confirmer</button>'
        + '<button class="gal-mod-btn gal-mod-cancel" onclick="galCancelReject(' + photoId + ')">Annuler</button>';
}

function galCancelReject(photoId) {
    var actEl = document.getElementById('gal-act-' + photoId);
    if (!actEl) return;
    actEl.innerHTML =
        '<button class="gal-mod-btn gal-mod-ok" onclick="galModerate(' + photoId + ',\'approuver\')">✓ Approuver</button>'
        + '<button class="gal-mod-btn gal-mod-ko" onclick="galShowRejectReason(' + photoId + ')">✕ Rejeter</button>';
}

function galConfirmReject(photoId) {
    var sel = document.getElementById('gal-reason-' + photoId);
    var raison = sel ? sel.value : '';
    galModerate(photoId, 'rejeter', raison);
}

function galModerate(photoId, action, raison) {
    var card = document.getElementById('gal-mc-' + photoId);
    var fd   = new FormData();
    fd.append('photo_id', photoId);
    fd.append('action', action);
    if (raison) fd.append('raison', raison);

    fetch('/php/galerie/moderate_photo.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (card) {
                    card.style.transition = 'opacity .3s';
                    card.style.opacity    = '0';
                    setTimeout(function () {
                        card.remove();
                        var remaining = document.querySelectorAll('.gal-mod-card').length;
                        if (remaining === 0) {
                            var el = document.getElementById('gal-mod-content');
                            if (el) el.innerHTML = '<p class="gal-empty">Toutes les photos ont été traitées. ✓</p>';
                        }
                        loadPendingCount();
                        if (action === 'approuver') loadSaisonActive();
                    }, 300);
                }
            } else {
                alert(data.error || 'Erreur');
            }
        });
}

/* ── Utilitaires modal ───────────────────────────────────────────── */

function _galOpenModal(innerHtml) {
    var existing = document.getElementById('gal-overlay');
    if (existing) existing.remove();

    var overlay   = document.createElement('div');
    overlay.id    = 'gal-overlay';
    overlay.className = 'gal-overlay';
    overlay.onclick   = function (e) { if (e.target === overlay) _galCloseModal(); };

    var box       = document.createElement('div');
    box.className = 'gal-modal-box';
    box.innerHTML = '<button class="gal-modal-close" onclick="_galCloseModal()">✕</button>' + innerHtml;

    overlay.appendChild(box);
    document.body.appendChild(overlay);
    requestAnimationFrame(function () { overlay.classList.add('gal-overlay-show'); });
}

function _galCloseModal() {
    var overlay = document.getElementById('gal-overlay');
    if (!overlay) return;
    overlay.classList.remove('gal-overlay-show');
    setTimeout(function () { if (overlay.parentNode) overlay.remove(); }, 200);
}

/* ── Helpers ─────────────────────────────────────────────────────── */

function _he(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function _fmtDate(iso) {
    if (!iso) return '';
    var d = new Date(iso.replace(' ', 'T'));
    return isNaN(d) ? iso : d.toLocaleDateString('fr-FR');
}
