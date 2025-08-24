// Ajout d'un écouteur pour fermer la modale si on clique à l'extérieur du contenu
document.getElementById('infoModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeModal();
    }
});

// Optionnel: fermeture de la modale en appuyant sur la touche "Échap"
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    loadHTML('/commun/menu.html', 'menu');
    loadHTML('/commun/footer.html', 'footer');
});

function openModal(name, imgSrc) {
    const modal = document.getElementById('infoModal');
    modal.querySelector('.modal-content');
    
    document.getElementById('modal-title').textContent = name;
    document.getElementById('modal-image').src = imgSrc;

    modal.style.display = 'block';

    // Déclenche l'effet d'ouverture en ajoutant la classe 'show' après un court délai
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

// Fonction pour fermer la modale
function closeModal(event) {
    const modal = document.getElementById('infoModal');

    // Retirer la classe 'show' pour commencer l'animation de fermeture
    modal.classList.remove('show');

    // Attendre la fin de l'animation avant de cacher complètement la modale
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300); // Ce délai doit correspondre à la durée de l'animation CSS
}

// Ajout des écouteurs existants pour fermer la modale
document.getElementById('infoModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});