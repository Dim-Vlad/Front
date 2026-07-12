/* ── CSRF ────────────────────────────────────────────────────────── */

let _csrfToken = null;

// Intercepte tous les fetch POST pour injecter le token CSRF automatiquement
const _origFetch = window.fetch.bind(window);
window.fetch = function (url, opts) {
    if (_csrfToken && opts && opts.method === 'POST') {
        if (opts.body instanceof FormData) {
            opts.body.set('_csrf', _csrfToken);
        } else if (typeof opts.body === 'string') {
            // Corps JSON : on injecte _csrf dans le payload
            try {
                var _parsed = JSON.parse(opts.body);
                _parsed._csrf = _csrfToken;
                opts.body = JSON.stringify(_parsed);
            } catch (e) {
                opts.headers = opts.headers || {};
                opts.headers['X-CSRF-Token'] = _csrfToken;
            }
        } else {
            opts.headers = opts.headers || {};
            opts.headers['X-CSRF-Token'] = _csrfToken;
        }
    }
    return _origFetch(url, opts);
};

/* ── Logo actif ──────────────────────────────────────────────────── */

let logoActif = null;

function fetchLogoActif() {
    fetch('/php/logo/get_logo_actif.php')
        .then(r => r.json())
        .then(data => {
            if (data.logo) {
                logoActif = data.logo;
                applyLogoToPage();
            }
        })
        .catch(() => {});
}

function applyLogoToPage() {
    if (!logoActif) return;
    const src = '/' + logoActif;
    document.querySelectorAll('img.logo-club, img.hero-logo').forEach(img => {
        if (img.getAttribute('src') !== src) img.src = src;
    });
    if (_lmManagerActive) injectLogoEditButtons();
}

function injectLogoEditButtons() {
    document.querySelectorAll('img.logo-club, img.hero-logo').forEach(img => {
        if (img.parentElement.classList.contains('logo-edit-wrapper')) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'logo-edit-wrapper';
        img.parentNode.insertBefore(wrapper, img);
        wrapper.appendChild(img);

        const btn = document.createElement('button');
        btn.className = 'logo-edit-btn';
        btn.innerHTML = '&#128444; Changer le logo';
        btn.onclick   = openLogoManager;
        wrapper.appendChild(btn);
    });
}

/* ── Menu burger / overlay ───────────────────────────────────────── */

function _menuIsOpen() {
    const menu = document.querySelector('.navbar-links');
    return menu && menu.classList.contains('show');
}

function _setMenuOpen(open) {
    const menu    = document.querySelector('.navbar-links');
    const toggler = document.querySelector('.navbar-toggler');
    if (!menu) return;
    if (open) {
        menu.classList.add('show');
        toggler.textContent = '✕';
        document.body.style.overflow = 'hidden';
    } else {
        menu.classList.remove('show');
        toggler.textContent = '☰';
        document.body.style.overflow = '';
        menu.querySelectorAll('.dropdown.active').forEach(function (d) {
            d.classList.remove('active');
        });
    }
}

function toggleMenu() {
    _setMenuOpen(!_menuIsOpen());
}

function closeMenu(event) {
    if (!_menuIsOpen()) return;
    const menu    = document.querySelector('.navbar-links');
    const toggler = document.querySelector('.navbar-toggler');
    // Fermer si clic en dehors du menu ET du toggler
    if (!menu.contains(event.target) && !toggler.contains(event.target)) {
        _setMenuOpen(false);
    }
}

