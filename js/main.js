// Fonction pour afficher ou masquer le menu burger
function toggleMenu() {
    const menu = document.querySelector('.navbar-links');
    const toggler = document.querySelector('.navbar-toggler');
    if (menu) {
        menu.classList.toggle('show');
        toggler.classList.toggle('close');
    }
}

// Fonction pour fermer le menu si on clique à l'extérieur
function closeMenu(event) {
    const menu = document.querySelector('.navbar-links');
    const toggler = document.querySelector('.navbar-toggler');
    if (menu && !menu.contains(event.target) && !toggler.contains(event.target)) {
        menu.classList.remove('show');
        toggler.classList.remove('close');
    }
}

// Initialisation des événements
function initializeMenu() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    if (navbarToggler) {
        navbarToggler.addEventListener('click', toggleMenu);
    }
    document.addEventListener('click', closeMenu);
}

// Injecte le bouton Login ou Logout dans la navbar selon l'état de session
function renderAuthButton(data) {
    const navbar = document.querySelector('.navbar-container-menu');
    if (!navbar) return;

    const existing = document.getElementById('nav-auth');
    if (existing) existing.remove();

    const container = document.createElement('div');
    container.id = 'nav-auth';

    if (data.logged_in) {
        const userLink = document.createElement('a');
        userLink.href = '/pages/tableau-de-bord.php';
        userLink.textContent = data.display_short || data.username;
        userLink.className = 'nav-btn-user';

        const logoutLink = document.createElement('a');
        logoutLink.href = '/php/logout.php';
        logoutLink.textContent = 'Déconnexion';
        logoutLink.className = 'nav-btn-logout';

        container.appendChild(userLink);
        container.appendChild(logoutLink);
    } else {
        const loginLink = document.createElement('a');
        loginLink.href = '/pages/connexion.php';
        loginLink.textContent = 'Connexion';
        loginLink.className = 'nav-btn-login';

        container.appendChild(loginLink);
    }

    navbar.appendChild(container);
}

function loadAuthButton() {
    fetch('/php/session_status.php')
        .then(r => r.json())
        .then(data => renderAuthButton(data))
        .catch(() => {});
}

// Charge les contenus HTML
function loadHTML(url, elementId) {
    fetch(url)
        .then(response => response.text())
        .then(data => {
            document.getElementById(elementId).innerHTML = data;
            if (elementId === 'menu') {
                initializeMenu();
                loadAuthButton();
            }
        })
        .catch(error => console.error('Erreur de chargement:', error));
}

// Chargement des contenus HTML
loadHTML('/commun/menu.html', 'menu');
loadHTML('/commun/footer.html', 'footer');
