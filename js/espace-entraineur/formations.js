'use strict';
/* global IS_MOD, openPdfModal */

let _fCurrentId = null;

function openFormationModal(data) {
    document.getElementById('formationForm').reset();
    document.getElementById('f-status').textContent = '';
    document.getElementById('fKeepHint').style.display = 'none';
    document.getElementById('f-file-label').textContent = '📁 Cliquer pour choisir un fichier';

    if (data) {
        _fCurrentId = data.id;
        document.getElementById('formationModalTitle').textContent = 'Modifier la formation';
        document.getElementById('f-id').value          = data.id;
        document.getElementById('f-label').value       = data.label;
        document.getElementById('f-description').value = data.description || '';
        document.getElementById('f-categorie').value   = data.categorie;
        const isLink = data.type === 'lien';
        document.querySelector(`input[name="f-kind"][value="${isLink ? 'lien' : 'fichier'}"]`).checked = true;
        if (isLink) document.getElementById('f-url').value = data.url;
        else document.getElementById('fKeepHint').style.display = 'block';
    } else {
        _fCurrentId = null;
        document.getElementById('formationModalTitle').textContent = 'Ajouter une formation';
        document.getElementById('f-categorie').value = 'entraineur';
        document.querySelector('input[name="f-kind"][value="fichier"]').checked = true;
    }
    toggleFormationKind();
    document.getElementById('formationModal').classList.add('open');
}

function closeFormationModal() {
    document.getElementById('formationModal').classList.remove('open');
}

function toggleFormationKind() {
    const isFichier = document.querySelector('input[name="f-kind"]:checked').value === 'fichier';
    document.getElementById('fDocGroup').style.display = isFichier ? '' : 'none';
    document.getElementById('fUrlGroup').style.display = isFichier ? 'none' : '';
}

function updateFormationFileLabel() {
    const file  = document.getElementById('f-file').files[0];
    const label = document.getElementById('f-file-label');
    label.textContent = file ? `📄 ${file.name}` : '📁 Cliquer pour choisir un fichier';
}

async function submitFormation(e) {
    e.preventDefault();
    const status = document.getElementById('f-status');
    status.textContent = 'Enregistrement…';

    const fd = new FormData();
    if (_fCurrentId) fd.append('id', _fCurrentId);
    fd.append('label', document.getElementById('f-label').value.trim());
    fd.append('description', document.getElementById('f-description').value.trim());
    fd.append('categorie', document.getElementById('f-categorie').value);
    const kind = document.querySelector('input[name="f-kind"]:checked').value;
    fd.append('kind', kind);
    if (kind === 'fichier') {
        const file = document.getElementById('f-file').files[0];
        if (file) fd.append('fichier', file);
    } else {
        fd.append('url', document.getElementById('f-url').value.trim());
    }

    try {
        const res  = await fetch('/php/espace-entraineur/save_formation.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Erreur inconnue');
        location.reload();
    } catch (err) {
        status.textContent = err.message;
    }
}

async function deleteFormation(id, label) {
    if (!confirm(`Supprimer « ${label} » ?`)) return;
    const fd = new FormData();
    fd.append('id', id);
    try {
        const res  = await fetch('/php/espace-entraineur/delete_formation.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.error);
        location.reload();
    } catch (err) {
        alert('Erreur : ' + err.message);
    }
}