function initializeMenu() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    if (navbarToggler) {
        navbarToggler.addEventListener('click', toggleMenu);
    }
    // Fermer au clic sur un lien direct (pas les boutons dropdown)
    const menu = document.querySelector('.navbar-links');
    if (menu) {
        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () { _setMenuOpen(false); });
        });
        // Ouvrir/fermer les dropdowns au clic (overlay mobile)
        menu.querySelectorAll('.dropdown > button').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var dropdown = btn.closest('.dropdown');
                var wasActive = dropdown.classList.contains('active');
                menu.querySelectorAll('.dropdown.active').forEach(function (d) {
                    d.classList.remove('active');
                });
                if (!wasActive) dropdown.classList.add('active');
            });
        });
        // Fermer au clic sur le fond de l'overlay (pas sur un enfant)
        menu.addEventListener('click', function (e) {
            if (e.target === menu) _setMenuOpen(false);
        });
    }
    document.addEventListener('click', closeMenu);
    // Fermer avec Échap
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && _menuIsOpen()) _setMenuOpen(false);
    });
}

/* ── Auth button ─────────────────────────────────────────────────── */

let _authData = null;

function updateHomeCard(data) {
    // Contenu géré côté PHP (home.php) — rien à faire ici
}

function renderAuthButton(data) {
    const navbar = document.querySelector('.navbar-container-menu');
    if (!navbar) return;

    const existing = document.getElementById('nav-auth');
    if (existing) existing.remove();

    const container = document.createElement('div');
    container.id = 'nav-auth';

    _authData    = data;
    _csrfToken   = data.csrf_token || null;
    updateHomeCard(data);

    if (data.logged_in) {
        const dashboardLink = document.createElement('a');
        dashboardLink.href      = '/pages/auth/tableau-de-bord.php';
        dashboardLink.className = 'nav-btn-dashboard';

        const nameSpan = document.createElement('span');
        nameSpan.textContent = data.display_short || data.username;
        nameSpan.className   = 'nav-btn-dashboard__name';
        dashboardLink.appendChild(nameSpan);
        dashboardLink.appendChild(document.createTextNode('Tableau de bord'));

        const logoutLink = document.createElement('a');
        logoutLink.href        = '/php/logout.php';
        logoutLink.textContent = 'Déconnexion';
        logoutLink.className   = 'nav-btn-logout';
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Voulez-vous vraiment vous déconnecter ?')) {
                window.location.href = '/php/logout.php';
            }
        });

        container.appendChild(dashboardLink);
        container.appendChild(logoutLink);
    } else {
        const loginLink = document.createElement('a');
        loginLink.href        = '/pages/auth/connexion.php';
        loginLink.textContent = 'Connexion';
        loginLink.className   = 'nav-btn-login';
        container.appendChild(loginLink);
    }

    navbar.appendChild(container);
    _pwaInjectBtn();

    // Logo manager pour admin / modérateur
    const roles = data.roles || [];
    if (data.logged_in && (roles.includes('admin') || roles.includes('moderateur'))) {
        initLogoManager();
    }
}

function loadAuthButton() {
    fetch('/php/session_status.php')
        .then(r => r.json())
        .then(data => renderAuthButton(data))
        .catch(() => {});
}

/* ── loadHTML ────────────────────────────────────────────────────── */

function loadHTML(url, elementId) {
    fetch(url)
        .then(response => response.text())
        .then(data => {
            document.getElementById(elementId).innerHTML = data;
            if (elementId === 'menu') {
                initializeMenu();
                loadAuthButton();
            }
            if (elementId === 'content' && _authData) {
                updateHomeCard(_authData);
            }
            applyLogoToPage();
        })
        .catch(error => console.error('Erreur de chargement:', error));
}

/* ── Logo Manager ────────────────────────────────────────────────── */

let _lmSelectedPath  = null;
let _lmManagerActive = false;

