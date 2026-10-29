let _rembLigneIndex = 0;

// REMB_DISTANCES (aller simple, en km, depuis Ollioules) est injecté par
// pages/leClub/remboursement.php depuis la table remboursement_villes,
// gérable par un admin dans admin/gestion-site.php.

function _rembDistanceOptions() {
    return Object.keys(REMB_DISTANCES).map(v => `<option value="${v}">`).join('');
}

// Si la ville saisie correspond à une ville connue, remplit la distance
// automatiquement (aller simple, ou doublée si "aller-retour" est coché),
// puis le montant kilométrique selon le tarif (REMB_TARIF_KM, réglable par
// un admin). La distance reste modifiable à la main pour une ville absente.
function _rembAutoDistance(i) {
    const ville = document.querySelector(`input[name="ligne_${i}_arrivee"]`).value.trim();
    if (REMB_DISTANCES[ville] === undefined) return;
    const allerRetour = document.querySelector(`input[name="ligne_${i}_aller_retour"]`).checked;
    const distance = allerRetour ? REMB_DISTANCES[ville] * 2 : REMB_DISTANCES[ville];
    document.querySelector(`input[name="ligne_${i}_distance"]`).value = distance;
    _rembAutoMontantKm(i);
}

// Coché/décoché sur "Trajet aller-retour" : pour une ville connue, recalcule
// depuis la distance de référence (fiable, pas de dérive possible). Pour une
// ville absente de la liste (distance saisie à la main), il n'y a pas de valeur
// de référence à relire : on double/divise par deux la distance déjà saisie,
// sinon cocher la case n'avait aucun effet pour les villes hors liste.
function _rembToggleAllerRetour(i) {
    const ville = document.querySelector(`input[name="ligne_${i}_arrivee"]`).value.trim();
    const allerRetour = document.querySelector(`input[name="ligne_${i}_aller_retour"]`).checked;
    const distanceInput = document.querySelector(`input[name="ligne_${i}_distance"]`);

    if (REMB_DISTANCES[ville] !== undefined) {
        distanceInput.value = allerRetour ? REMB_DISTANCES[ville] * 2 : REMB_DISTANCES[ville];
    } else {
        const current = parseFloat(distanceInput.value) || 0;
        distanceInput.value = Math.round((allerRetour ? current * 2 : current / 2) * 10) / 10;
    }
    _rembAutoMontantKm(i);
}

// Recalcule le montant kilométrique (distance × tarif) à partir de la
// distance actuellement saisie, puis le total de la ligne. Champ non
// modifiable à la main : seuls la distance et les "autres frais" le sont.
function _rembAutoMontantKm(i) {
    const distance = parseFloat(document.querySelector(`input[name="ligne_${i}_distance"]`).value) || 0;
    document.querySelector(`input[name="ligne_${i}_montant_km"]`).value = (distance * REMB_TARIF_KM).toFixed(2);
    _rembRecalcTotal(i);
}

// Montant total de la ligne = frais kilométriques + autres frais (péages…).
function _rembRecalcTotal(i) {
    const montantKm    = parseFloat(document.querySelector(`input[name="ligne_${i}_montant_km"]`).value) || 0;
    const autresFrais  = parseFloat(document.querySelector(`input[name="ligne_${i}_autres_frais"]`).value) || 0;
    document.querySelector(`input[name="ligne_${i}_montant"]`).value = (montantKm + autresFrais).toFixed(2);

    // Justificatif obligatoire uniquement s'il y a d'autres frais à justifier
    // (péages, etc.) : un trajet kilométrique seul n'a rien à joindre.
    const fileInput = document.querySelector(`input[name="ligne_${i}_justificatif"]`);
    if (fileInput) fileInput.required = autresFrais > 0;
}

