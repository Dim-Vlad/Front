// Configuration des modales
const modal = document.getElementById("myModal");
const span = document.querySelector(".close");

const descriptions = {
    "Sportive": `
        <h3>Objectif :</h3>
        <p>Assurer le bon fonctionnement sportif du club</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Définir les objectifs sportifs de chaque équipe</li>
            <li>Organiser le planning des entraînements des coachs</li>
            <li>Coordonner les relations avec la fédération, la ligue et le comité</li>
            <li>Coordonner les relations entre les entraîneurs du club</li>
            <li>Communiquer avec la mairie pour la réservation du gymnase</li>
        </ul>
    `,
    "Financière": `
        <h3>Objectif :</h3>
        <p>Assurer la stabilité financière du club et optimiser la gestion des ressources</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Élaborer et suivre le budget annuel du club</li>
            <li>Préparer le bilan financier présenté à l’Assemblée Générale</li>
            <li>Rechercher des sources de financement (subventions, partenariats, mécénat)</li>
            <li>Conseiller les autres commissions sur la faisabilité financière de leurs projets</li>
            <li>Gérer les inscriptions HelloAsso (tournois, repas, événements payants)</li>
            <li>Assurer une transparence financière auprès des membres du club</li>
        </ul>
    `,
    "Arbitrage": `
        <h3>Objectif :</h3>
        <p>Garantir la présence et la formation des arbitres du club</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Identifier et former des arbitres internes au club</li>
            <li>Organiser les désignations des marqueurs pour les matchs à domicile</li>
            <li>Veiller au respect des règles et sensibiliser les joueurs et entraîneurs</li>
            <li>Assurer le lien avec la commission arbitrale de la fédération, de la ligue et du comité</li>
        </ul>
    `,
    "Sponsoring": `
        <h3>Objectif :</h3>
        <p>Développer les partenariats et assurer le financement du club via les sponsors</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Rechercher et négocier des partenariats financiers et matériels</li>
            <li>Concevoir des supports de communication pour les sponsors (dossiers, affiches, cadre maillot)</li>
            <li>Travailler en collaboration avec la commission communication pour la mise en avant des partenaires</li>
            <li>Assurer la visibilité des partenaires sur les équipements et événements du club</li>
            <li>Organiser des événements pour fidéliser les sponsors</li>
            <li>Gérer la relation avec les partenaires et assurer leur satisfaction</li>
        </ul>
    `,
    "Formation": `
        <h3>Objectif :</h3>
        <p>Développer la formation des jeunes et des encadrants du club</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Organiser des stages et sessions de formation pour les jeunes</li>
            <li>Former et accompagner les entraîneurs et encadrants</li>
            <li>Assurer le suivi des joueurs en formation et leur progression</li>
            <li>Établir un lien avec les instances de formation (FF Volley, Ligue et Comité)</li>
        </ul>
    `,
    "Textile": `
        <p>Adjoint : Pierre Desoomer</p>
        <h3>Objectif :</h3>
        <p>Gérer les équipements et les tenues du club</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Sélectionner les tenues et équipements en lien avec les besoins du club</li>
            <li>Passer les commandes et gérer la distribution des équipements</li>
            <li>Négocier les tarifs avec les fournisseurs</li>
            <li>Gérer la boutique HelloAsso visible sur le site</li>
            <li>Veiller à la bonne utilisation et l’entretien des équipements</li>
        </ul>
    `,
    "Matériel": `
        <h3>Objectif :</h3>
        <p>Assurer la gestion, l’entretien et le renouvellement du matériel du club</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Recenser et gérer l’inventaire du matériel sportif (ballons, filets, poteaux, mires, etc.)</li>
            <li>Recenser et gérer l’inventaire du matériel pédagogique (plots, échelle, medecine-ball, tapis, etc.)</li>
            <li>Vérifier régulièrement l’état du matériel</li>
            <li>Proposer et gérer les achats de matériel en fonction des besoins du club</li>
            <li>Assurer la mise à disposition et le respect du rangement du matériel avant et après utilisation</li>
            <li>Sensibiliser les membres du club à la bonne utilisation du matériel</li>
        </ul>
    `,
    "Transport": `
        <h3>Objectif :</h3>
        <p>Organiser et optimiser les déplacements des équipes du club</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Planifier les déplacements pour les matchs et tournois à l’extérieur</li>
            <li>Gérer les réservations de minibus ou autres moyens de transport</li>
            <li>Assurer le respect des règles de sécurité lors des trajets</li>
            <li>Rechercher des solutions de financement pour les déplacements (subventions, sponsors, etc.)</li>
        </ul>
    `,
    "Communication": `
        <h3>Objectif :</h3>
        <p>Assurer la visibilité et la promotion du club auprès du public et des partenaires</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Gérer les réseaux sociaux (Facebook, Instagram, Youtube, Discord)</li>
            <li>Rédiger et publier les actualités du club (résultats, événements, annonces)</li>
            <li>Concevoir et diffuser des supports de communication (affiches, flyers, vidéos)</li>
            <li>Travailler en collaboration avec la commission sponsors pour la mise en avant des partenaires</li>
        </ul>
    `,
    "Événementielle": `
        <h3>Objectif :</h3>
        <p>Organiser les événements du club pour renforcer la convivialité et la visibilité</p>
        
        <h3>Missions :</h3>
        <ul>
            <li>Planifier et coordonner les événements internes (tournois, repas, animations, loto, etc.)</li>
            <li>Gérer la logistique (lieux, matériel, autorisations, billetterie, restauration)</li>
            <li>Mobiliser les bénévoles pour l’organisation et la mise en place des événements</li>
            <li>Assurer la promotion des événements en lien avec la commission communication</li>
            <li>Rechercher des sponsors ou financements pour soutenir les événements</li>
            <li>Veiller au bon déroulement des événements et assurer un retour d’expérience pour améliorer les futures éditions</li>
        </ul>
    `,
    "Devenir membre": `
        <p>📢Rejoignez les commissions du club<br>
            et participez à son dynamisme !🏐💪</p>
        <p>📆Intéressé(e) ?<br>
            Contactez-nous rapidement pour choisir votre commission ! Ensemble, faisons vivre notre club !💙🏐</p>
    `,
};

