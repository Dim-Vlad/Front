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

function openModal(name, info, link, imgSrc) {
    document.getElementById('modal-title').textContent = name;
    document.getElementById('modal-body').textContent = info;
    document.getElementById('modal-link').href = link;
    document.getElementById('modal-image').src = imgSrc;
    document.getElementById('infoModal').style.display = 'block';
}

// Fonction pour fermer la modale
function closeModal(event) {
    const modal = document.getElementById('infoModal');
    
    // Ferme la modale si on clique à l'extérieur du contenu ou si la touche Escape est pressée
    if (!event || event.type === 'click' || (event.type === 'keydown' && event.key === 'Escape')) {
        modal.style.display = 'none';
    }
}

// Ajoute un écouteur d'événements pour fermer la modale avec Échap
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal(event);
    }
});