// Convertit une photo (JPEG/PNG…) en PDF via un <canvas> + jsPDF, pour que les
// utilisateurs sur smartphone puissent joindre une photo prise depuis leur galerie :
// le serveur n'accepte que du PDF (voir send_demande.php), donc la conversion doit
// se faire ici, avant l'envoi.
function _rembImageToPdf(file) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            try {
                const maxDim = 1600;
                const scale  = Math.min(1, maxDim / Math.max(img.naturalWidth, img.naturalHeight));
                const w = Math.round(img.naturalWidth * scale);
                const h = Math.round(img.naturalHeight * scale);
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ orientation: w >= h ? 'landscape' : 'portrait', unit: 'mm', format: 'a4' });
                const pageW = doc.internal.pageSize.getWidth();
                const pageH = doc.internal.pageSize.getHeight();
                const margin = 10;
                const ratio  = Math.min((pageW - margin * 2) / w, (pageH - margin * 2) / h);
                const drawW  = w * ratio;
                const drawH  = h * ratio;
                doc.addImage(dataUrl, 'JPEG', (pageW - drawW) / 2, (pageH - drawH) / 2, drawW, drawH);

                const name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.pdf';
                resolve(new File([doc.output('blob')], name, { type: 'application/pdf' }));
            } catch (err) {
                reject(err);
            } finally {
                URL.revokeObjectURL(img.src);
            }
        };
        img.onerror = reject;
        img.src = URL.createObjectURL(file);
    });
}

// Déclenché à la sélection d'un justificatif : si c'est une photo, la convertit en
// PDF puis remplace le contenu de l'<input type="file"> (via DataTransfer, le seul
// moyen d'assigner des fichiers à un input natif) pour que l'envoi se comporte
// exactement comme si un PDF avait été choisi directement.
async function _rembHandleJustificatifChange(i) {
    const input  = document.querySelector(`input[name="ligne_${i}_justificatif"]`);
    const status = document.getElementById(`remb-file-status-${i}`);
    const file   = input.files[0];

    if (status) { status.textContent = ''; status.className = 'remb-file-status'; }
    if (!file || !file.type.startsWith('image/')) return;

    if (status) { status.textContent = '⏳ Conversion de la photo en PDF…'; status.className = 'remb-file-status'; }
    input.disabled = true;
    try {
        const pdfFile = await _rembImageToPdf(file);
        const dt = new DataTransfer();
        dt.items.add(pdfFile);
        input.files = dt.files;
        if (status) { status.textContent = `✓ Photo convertie en PDF (${pdfFile.name})`; status.className = 'remb-file-status success'; }
    } catch {
        input.value = '';
        if (status) { status.textContent = 'Erreur lors de la conversion de la photo. Réessayez ou joignez directement un PDF.'; status.className = 'remb-file-status error'; }
    } finally {
        input.disabled = false;
    }
}

function _rembTeamOptions() {
    let html = '<option value="" disabled selected>Choisissez une équipe</option>';
    if (REMB_EQUIPES_SENIORS.length) {
        html += '<optgroup label="Seniors">' + REMB_EQUIPES_SENIORS.map(n => `<option value="${n}">${n}</option>`).join('') + '</optgroup>';
    }
    if (REMB_EQUIPES_JEUNES.length) {
        html += '<optgroup label="Jeunes">' + REMB_EQUIPES_JEUNES.map(n => `<option value="${n}">${n}</option>`).join('') + '</optgroup>';
    }
    return html;
}

