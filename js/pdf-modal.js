'use strict';

const IS_IOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

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
