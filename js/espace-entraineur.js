'use strict';
/* global IS_MOD */

const IS_IOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

let _sections     = [];
let _editingDocId = null;
let _editSecId    = null;

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadData);

// ── Data ──────────────────────────────────────────────────────────────────────
async function loadData() {
    const container = document.getElementById('sections-container');
    container.innerHTML = '<p class="ent-loading">Chargement…</p>';
    try {
        const res  = await fetch('/php/entraineur/get_documents.php');
        const json = await res.json();
        if (!json.success) throw new Error(json.error);
        _sections = json.data;
        renderAll();
    } catch {
        container.innerHTML = '<p class="ent-error">Erreur lors du chargement des documents.</p>';
    }
}

function renderAll() {
    const container = document.getElementById('sections-container');
    container.innerHTML = '';
    if (_sections.length === 0) {
        container.innerHTML = '<p class="ent-empty">Aucun document disponible pour le moment.</p>';
        return;
    }
    _sections.forEach(s => container.appendChild(buildSectionEl(s)));
}

// ── Section builder ───────────────────────────────────────────────────────────
function buildSectionEl(section) {
    const el = document.createElement('div');
    el.className  = 'ent-section';
    el.dataset.id = section.id;

    const hdr = document.createElement('div');
    hdr.className = 'ent-section-header';

    const h3 = document.createElement('h3');
    h3.textContent = section.label;
    hdr.appendChild(h3);

    if (typeof IS_MOD !== 'undefined' && IS_MOD) {
        const admBtns = document.createElement('div');
        admBtns.className = 'section-admin-btns';

        const renBtn = document.createElement('button');
        renBtn.className   = 'btn-sec-rename';
        renBtn.title       = 'Renommer';
        renBtn.textContent = '✎';
        renBtn.addEventListener('click', () => openEditSection(section.id));

        const delBtn = document.createElement('button');
        delBtn.className   = 'btn-sec-delete';
        delBtn.title       = 'Supprimer la section';
        delBtn.textContent = '🗑';
        delBtn.addEventListener('click', () => deleteSection(section.id));

        admBtns.appendChild(renBtn);
        admBtns.appendChild(delBtn);
        hdr.appendChild(admBtns);
    }
    el.appendChild(hdr);

    const list = document.createElement('div');
    list.className = 'ent-doc-list';
    const docs = section.documents || [];
    if (docs.length === 0) {
        const empty = document.createElement('p');
        empty.className   = 'ent-empty-section';
        empty.textContent = 'Aucun document dans cette section.';
        list.appendChild(empty);
    } else {
        docs.forEach(d => list.appendChild(buildDocEl(d)));
    }
    el.appendChild(list);

    if (typeof IS_MOD !== 'undefined' && IS_MOD) {
        const addBtn = document.createElement('button');
        addBtn.className   = 'btn-add-doc';
        addBtn.textContent = '＋ Ajouter un document';
        addBtn.addEventListener('click', () => openAddDoc(section.id));
        el.appendChild(addBtn);
    }

    return el;
}

// ── Document builder ──────────────────────────────────────────────────────────
function buildDocEl(doc) {
    const item = document.createElement('div');
    item.className  = 'ent-doc-item';
    item.dataset.id = doc.id;

    const btn = document.createElement('button');
    btn.className = 'ent-doc-btn';
    btn.innerHTML = `<span class="ent-doc-icon">${doc.type === 'pdf' ? '📄' : '🔗'}</span>
        <span class="ent-doc-label">${escHtml(doc.label)}</span>
        ${doc.type !== 'pdf' ? '<span class="ent-doc-ext">↗</span>' : ''}`;
    btn.addEventListener('click', () => {
        if (doc.type === 'pdf') openPdfModal(doc.url, doc.label);
        else window.open(doc.url, '_blank');
    });
    item.appendChild(btn);

    if (typeof IS_MOD !== 'undefined' && IS_MOD) {
        const modBtns = document.createElement('div');
        modBtns.className = 'doc-mod-btns';

        const editBtn = document.createElement('button');
        editBtn.className   = 'btn-doc-edit';
        editBtn.title       = 'Modifier';
        editBtn.textContent = '✎';
        editBtn.addEventListener('click', () => openEditDoc(doc.id));

        const delBtn = document.createElement('button');
        delBtn.className   = 'btn-doc-del';
        delBtn.title       = 'Supprimer';
        delBtn.textContent = '🗑';
        delBtn.addEventListener('click', () => deleteDoc(doc.id, doc.label));

        modBtns.appendChild(editBtn);
        modBtns.appendChild(delBtn);
        item.appendChild(modBtns);
    }

    return item;
}