function addLigne(type) {
    const i = _rembLigneIndex++;
    const wrap = document.createElement('div');
    wrap.className = 'remb-ligne';
    wrap.dataset.index = i;

    const titre = type === 'km' ? '🚗 Remboursement kilométrique' : '📁 Remboursement sur facture';

    const champsKm = `
        <div class="remb-ligne-row">
            <div class="form-group">
                <label>Équipe</label>
                <select name="ligne_${i}_equipe" required>${_rembTeamOptions()}</select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="ligne_${i}_date" required>
            </div>
        </div>
        <div class="remb-ligne-row">
            <div class="form-group">
                <label>Lieu d'arrivée</label>
                <input type="text" name="ligne_${i}_arrivee" list="remb-villes-${i}" placeholder="Ex : Aix Venelles" required oninput="_rembAutoDistance(${i})">
                <datalist id="remb-villes-${i}">${_rembDistanceOptions()}</datalist>
                <p class="remb-field-hint">Départ : Ollioules. Distance pré-remplie pour les villes connues, modifiable sinon.</p>
            </div>
            <div class="form-group form-group--checkbox">
                <label><input type="checkbox" name="ligne_${i}_aller_retour" value="1" onchange="_rembToggleAllerRetour(${i})"> Trajet aller-retour</label>
            </div>
        </div>
        <div class="remb-ligne-row">
            <div class="form-group">
                <label>Distance (km)</label>
                <input type="number" name="ligne_${i}_distance" min="0.1" step="0.1" required oninput="_rembAutoMontantKm(${i})">
            </div>
            <div class="form-group">
                <label>Montant des frais kilométriques (€)</label>
                <input type="number" name="ligne_${i}_montant_km" min="0" step="0.01" value="0.00" required readonly tabindex="-1">
                <p class="remb-field-hint">Calculé automatiquement à ${REMB_TARIF_KM.toString().replace('.', ',')} €/km.</p>
            </div>
        </div>
        <div class="remb-ligne-row">
            <div class="form-group">
                <label>Autres frais (péages, etc.) (€)</label>
                <input type="number" name="ligne_${i}_autres_frais" min="0" step="0.01" placeholder="0" oninput="_rembRecalcTotal(${i})">
            </div>
            <div class="form-group">
                <label>Montant total des frais (€)</label>
                <input type="number" name="ligne_${i}_montant" min="0.01" step="0.01" value="0.00" required readonly tabindex="-1">
            </div>
        </div>
        <div class="remb-ligne-row">
            <div class="form-group">
                <label>Justificatif (PDF ou photo)</label>
                <input type="file" name="ligne_${i}_justificatif" accept="application/pdf,image/*" onchange="_rembHandleJustificatifChange(${i})">
                <p class="remb-field-hint">Obligatoire uniquement si vous avez renseigné d'autres frais (péages, etc.) ci-dessus.</p>
                <p class="remb-field-hint">📷 Une photo sélectionnée depuis la galerie est automatiquement convertie en PDF.</p>
                <p class="remb-file-status" id="remb-file-status-${i}"></p>
            </div>
        </div>`;

    const champsFacture = `
        <div class="remb-ligne-row">
            <div class="form-group">
                <label>Équipe</label>
                <select name="ligne_${i}_equipe" required>${_rembTeamOptions()}</select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="ligne_${i}_date" required>
            </div>
        </div>
        <div class="remb-ligne-row">
            <div class="form-group">
                <label>Motif</label>
                <input type="text" name="ligne_${i}_motif" placeholder="Ex : Achat ballons, frais d'arbitrage…" required>
            </div>
            <div class="form-group">
                <label>Montant (€)</label>
                <input type="number" name="ligne_${i}_montant" min="0.01" step="0.01" required>
            </div>
        </div>
        <div class="remb-ligne-row">
            <div class="form-group">
                <label>Justificatif (PDF ou photo)</label>
                <input type="file" name="ligne_${i}_justificatif" accept="application/pdf,image/*" required onchange="_rembHandleJustificatifChange(${i})">
                <p class="remb-field-hint">📷 Une photo sélectionnée depuis la galerie est automatiquement convertie en PDF.</p>
                <p class="remb-file-status" id="remb-file-status-${i}"></p>
            </div>
        </div>`;

    wrap.innerHTML = `
        <input type="hidden" name="ligne_${i}_type" value="${type}">
        <div class="remb-ligne-header">
            <strong>${titre}</strong>
            <button type="button" class="remb-ligne-delete" onclick="removeLigne(this)" title="Supprimer cette ligne">🗑 Supprimer</button>
        </div>
        ${type === 'km' ? champsKm : champsFacture}`;

    document.getElementById('remb-lignes').appendChild(wrap);
    _rembUpdateState();
}

function removeLigne(btn) {
    btn.closest('.remb-ligne').remove();
    _rembUpdateState();
}

function _rembUpdateState() {
    const lignes = document.querySelectorAll('#remb-lignes .remb-ligne');
    document.getElementById('remb-lignes-empty').style.display = lignes.length ? 'none' : 'block';
    document.getElementById('remb-nb-lignes').value = _rembLigneIndex;
}

// ── Génération du PDF "Justificatif de frais" (comme l'ancien formulaire) ──

