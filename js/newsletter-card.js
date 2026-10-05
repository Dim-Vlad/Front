(function () {
    var sw       = document.getElementById('nl-switch');
    var statut   = document.getElementById('nl-card-status');
    var btnArch  = document.getElementById('nl-archives-btn');
    var modal    = document.getElementById('nl-modal');
    var titre    = document.getElementById('nl-modal-title');
    var liste    = document.getElementById('nl-modal-list');
    var frame    = document.getElementById('nl-modal-frame');
    var btnBack  = document.getElementById('nl-modal-back');
    var btnClose = document.getElementById('nl-modal-close');

    // ── Abonnement ──
    sw.addEventListener('click', async function () {
        var actif = sw.getAttribute('aria-checked') === 'true';
        var nouveau = !actif;
        sw.disabled = true;
        try {
            var fd = new FormData();
            fd.append('newsletter', nouveau ? '1' : '0');
            var res  = await fetch('/php/newsletter/preferences.php', { method: 'POST', body: fd });
            var json = await res.json();
            if (!json.success) throw new Error(json.error || 'Erreur');
            sw.setAttribute('aria-checked', nouveau ? 'true' : 'false');
            statut.textContent = nouveau
                ? 'Vous recevez la newsletter du club.'
                : 'Vous ne recevez plus la newsletter.';
        } catch (err) {
            statut.textContent = 'Impossible de modifier votre choix : ' + err.message;
        } finally {
            sw.disabled = false;
        }
    });

    // ── Archives ──
    function ouvrirModal() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function fermerModal() {
        modal.hidden = true;
        frame.hidden = true;
        frame.removeAttribute('srcdoc');
        document.body.style.overflow = '';
    }
    function afficherListe() {
        frame.hidden = true;
        frame.removeAttribute('srcdoc');
        liste.hidden = false;
        btnBack.hidden = true;
        titre.textContent = 'Anciennes éditions';
    }
    function formaterDate(s) {
        var d = new Date(s.replace(' ', 'T'));
        return isNaN(d) ? s : d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    async function chargerListe() {
        liste.innerHTML = '<p class="nl-modal-info">Chargement…</p>';
        try {
            var res  = await fetch('/php/newsletter/archives.php');
            var json = await res.json();
            if (!json.success) throw new Error(json.error || 'Erreur');
            if (json.editions.length === 0) {
                liste.innerHTML = '<p class="nl-modal-info">Aucune édition n\'a encore été envoyée.</p>';
                return;
            }
            liste.innerHTML = '';
            json.editions.forEach(function (ed) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'nl-edition';
                var t = document.createElement('span');
                t.className = 'nl-edition-titre';
                t.textContent = ed.titre;
                var d = document.createElement('span');
                d.className = 'nl-edition-date';
                d.textContent = formaterDate(ed.created_at);
                b.appendChild(t);
                b.appendChild(d);
                b.addEventListener('click', function () { ouvrirEdition(ed.id, ed.titre); });
                liste.appendChild(b);
            });
        } catch (err) {
            liste.innerHTML = '<p class="nl-modal-info nl-modal-info--err">' + err.message + '</p>';
        }
    }

    async function ouvrirEdition(id, nom) {
        liste.hidden = true;
        frame.hidden = false;
        btnBack.hidden = false;
        titre.textContent = nom;
        try {
            var res  = await fetch('/php/newsletter/archive_view.php?id=' + encodeURIComponent(id));
            var json = await res.json();
            if (!json.success) throw new Error(json.error || 'Erreur');
            frame.srcdoc = json.html;
        } catch (err) {
            frame.hidden = true;
            liste.hidden = false;
            btnBack.hidden = true;
            liste.innerHTML = '<p class="nl-modal-info nl-modal-info--err">' + err.message + '</p>';
        }
    }

    btnArch.addEventListener('click', function () {
        ouvrirModal();
        afficherListe();
        chargerListe();
    });
    btnBack.addEventListener('click', afficherListe);
    btnClose.addEventListener('click', fermerModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) fermerModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) fermerModal(); });
})();