// ── PDF Modal ─────────────────────────────────────────────────────────────────
function openPdfModal(path, label) {
    document.getElementById('pdf-modal-title').textContent   = label;
    document.getElementById('pdf-download-btn').href         = path;
    document.getElementById('pdf-download-btn').download     = label + '.pdf';
    if (IS_IOS) {
        document.getElementById('pdf-iframe').src             = '';
        document.getElementById('pdf-iframe').style.display   = 'none';
        document.getElementById('pdf-fallback').style.display = 'flex';
        document.getElementById('pdf-open-link').href         = path;
    } else {
        document.getElementById('pdf-iframe').style.display   = 'block';
        document.getElementById('pdf-fallback').style.display = 'none';
        document.getElementById('pdf-iframe').src             = path;
    }
    document.getElementById('pdfModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closePdfModal() {
    document.getElementById('pdfModal').classList.remove('open');
    document.body.style.overflow = '';
    document.getElementById('pdf-iframe').src = '';
}

function printPdf() {
    if (IS_IOS) { window.open(document.getElementById('pdf-download-btn').href, '_blank'); return; }
    try { document.getElementById('pdf-iframe').contentWindow.print(); }
    catch { window.open(document.getElementById('pdf-iframe').src, '_blank'); }
}

// ── Document Modal ────────────────────────────────────────────────────────────
function openAddDoc(sectionId) {
    _editingDocId = null;
    document.getElementById('docModalTitle').textContent   = 'Ajouter un document';
    document.getElementById('docForm').reset();
    document.getElementById('pdfKeepHint').style.display  = 'none';
    document.getElementById('docFile').required            = true;
    document.getElementById('docStatus').textContent       = '';
    toggleDocType();
    populateSectionSelect(sectionId);
    document.getElementById('docModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('docLabel').focus(), 50);
}

function openEditDoc(docId) {
    let doc = null;
    for (const s of _sections) {
        doc = (s.documents || []).find(d => +d.id === +docId);
        if (doc) break;
    }
    if (!doc) return;

    _editingDocId = docId;
    document.getElementById('docModalTitle').textContent   = 'Modifier le document';
    document.getElementById('docLabel').value              = doc.label;
    document.getElementById('docFile').required            = false;
    document.getElementById('pdfKeepHint').style.display   = doc.type === 'pdf' ? 'block' : 'none';
    document.getElementById('docUrl').value                = doc.type === 'link' ? doc.url : '';
    document.getElementById('docStatus').textContent       = '';
    document.querySelectorAll('input[name="docType"]').forEach(r => { r.checked = r.value === doc.type; });
    toggleDocType();
    populateSectionSelect(doc.section_id);
    document.getElementById('docModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('docLabel').focus(), 50);
}

function closeDocModal() {
    document.getElementById('docModal').classList.remove('open');
    document.body.style.overflow = '';
}

function toggleDocType() {
    const isPdf = document.querySelector('input[name="docType"]:checked')?.value === 'pdf';
    document.getElementById('pdfGroup').style.display  = isPdf ? '' : 'none';
    document.getElementById('linkGroup').style.display = isPdf ? 'none' : '';
    if (!isPdf) document.getElementById('pdfKeepHint').style.display = 'none';
    if (isPdf && _editingDocId) document.getElementById('pdfKeepHint').style.display = 'block';
}

function populateSectionSelect(selectedId) {
    document.getElementById('docSection').innerHTML = _sections.map(s =>
        `<option value="${s.id}" ${+s.id === +selectedId ? 'selected' : ''}>${escHtml(s.label)}</option>`
    ).join('');
}

async function submitDoc(e) {
    e.preventDefault();
    const statusEl = document.getElementById('docStatus');
    const saveBtn  = document.getElementById('docSaveBtn');
    statusEl.textContent = '';
    saveBtn.disabled     = true;
    saveBtn.textContent  = 'Enregistrement…';

    const fd = new FormData(document.getElementById('docForm'));
    fd.set('type', fd.get('docType'));
    fd.delete('docType');
    if (_editingDocId) fd.set('id', _editingDocId);

    try {
        const res  = await fetch('/php/entraineur/save_document.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Erreur inconnue');
        closeDocModal();
        await loadData();
    } catch (err) {
        statusEl.textContent = err.message;
        statusEl.className   = 'form-status error';
    } finally {
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Enregistrer';
    }
}

async function deleteDoc(docId, label) {
    if (!confirm(`Supprimer « ${label} » ?`)) return;
    try {
        const fd = new FormData();
        fd.set('id', docId);
        const res  = await fetch('/php/entraineur/delete_document.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.error);
        await loadData();
    } catch (err) {
        alert('Erreur : ' + err.message);
    }
}

// ── Section Modal ─────────────────────────────────────────────────────────────
function openAddSection() {
    _editSecId = null;
    document.getElementById('sectionModalTitle').textContent = 'Nouvelle section';
    document.getElementById('sectionLabel').value            = '';
    document.getElementById('sectionStatus').textContent     = '';
    document.getElementById('sectionModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('sectionLabel').focus(), 50);
}

function openEditSection(sectionId) {
    const section = _sections.find(s => +s.id === +sectionId);
    if (!section) return;
    _editSecId = sectionId;
    document.getElementById('sectionModalTitle').textContent = 'Renommer la section';
    document.getElementById('sectionLabel').value            = section.label;
    document.getElementById('sectionStatus').textContent     = '';
    document.getElementById('sectionModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('sectionLabel').focus(), 50);
}

function closeSectionModal() {
    document.getElementById('sectionModal').classList.remove('open');
    document.body.style.overflow = '';
}

async function submitSection(e) {
    e.preventDefault();
    const statusEl = document.getElementById('sectionStatus');
    const saveBtn  = e.target.querySelector('[type="submit"]');
    statusEl.textContent = '';
    saveBtn.disabled     = true;

    const fd = new FormData();
    fd.set('label', document.getElementById('sectionLabel').value.trim());
    if (_editSecId) fd.set('id', _editSecId);

    try {
        const res  = await fetch('/php/entraineur/save_section.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Erreur inconnue');
        closeSectionModal();
        await loadData();
    } catch (err) {
        statusEl.textContent = err.message;
        statusEl.className   = 'form-status error';
    } finally {
        saveBtn.disabled = false;
    }
}

async function deleteSection(sectionId) {
    const section  = _sections.find(s => +s.id === +sectionId);
    const docCount = (section?.documents || []).length;
    const msg = docCount > 0
        ? `Supprimer la section « ${section.label} » et ses ${docCount} document(s) ?`
        : `Supprimer la section « ${section?.label} » ?`;
    if (!confirm(msg)) return;
    try {
        const fd = new FormData();
        fd.set('id', sectionId);
        const res  = await fetch('/php/entraineur/delete_section.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.error);
        await loadData();
    } catch (err) {
        alert('Erreur : ' + err.message);
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