// Numéro de dossier au format NDF-YYYYMMDD_HHmm-XXXX, généré côté client :
// simple référence lisible, aucune unicité à garantir puisque rien n'est stocké en base.
function _rembGenerateDossierNumero() {
    const now  = new Date();
    const pad  = n => String(n).padStart(2, '0');
    const date = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}`;
    const time = `${pad(now.getHours())}${pad(now.getMinutes())}`;
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = '';
    for (let i = 0; i < 4; i++) code += alphabet[Math.floor(Math.random() * alphabet.length)];
    return `NDF-${date}_${time}-${code}`;
}

// Nettoie un nom pour l'utiliser dans un nom de fichier (sans accent, alphanumérique).
// Doit rester strictement identique à remb_safe_filename_part() côté PHP.
function _rembSafeFilenamePart(s) {
    return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-zA-Z0-9]/g, '');
}

// Relit les lignes telles que remplies dans le formulaire (même ordre, mêmes champs
// que ceux envoyés au serveur), pour construire le récapitulatif PDF.
function _rembCollectLignes() {
    return [...document.querySelectorAll('#remb-lignes .remb-ligne')].map(wrap => {
        const i    = wrap.dataset.index;
        const val  = name => wrap.querySelector(`[name="ligne_${i}_${name}"]`)?.value || '';
        const type = val('type');
        return {
            type,
            date:    val('date'),
            equipe:  val('equipe'),
            montant: parseFloat(val('montant')) || 0,
            montantKm: parseFloat(val('montant_km')) || 0,
            autresFrais: parseFloat(val('autres_frais')) || 0,
            arrivee: val('arrivee'),
            distance: parseFloat(val('distance')) || 0,
            allerRetour: wrap.querySelector(`[name="ligne_${i}_aller_retour"]`)?.checked || false,
            motif: val('motif'),
            hasJustificatif: !!(wrap.querySelector(`[name="ligne_${i}_justificatif"]`)?.files?.length),
        };
    });
}

// Charge le logo et le redessine à taille réduite sur un <canvas> avant de
// l'exporter en JPEG : le fichier source (LogoVBO.png) fait 2293×3000px, et
// jsPDF l'intègre tel quel sans compression s'il est passé en PNG (~27 Mo
// pour le PDF entier). En limitant la résolution et en passant par le JPEG,
// le logo reste net sur la page tout en gardant le PDF léger.
function _rembLogoDataUrl(url, maxWidthPx = 300) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            const scale  = Math.min(1, maxWidthPx / img.naturalWidth);
            const canvas = document.createElement('canvas');
            canvas.width  = Math.round(img.naturalWidth * scale);
            canvas.height = Math.round(img.naturalHeight * scale);
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            resolve({ dataUrl: canvas.toDataURL('image/jpeg', 0.9), ratio: canvas.height / canvas.width });
        };
        img.onerror = reject;
        img.src = url;
    });
}

// Construit le PDF récapitulatif (même structure que l'ancien formulaire Google :
// numéro de dossier, bénéficiaire, montant total, tableau des lignes).
async function _rembGenerateRecapPdf(dossier, prenom, nom, lignes) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.getWidth();

    try {
        const { dataUrl, ratio } = await _rembLogoDataUrl('/images/logo-club/LogoVBO.png');
        const w = 26, h = w * ratio;
        doc.addImage(dataUrl, 'JPEG', pageWidth / 2 - w / 2, 10, w, h);
    } catch {}

    doc.setFont(undefined, 'bold');
    doc.setFontSize(20);
    doc.text('JUSTIFICATIF DE FRAIS', pageWidth / 2, 50, { align: 'center' });

    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const dateDemande = `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    const montantTotal = lignes.reduce((s, l) => s + l.montant, 0);

    doc.setFontSize(10);
    doc.setFont(undefined, 'normal');
    let y = 65;
    doc.text(`Numéro de dossier : ${dossier}`, 14, y); y += 7;
    doc.text(`Date de la demande : ${dateDemande}`, 14, y); y += 7;
    doc.text(`Bénéficiaire : ${nom} ${prenom}`, 14, y); y += 7;
    doc.setFont(undefined, 'bold');
    doc.text(`Montant total : ${montantTotal.toFixed(2)} €`, 14, y); y += 10;
    doc.setFont(undefined, 'normal');

    const nomSafe = _rembSafeFilenamePart(nom);
    const cols = [
        { label: 'Date',         w: 20 },
        { label: 'Équipe',       w: 32 },
        { label: 'Détails',      w: 62 },
        { label: 'Km',           w: 16 },
        { label: 'Montant',      w: 22 },
        { label: 'Justificatif', w: 30 },
    ];
    const marginX  = 14;
    const lineH    = 4.2;
    const cellPad  = 1.5;

    function rowCells(l, num) {
        const dateFr = l.date ? l.date.split('-').reverse().join('/').slice(0, 8) : '';
        // Police par défaut de jsPDF (Helvetica/WinAnsi) : pas de glyphe "→", on reste en ASCII.
        const tarifStr = REMB_TARIF_KM.toString().replace('.', ',');
        let details = l.motif;
        if (l.type === 'km') {
            details = `Ollioules -> ${l.arrivee} (${l.allerRetour ? 'aller-retour' : 'aller simple'}) - ${l.distance} km x ${tarifStr} EUR/km = ${l.montantKm.toFixed(2)} EUR`;
            if (l.autresFrais > 0) details += ` + ${l.autresFrais.toFixed(2)} EUR (autres frais)`;
        }
        const km = l.type === 'km' ? String(l.distance) : '-';
        return [
            dateFr,
            l.equipe,
            details,
            km,
            `${l.montant.toFixed(2)} €`,
            l.hasJustificatif ? `${dossier}_${nomSafe}_Justificatif_${num}.pdf` : '-',
        ];
    }

    function drawRow(cells, isHeader) {
        doc.setFont(undefined, isHeader ? 'bold' : 'normal');
        doc.setFontSize(isHeader ? 9 : 8.5);
        const wrapped = cells.map((text, idx) => doc.splitTextToSize(String(text), cols[idx].w - cellPad * 2));
        const rowLines = Math.max(...wrapped.map(w => w.length));
        const rowH = rowLines * lineH + cellPad * 2;

        if (y + rowH > 280) { doc.addPage(); y = 20; }
        if (isHeader) { doc.setFillColor(240, 240, 240); doc.rect(marginX, y, cols.reduce((s, c) => s + c.w, 0), rowH, 'F'); }

        let x = marginX;
        cols.forEach((col, idx) => {
            doc.rect(x, y, col.w, rowH);
            doc.text(wrapped[idx], x + cellPad, y + cellPad + lineH - 1);
            x += col.w;
        });
        y += rowH;
    }

    drawRow(cols.map(c => c.label), true);
    lignes.forEach((l, idx) => drawRow(rowCells(l, idx + 1), false));

    return doc;
}