function initLogoManager() {
    if (_lmManagerActive) return;
    _lmManagerActive = true;

    // CSS injecté dynamiquement — pas besoin de modifier les <head> de chaque page
    const style = document.createElement('style');
    style.id = 'logo-manager-style';
    style.textContent = `
        /* Wrapper autour du logo (logo-club et hero-logo ont tous deux 110px) */
        .logo-edit-wrapper {
            position: relative;
            display: inline-block;
            width: 150px;
            flex-shrink: 0;
        }
        .logo-edit-wrapper > img {
            width: 100%; height: auto; display: block;
        }

        .logo-edit-btn {
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(6,62,11,.78);
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 5px 14px;
            font-size: .75rem;
            font-weight: 600;
            font-family: var(--font-poppins);
            cursor: pointer;
            opacity: 0;
            transition: opacity .22s;
            white-space: nowrap;
            pointer-events: none;
        }
        .logo-edit-wrapper:hover .logo-edit-btn {
            opacity: 1;
            pointer-events: auto;
        }

        #logo-manager-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1300;
            background: rgba(0,0,0,.55);
            align-items: center;
            justify-content: center;
            padding: 1rem;
            box-sizing: border-box;
        }
        #logo-manager-modal.open { display: flex; }

        .lm-box {
            background: #fff;
            border-radius: 14px;
            width: 100%;
            max-width: 560px;
            max-height: 88vh;
            overflow-y: auto;
            animation: lmopen .22s;
        }
        @keyframes lmopen {
            from { opacity:0; transform:translateY(-16px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .lm-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.4rem;
            background: var(--secondary-color);
            border-radius: 14px 14px 0 0;
            position: sticky;
            top: 0;
        }
        .lm-header h3 { margin:0; color:#fff; font-size:1rem; font-family:var(--font-poppins); }
        .lm-close {
            color: rgba(255,255,255,.8); font-size: 1.6rem; cursor: pointer;
            line-height: 1; background: none; border: none; padding: 0;
        }
        .lm-close:hover { color: #fff; }

        .lm-body { padding: 1.3rem 1.5rem; }

        .lm-section-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--secondary-color);
            margin: 0 0 .75rem;
            font-family: var(--font-poppins);
        }

        .lm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(115px, 1fr));
            gap: .75rem;
            margin-bottom: 1.4rem;
        }

        .lm-logo-item {
            border: 2.5px solid #e0e8e0;
            border-radius: 10px;
            padding: .6rem .5rem .4rem;
            cursor: pointer;
            background: #f8faf8;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .35rem;
            transition: border-color .2s, background .2s;
        }
        .lm-logo-item:hover  { border-color: var(--primary-color); background: #f0f7f0; }
        .lm-logo-item.active { border-color: var(--secondary-color); background: #e8f2e8; }
        .lm-logo-item img { width:100%; height:64px; object-fit:contain; }
        .lm-logo-item span {
            font-size: .64rem; color: #666; text-align: center;
            word-break: break-all; font-family: var(--font-poppins); line-height: 1.3;
        }
        .lm-logo-item.active span { color: var(--secondary-color); font-weight: 600; }

        .lm-separator { border:none; border-top:1px solid #e8ece8; margin:.2rem 0 1.2rem; }

        .lm-upload-zone {
            border: 2px dashed #c8d8c8;
            border-radius: 10px;
            padding: 1.1rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            margin-bottom: 1.2rem;
            font-family: var(--font-poppins);
        }
        .lm-upload-zone:hover { border-color: var(--primary-color); background: #f5faf5; }
        .lm-upload-zone .lm-upload-label { font-size:.88rem; color:var(--bg-dark); font-weight:500; }
        .lm-upload-zone p { margin:.3rem 0 0; font-size:.78rem; color:#888; }
        .lm-upload-zone input { display: none; }

        #lm-upload-preview {
            width: 100%; max-height: 80px; object-fit: contain;
            margin-top: .7rem; display: none; border-radius: 6px;
        }

        .lm-actions {
            display: flex; align-items: center;
            gap: .75rem; justify-content: flex-end; margin-top: .4rem;
        }
        .lm-status {
            flex: 1; font-size: .83rem;
            font-family: var(--font-poppins); min-height: 1.1rem;
        }
        .lm-status.error   { color: #c62828; }
        .lm-status.success { color: #2e7d32; }

        .lm-btn-save {
            background: var(--secondary-color); color: #fff; border: none;
            border-radius: 7px; padding: .55rem 1.4rem;
            font-weight: 600; font-family: var(--font-poppins);
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .lm-btn-save:hover { background: var(--bg-dark); }

        .lm-btn-cancel {
            background: #eee; color: #444; border: none; border-radius: 7px;
            padding: .55rem 1.1rem; font-family: var(--font-poppins);
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .lm-btn-cancel:hover { background: #ddd; }

        @media (max-width: 480px) {
            .lm-grid { grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); }
            #logo-manager-btn { bottom: 14px; right: 14px; font-size: .76rem; padding: 7px 12px; }
            .lm-body { padding: 1rem; }
        }
    `;
    document.head.appendChild(style);

    // Injecter le bouton sur les logos déjà présents
    injectLogoEditButtons();

    // Modale
    const modal = document.createElement('div');
    modal.id = 'logo-manager-modal';
    modal.innerHTML = `
        <div class="lm-box">
            <div class="lm-header">
                <h3>Changer le logo du club</h3>
                <button class="lm-close" onclick="closeLogoManager()" title="Fermer">&times;</button>
            </div>
            <div class="lm-body">
                <p class="lm-section-title">Logos disponibles</p>
                <div class="lm-grid" id="lm-logos-grid">
                    <p style="color:#888;font-size:.85rem;grid-column:1/-1">Chargement&hellip;</p>
                </div>
                <hr class="lm-separator">
                <p class="lm-section-title">Importer un nouveau logo</p>
                <div class="lm-upload-zone" onclick="document.getElementById('lm-file-input').click()">
                    <div class="lm-upload-label">&#128193; Cliquer pour choisir une image</div>
                    <p>JPG &middot; PNG &middot; WEBP &middot; GIF &mdash; max&nbsp;3&nbsp;Mo</p>
                    <img id="lm-upload-preview" alt="Aperçu du logo">
                </div>
                <input type="file" id="lm-file-input" accept="image/jpeg,image/png,image/webp,image/gif">
                <div class="lm-actions">
                    <span class="lm-status" id="lm-status"></span>
                    <button class="lm-btn-cancel" onclick="closeLogoManager()">Annuler</button>
                    <button class="lm-btn-save"   onclick="saveLogo()">Appliquer</button>
                </div>
            </div>
        </div>
    `;
    modal.addEventListener('click', e => { if (e.target === modal) closeLogoManager(); });
    document.body.appendChild(modal);

    document.getElementById('lm-file-input').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const preview = document.getElementById('lm-upload-preview');
        preview.src           = URL.createObjectURL(file);
        preview.style.display = 'block';
        document.querySelectorAll('.lm-logo-item').forEach(i => i.classList.remove('active'));
        _lmSelectedPath = null;
    });
}

