document.getElementById('season-select').addEventListener('change', function () {
    const selected = this.value;

    if (selected === '2024') {
        window.location.href = '/pages/galerie/galerie24-25.html';
    } else if (selected === '2023') {
        window.location.href = '/pages/galerie/galerie23-24.html';
    } else if (selected === '2025') {
        window.location.href = '/pages/galerie/galerie25-26.html';
    } else if (selected === 'events') {
        window.location.href = '/pages/galerie/galerie-evenement.html';
    }
});


// CARROUSEL
const swiper = new Swiper('.swiper-container', {
    loop: true,
    pagination: { el: '.swiper-pagination' },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' }
});