document.addEventListener('DOMContentLoaded', () => {
    loadHTML('/commun/menu.html', 'menu');
    loadHTML('/commun/footer.php', 'footer');

    document.getElementById('remb-form').addEventListener('submit', async e => {
        e.preventDefault();
        const nbLignes = document.querySelectorAll('#remb-lignes .remb-ligne').length;
        const status = document.getElementById('remb-status');
        const btn    = document.getElementById('remb-submit-btn');

        if (nbLignes === 0) {
            status.textContent = 'Ajoutez au moins une ligne de remboursement.';
            status.className   = 'remb-status error';
            return;
        }

        btn.disabled    = true;
        btn.textContent = 'Envoi en cours…';
        status.textContent = '';
        status.className   = 'remb-status';

        try {
            const prenom  = document.getElementById('r-prenom').value.trim();
            const nom     = document.getElementById('r-nom').value.trim();
            const lignes  = _rembCollectLignes();
            const dossier = _rembGenerateDossierNumero();

            status.textContent = 'Préparation du justificatif…';
            const recapDoc      = await _rembGenerateRecapPdf(dossier, prenom, nom, lignes);
            const recapFilename = `${dossier}_${_rembSafeFilenamePart(nom)}.pdf`;
            const recapBlob     = recapDoc.output('blob');

            status.textContent = 'Envoi en cours…';
            const fd = new FormData(e.target);
            fd.append('dossier_numero', dossier);
            fd.append('recap_pdf', recapBlob, recapFilename);

            const res  = await fetch('/php/remboursement/send_demande.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                status.textContent = '✓ Votre demande a bien été envoyée. Dossier ' + dossier + '.';
                status.className   = 'remb-status success';
                e.target.reset();
                document.getElementById('remb-lignes').innerHTML = '';
                _rembLigneIndex = 0;
                _rembUpdateState();
            } else {
                status.textContent = json.error || 'Erreur lors de l\'envoi.';
                status.className   = 'remb-status error';
            }
        } catch {
            status.textContent = 'Erreur réseau.';
            status.className   = 'remb-status error';
        } finally {
            btn.disabled    = false;
            btn.textContent = 'Envoyer la demande';
        }
    });
});