function openLogoManager() {
    _lmSelectedPath = null;
    const status = document.getElementById('lm-status');
    status.textContent = '';
    status.className   = 'lm-status';
    document.getElementById('lm-file-input').value = '';
    document.getElementById('lm-upload-preview').style.display = 'none';
    document.getElementById('logo-manager-modal').classList.add('open');

    const grid = document.getElementById('lm-logos-grid');
    grid.innerHTML = '<p style="color:#888;font-size:.85rem;grid-column:1/-1">Chargement&hellip;</p>';

    fetch('/php/logo/list_logos.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.logos.length) {
                grid.innerHTML = '<p style="color:#888;font-size:.85rem;grid-column:1/-1">Aucun logo trouvé</p>';
                return;
            }
            grid.innerHTML = '';
            data.logos.forEach(path => {
                const isActive = (path === logoActif);
                const item = document.createElement('div');
                item.className = 'lm-logo-item' + (isActive ? ' active' : '');
                if (isActive) _lmSelectedPath = path;

                const name = path.split('/').pop();
                const img  = document.createElement('img');
                img.src     = '/' + path;
                img.alt     = name;
                img.loading = 'lazy';
                const span  = document.createElement('span');
                span.textContent = name;
                item.appendChild(img);
                item.appendChild(span);

                item.addEventListener('click', () => {
                    document.querySelectorAll('.lm-logo-item').forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
                    _lmSelectedPath = path;
                    document.getElementById('lm-file-input').value = '';
                    document.getElementById('lm-upload-preview').style.display = 'none';
                });

                grid.appendChild(item);
            });
        })
        .catch(() => {
            grid.innerHTML = '<p style="color:#c62828;grid-column:1/-1">Erreur de chargement</p>';
        });
}

