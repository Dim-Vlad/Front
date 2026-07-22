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

let logoActif        = null;
let logoMenuActif     = null;
let logoFaviconActif  = null;

function fetchLogoActif() {
    fetch('/php/logo/get_logo_actif.php')
        .then(r => r.json())
        .then(data => {
            logoActif       = data.logo         || logoActif;
            logoMenuActif   = data.logo_menu    || logoMenuActif;
            logoFaviconActif = data.logo_favicon || logoFaviconActif;
            applyLogoToPage();
        })
        .catch(() => {});
}

function applyLogoToPage() {
    if (logoActif) {
        const src = '/' + logoActif;
        document.querySelectorAll('img.logo-club, img.hero-logo').forEach(img => {
            if (img.getAttribute('src') !== src) img.src = src;
        });
    }
    if (logoMenuActif) {
        const src = '/' + logoMenuActif;
        document.querySelectorAll('img.navbar-logo').forEach(img => {
            if (img.getAttribute('src') !== src) img.src = src;
        });
    }
    if (logoFaviconActif) {
        const href = '/' + logoFaviconActif;
        const link = document.querySelector('link[rel="icon"]');
        if (link && link.getAttribute('href') !== href) link.setAttribute('href', href);
    }
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
if (document.getElementById('pdf-modal-slot')) {
    loadHTML('/commun/pdf-modal.html', 'pdf-modal-slot');
}