// Fonction pour créer des liens
function createLink(href, text) {
    const link = document.createElement('a');
    link.href = href;
    link.textContent = text;
    link.className = 'modal-link';
    return link;
}

// Fonction d'ouverture de modale
function openModal(title, subtitle, imageUrl) {
    const modalTitle = document.getElementById("modal-title");
    const modalSubtitle = document.getElementById("modal-subtitle");
    const modalImage = document.getElementById("modal-image");
    const modalDesc = document.getElementById("modal-description");
    const modalLinks = document.getElementById("modal-links");

    modalTitle.textContent = title;
    modalSubtitle.textContent = subtitle;
    modalImage.src = imageUrl;
    modalImage.alt = `Photo ${title}`;
    modalDesc.innerHTML = descriptions[title] || "<p>Description à venir</p>";

    modalLinks.innerHTML = '';
    if (title === "Devenir membre") {
        modalLinks.append(
            createLink("/pages/leClub/commissions.html", "En savoir plus"),
            createLink("/pages/nousContacter.html", "Contactez-nous")
        );
    }

    modal.style.display = "block";
    document.body.classList.add('modal-open');
}

// Fonction de fermeture de modale
function closeModal() {
    modal.style.display = "none";
    document.body.classList.remove('modal-open');
}

// Gestion des événements
function setupEventListeners() {
    // Fermeture via le bouton ×
    if (span) {
        span.addEventListener('click', closeModal);
    }

    // Fermeture en cliquant à l'extérieur
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Fermeture avec la touche Échap
    document.addEventListener('keydown', (e) => {
        if (e.key === "Escape" && modal.style.display === "block") {
            closeModal();
        }
    });

    // Accessibilité au clavier pour les cartes
    document.querySelectorAll('.position').forEach(item => {
        item.addEventListener('keydown', (e) => {
            if (['Enter', ' '].includes(e.key)) {
                e.preventDefault();
                item.click();
            }
        });
    });
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    setupEventListeners();
});

// Version alternative pour la délégation d'événements (si nécessaire)
document.body.addEventListener('click', function(e) {
    if (e.target.classList.contains('close') || e.target.id === 'myModal') {
        closeModal();
    }
});