function closeLogoManager() {
    document.getElementById('logo-manager-modal').classList.remove('open');
}

async function saveLogo() {
    const status    = document.getElementById('lm-status');
    const fileInput = document.getElementById('lm-file-input');
    const fd        = new FormData();

    if (fileInput.files[0]) {
        fd.append('logo_upload', fileInput.files[0]);
    } else if (_lmSelectedPath) {
        fd.append('logo_path', _lmSelectedPath);
    } else {
        status.textContent = 'Sélectionnez un logo ou importez une image.';
        status.className   = 'lm-status error';
        return;
    }

    status.textContent = 'Enregistrement…';
    status.className   = 'lm-status';

    try {
        const res  = await fetch('/php/logo/change_logo.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            logoActif = data.logo;
            applyLogoToPage();
            status.textContent = 'Logo mis à jour !';
            status.className   = 'lm-status success';
            setTimeout(closeLogoManager, 1400);
        } else {
            status.textContent = data.error || 'Erreur inconnue';
            status.className   = 'lm-status error';
        }
    } catch {
        status.textContent = 'Erreur réseau';
        status.className   = 'lm-status error';
    }
}

/* ── Lightbox galerie ────────────────────────────────────────────── */

(function () {
    var _lbPhotos  = [];
    var _lbIdx     = 0;
    var _lbTouchX  = null;

    document.addEventListener('click', function (e) {
        var link = e.target.closest('.photo-grid a, .archive-gallery a');
        if (!link) return;
        if (link.classList.contains('gal-more-tile') || link.classList.contains('more')) return;
        // Skip delete button clicks inside the link
        if (e.target.classList.contains('gal-photo-delete')) return;

        e.preventDefault();

        var grid     = link.closest('.photo-grid, .archive-gallery');
        var allLinks = Array.from(grid.querySelectorAll('a:not(.gal-more-tile):not(.more)'));

        _lbPhotos = allLinks.map(function (a) {
            var img = a.querySelector('img');
            return { src: a.getAttribute('href'), alt: img ? (img.alt || '') : '' };
        });
        _lbIdx = allLinks.indexOf(link);

        _lbOpen();
    });

    function _lbOpen() {
        if (document.getElementById('lb-overlay')) _lbClose(true);

        var overlay       = document.createElement('div');
        overlay.id        = 'lb-overlay';
        overlay.innerHTML =
            '<button class="lb-close" id="lb-close" aria-label="Fermer">✕</button>'
            + '<button class="lb-nav lb-prev" id="lb-prev" aria-label="Photo précédente">‹</button>'
            + '<div class="lb-img-wrap" id="lb-img-wrap"><img id="lb-img" src="" alt=""></div>'
            + '<button class="lb-nav lb-next" id="lb-next" aria-label="Photo suivante">›</button>'
            + '<div class="lb-counter" id="lb-counter"></div>';

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        document.getElementById('lb-close').addEventListener('click', function () { _lbClose(false); });
        document.getElementById('lb-prev').addEventListener('click', _lbPrev);
        document.getElementById('lb-next').addEventListener('click', _lbNext);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) _lbClose(false); });
        document.addEventListener('keydown', _lbKey);

        var wrap = document.getElementById('lb-img-wrap');
        wrap.addEventListener('touchstart', function (e) { _lbTouchX = e.touches[0].clientX; }, { passive: true });
        wrap.addEventListener('touchend',   function (e) {
            if (_lbTouchX === null) return;
            var dx = e.changedTouches[0].clientX - _lbTouchX;
            _lbTouchX = null;
            if (Math.abs(dx) > 45) { if (dx < 0) _lbNext(); else _lbPrev(); }
        }, { passive: true });

        requestAnimationFrame(function () { overlay.classList.add('lb-visible'); });
        _lbShow();
    }

    function _lbClose(instant) {
        var overlay = document.getElementById('lb-overlay');
        if (!overlay) return;
        document.removeEventListener('keydown', _lbKey);
        document.body.style.overflow = '';
        if (instant) { overlay.remove(); return; }
        overlay.classList.remove('lb-visible');
        setTimeout(function () { if (overlay.parentNode) overlay.remove(); }, 250);
    }

    function _lbShow() {
        var p    = _lbPhotos[_lbIdx];
        var img  = document.getElementById('lb-img');
        var ctr  = document.getElementById('lb-counter');
        if (!img || !p) return;

        img.style.opacity = '0';
        img.onload = function () {
            img.style.transition = 'opacity .25s';
            img.style.opacity    = '1';
        };
        img.src = p.src;
        img.alt = p.alt;

        if (ctr) ctr.textContent = (_lbIdx + 1) + ' / ' + _lbPhotos.length;

        var prev = document.getElementById('lb-prev');
        var next = document.getElementById('lb-next');
        if (prev) prev.style.visibility = _lbIdx > 0                       ? 'visible' : 'hidden';
        if (next) next.style.visibility = _lbIdx < _lbPhotos.length - 1    ? 'visible' : 'hidden';
    }

    function _lbNext() { if (_lbIdx < _lbPhotos.length - 1) { _lbIdx++; _lbShow(); } }
    function _lbPrev() { if (_lbIdx > 0)                    { _lbIdx--; _lbShow(); } }

    function _lbKey(e) {
        if      (e.key === 'ArrowRight') _lbNext();
        else if (e.key === 'ArrowLeft')  _lbPrev();
        else if (e.key === 'Escape')     _lbClose(false);
    }
})();

