/* ── Logo actif ──────────────────────────────────────────────────── */

let logoActif = null;

function fetchLogoActif() {
    fetch('/php/get_logo_actif.php')
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

/* ── Menu burger ─────────────────────────────────────────────────── */

function toggleMenu() {
    const menu    = document.querySelector('.navbar-links');
    const toggler = document.querySelector('.navbar-toggler');
    if (menu) {
        menu.classList.toggle('show');
        toggler.classList.toggle('close');
    }
}

function closeMenu(event) {
    const menu    = document.querySelector('.navbar-links');
    const toggler = document.querySelector('.navbar-toggler');
    if (menu && !menu.contains(event.target) && !toggler.contains(event.target)) {
        menu.classList.remove('show');
        toggler.classList.remove('close');
    }
}

function initializeMenu() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    if (navbarToggler) {
        navbarToggler.addEventListener('click', toggleMenu);
    }
    document.addEventListener('click', closeMenu);
}

/* ── Auth button ─────────────────────────────────────────────────── */

function renderAuthButton(data) {
    const navbar = document.querySelector('.navbar-container-menu');
    if (!navbar) return;

    const existing = document.getElementById('nav-auth');
    if (existing) existing.remove();

    const container = document.createElement('div');
    container.id = 'nav-auth';

    if (data.logged_in) {
        const userLink = document.createElement('a');
        userLink.href        = '/pages/tableau-de-bord.php';
        userLink.textContent = data.display_short || data.username;
        userLink.className   = 'nav-btn-user';

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

        container.appendChild(userLink);
        container.appendChild(logoutLink);
    } else {
        const loginLink = document.createElement('a');
        loginLink.href        = '/pages/connexion.php';
        loginLink.textContent = 'Connexion';
        loginLink.className   = 'nav-btn-login';
        container.appendChild(loginLink);
    }

    navbar.appendChild(container);

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
        /* Wrapper autour du logo — respecte la taille d'origine de chaque classe */
        .logo-edit-wrapper { position: relative; }

        /* .logo-club : 50% / max 300px / centré */
        .logo-edit-wrapper:has(> img.logo-club) {
            display: block;
            width: 50%;
            max-width: 300px;
            margin: 0 auto;
        }
        .logo-edit-wrapper > img.logo-club {
            width: 100%; height: auto; max-width: 100%; margin: 0; display: block;
        }

        /* .hero-logo : 110px fixe, ne rétrécit pas */
        .logo-edit-wrapper:has(> img.hero-logo) {
            display: inline-block;
            width: 110px;
            flex-shrink: 0;
        }
        .logo-edit-wrapper > img.hero-logo {
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

    fetch('/php/list_logos.php')
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
                item.innerHTML = `<img src="/${path}" alt="${name}" loading="lazy"><span>${name}</span>`;

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
        const res  = await fetch('/php/change_logo.php', { method: 'POST', body: fd });
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

/* ── Initialisation ──────────────────────────────────────────────── */

fetchLogoActif();
loadHTML('/commun/menu.html', 'menu');
loadHTML('/commun/footer.html', 'footer');