/* ── PWA — manifest + service worker + bouton d'installation ────── */

(function () {
    // Injecte le manifest si pas déjà présent
    if (!document.querySelector('link[rel="manifest"]')) {
        const link = document.createElement('link');
        link.rel   = 'manifest';
        link.href  = '/manifest.json';
        document.head.appendChild(link);
    }

    // Injecte theme-color pour la barre de statut Android
    if (!document.querySelector('meta[name="theme-color"]')) {
        const meta = document.createElement('meta');
        meta.name    = 'theme-color';
        meta.content = '#063E0B';
        document.head.appendChild(meta);
    }

    // Enregistre le service worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
})();

/* ── Bouton d'installation PWA ───────────────────────────────────── */

let _pwaInstallPrompt = null;

// Déjà installée en mode standalone : on n'affiche rien
const _pwaIsStandalone = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    _pwaInstallPrompt = e;
    // Le bouton est déjà dans le DOM (injecté au chargement du menu), rien à faire
});

window.addEventListener('appinstalled', () => {
    _pwaHideInstallBtn();
    _pwaInstallPrompt = null;
});

function _pwaInjectBtn() {
    if (_pwaIsStandalone) return;
    if (document.getElementById('pwa-install-btn')) return;

    if (!document.getElementById('pwa-install-style')) {
        const style = document.createElement('style');
        style.id = 'pwa-install-style';
        style.textContent = `
            #pwa-install-btn {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                padding: .38rem .85rem;
                background: var(--primary-color, #acc2ab);
                color: var(--secondary-color, #063E0B);
                border: none;
                border-radius: 20px;
                font-family: var(--font-poppins, 'Poppins', sans-serif);
                font-size: .78rem;
                font-weight: 600;
                cursor: pointer;
                transition: opacity .2s, transform .2s;
                white-space: nowrap;
                flex-shrink: 0;
            }
            #pwa-install-btn:hover { opacity: .85; transform: translateY(-1px); }
            #pwa-install-btn svg { width: 14px; height: 14px; }
            #pwa-install-tooltip {
                position: fixed;
                bottom: 1.5rem;
                left: 50%;
                transform: translateX(-50%);
                background: #223224;
                color: #fff;
                padding: .85rem 1.3rem;
                border-radius: 12px;
                font-family: var(--font-poppins, 'Poppins', sans-serif);
                font-size: .85rem;
                box-shadow: 0 4px 20px rgba(0,0,0,.35);
                z-index: 2000;
                text-align: center;
                max-width: 320px;
                line-height: 1.5;
                animation: pwaTooltipIn .25s ease;
            }
            #pwa-install-tooltip strong { color: #acc2ab; }
            #pwa-install-tooltip-close {
                display: block;
                margin-top: .6rem;
                font-size: .78rem;
                color: rgba(255,255,255,.6);
                cursor: pointer;
                background: none;
                border: none;
                font-family: inherit;
            }
            @keyframes pwaTooltipIn {
                from { opacity: 0; transform: translateX(-50%) translateY(10px); }
                to   { opacity: 1; transform: translateX(-50%) translateY(0); }
            }
            @media (max-width: 480px) {
                #pwa-install-btn span { display: none; }
                #pwa-install-btn { padding: .38rem .55rem; }
                #pwa-install-btn svg { width: 17px; height: 17px; }
            }
        `;
        document.head.appendChild(style);
    }

    const btn = document.createElement('button');
    btn.id        = 'pwa-install-btn';
    btn.title     = "Installer l'application";
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13M7 11l5 5 5-5"/><path d="M5 20h14"/></svg><span>Installer</span>';

    btn.addEventListener('click', async () => {
        if (_pwaInstallPrompt) {
            // Prompt natif disponible (Chrome Android / Edge desktop)
            _pwaInstallPrompt.prompt();
            const { outcome } = await _pwaInstallPrompt.userChoice;
            if (outcome === 'accepted') _pwaHideInstallBtn();
        } else {
            // Pas de prompt natif : affiche les instructions selon navigateur
            _pwaShowTooltip();
        }
    });

    if (window.innerWidth <= 600) {
        // Mobile : à l'intérieur du brand, même ligne que le logo
        const brand = document.querySelector('.navbar-brand');
        if (brand) {
            btn.style.marginLeft = 'auto';
            btn.style.flexShrink = '0';
            brand.appendChild(btn);
        }
    } else if (window.innerWidth <= 1300) {
        // Tablette/burger : dans #nav-auth, côté droit (comportement original)
        const navAuth = document.getElementById('nav-auth');
        if (navAuth) navAuth.insertBefore(btn, navAuth.firstChild);
    } else {
        // Desktop : avant le logo dans la navbar
        const navContainer = document.querySelector('.navbar-container-menu');
        if (navContainer) navContainer.insertBefore(btn, navContainer.firstChild);
    }
}

function _pwaShowTooltip() {
    if (document.getElementById('pwa-install-tooltip')) return;
    const isIOS   = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

    let msg;
    if (isIOS || isSafari) {
        msg = 'Sur Safari, appuyez sur <strong>Partager</strong> puis <strong>Sur l\'écran d\'accueil</strong>.';
    } else {
        msg = 'Dans Chrome ou Edge, cliquez sur l\'icône <strong>⊕</strong> à droite de la barre d\'adresse.';
    }

    const tip = document.createElement('div');
    tip.id = 'pwa-install-tooltip';
    tip.innerHTML = msg + '<button id="pwa-install-tooltip-close">Fermer</button>';
    document.body.appendChild(tip);
    document.getElementById('pwa-install-tooltip-close').addEventListener('click', () => tip.remove());
    setTimeout(() => tip.remove(), 8000);
}

function _pwaHideInstallBtn() {
    document.getElementById('pwa-install-btn')?.remove();
}

/* ── Initialisation ──────────────────────────────────────────────── */

fetchLogoActif();
loadHTML('/commun/menu.html', 'menu');
loadHTML('/commun/footer.php', 'footer');
