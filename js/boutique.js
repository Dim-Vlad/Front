(function () {
    'use strict';

    /* ── État ── */
    var _bqIsAdmin = false;
    var _bqArticles = [];
    var _bqHelloAssoUrl = '';
    var _bqEditingId = null;

    /* ── Init ── */
    document.addEventListener('DOMContentLoaded', function () {
        _bqCheckAuth();
    });

    function _bqCheckAuth() {
        fetch('/php/session_status.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var roles = data.roles || [];
                _bqIsAdmin = data.logged_in &&
                    (roles.includes('admin') || roles.includes('moderateur'));
                _bqLoadAll();
            })
            .catch(function () { _bqLoadAll(); });
    }

    function _bqLoadAll() {
        Promise.all([
            fetch('/php/boutique/get_config.php').then(function (r) { return r.json(); }),
            fetch('/php/boutique/get_articles.php').then(function (r) { return r.json(); })
        ]).then(function (results) {
            var cfg = results[0];
            var arts = results[1];

            if (cfg.success) _bqHelloAssoUrl = cfg.helloasso_url || '';
            if (arts.success) _bqArticles = arts.articles || [];

            if (_bqIsAdmin) _bqRenderAdminBar();
            _bqRenderSections();
            _bqSetupHelloAsso();
            _bqSetupKeyboard();
        }).catch(function () {
            _bqRenderSections();
            _bqSetupHelloAsso();
        });
    }

    /* ── Barre admin ── */
    function _bqRenderAdminBar() {
        var bar = document.createElement('div');
        bar.className = 'bq-admin-bar';
        bar.innerHTML = '<span class="bq-admin-bar-label">Mode administration</span>';
        var main = document.getElementById('bq-main');
        main.insertBefore(bar, main.firstChild);
    }

    /* ── Rendu des sections ── */
    function _bqRenderSections() {
        var bySection = { promo: [], vedette: [], base: [] };
        _bqArticles.forEach(function (a) {
            if (bySection[a.section]) bySection[a.section].push(a);
        });

        ['promo', 'vedette', 'base'].forEach(function (sec) {
            var section = document.getElementById('bq-section-' + sec);
            var grid = document.getElementById('bq-grid-' + sec);
            var items = bySection[sec];

            grid.innerHTML = '';

            if (_bqIsAdmin || items.length > 0) {
                section.hidden = false;
                items.forEach(function (a) {
                    grid.appendChild(_bqMakeCard(a));
                });
                if (_bqIsAdmin) {
                    grid.appendChild(_bqMakeAddCard(sec));
                }
            } else {
                section.hidden = true;
            }
        });
    }

    /* ── Carte article ── */
    function _bqMakeCard(a) {
        var card = document.createElement('div');
        card.className = 'bq-card' + (a.actif === 0 || a.actif === '0' ? ' bq-card--inactive' : '');
        card.dataset.id = a.id;

        var imgSrc = '/' + a.image_path;
        var badgeHtml = '';
        if (a.section === 'promo')   badgeHtml = '<span class="bq-badge bq-badge--promo">Promo</span>';
        if (a.section === 'vedette') badgeHtml = '<span class="bq-badge bq-badge--vedette">Vedette</span>';

        var adminHtml = _bqIsAdmin
            ? '<div class="bq-card-admin">' +
              '<button class="bq-cadmin-btn bq-cadmin-btn--edit">Modifier</button>' +
              '<button class="bq-cadmin-btn bq-cadmin-btn--del">Supprimer</button>' +
              '</div>'
            : '';

        var desc = a.description ? '<p>' + _bqEsc(a.description) + '</p>' : '';

        card.innerHTML =
            '<div class="bq-card-img">' +
            '<img src="' + _bqEscAttr(imgSrc) + '" alt="' + _bqEscAttr(a.nom) + '" loading="lazy">' +
            badgeHtml +
            adminHtml +
            '</div>' +
            '<div class="bq-card-body"><h3>' + _bqEsc(a.nom) + '</h3>' + desc + '</div>';

        // Listeners ajoutés après innerHTML : évite les problèmes d'échappement (apostrophes, etc.)
        var cardImg = card.querySelector('.bq-card-img');
        cardImg.addEventListener('click', function (e) {
            if (e.target.closest('.bq-card-admin')) return;
            bqOpenLb(imgSrc, a.nom, a.description || '');
        });

        if (_bqIsAdmin) {
            card.querySelector('.bq-cadmin-btn--edit').addEventListener('click', function (e) {
                e.stopPropagation();
                bqEditArticle(a.id);
            });
            card.querySelector('.bq-cadmin-btn--del').addEventListener('click', function (e) {
                e.stopPropagation();
                bqDeleteArticle(a.id);
            });
        }

        return card;
    }

    /* ── Carte "Ajouter" ── */
    function _bqMakeAddCard(sec) {
        var card = document.createElement('div');
        card.className = 'bq-card bq-card--add';
        card.onclick = function () { bqOpenModalAdd(sec); };
        card.innerHTML = '<span class="bq-add-icon">+</span><span>Ajouter un article</span>';
        return card;
    }

    /* ── HelloAsso ── */
    function _bqSetupHelloAsso() {
        var iframe = document.getElementById('bq-helloasso-iframe');
        if (!iframe) return;

        // Bouton de configuration admin au-dessus de la section
        if (_bqIsAdmin) {
            var container = document.getElementById('bq-ha-action');
            if (container) {
                var btn = document.createElement('button');
                btn.className = 'bq-bar-btn bq-ha-cfg-btn';
                btn.textContent = 'Modifier le lien';
                btn.onclick = bqOpenConfigModal;
                container.appendChild(btn);
            }
        }

        if (_bqHelloAssoUrl) {
            iframe.src = _bqHelloAssoUrl;
            iframe.style.height = '700px';
        } else {
            var section = document.getElementById('bq-section-helloasso');
            if (!_bqIsAdmin && section) section.hidden = true;
        }

        window.addEventListener('message', function (e) {
            if (e.data && e.data.type === 'ha-widget-message' && e.data.height) {
                iframe.style.height = e.data.height + 'px';
            }
        });
    }

    /* ── Lightbox ── */
    window.bqOpenLb = function (src, title, desc) {
        var lb = document.getElementById('bq-lb');
        document.getElementById('bq-lb-img').src = src;
        document.getElementById('bq-lb-img').alt = title;
        document.getElementById('bq-lb-title').textContent = title;
        var descEl = document.getElementById('bq-lb-desc');
        descEl.textContent = desc || '';
        descEl.hidden = !desc;
        lb.classList.add('bq-lb--open');
        document.body.style.overflow = 'hidden';
    };

    window.bqCloseLb = function () {
        document.getElementById('bq-lb').classList.remove('bq-lb--open');
        document.body.style.overflow = '';
    };

    /* ── Modale admin ── */
    function _bqOpenModal(innerHtml) {
        document.getElementById('bq-modal-inner').innerHTML = innerHtml;
        document.getElementById('bq-overlay').classList.add('bq-overlay--open');
        document.body.style.overflow = 'hidden';
    }

    window.bqCloseModal = function () {
        document.getElementById('bq-overlay').classList.remove('bq-overlay--open');
        document.body.style.overflow = '';
        _bqEditingId = null;
    };

    /* ── Formulaire article ── */
    function _bqArticleFormHtml(article) {
        var isEdit = !!article;
        var nom = isEdit ? _bqEscAttr(article.nom) : '';
        var desc = isEdit ? _bqEsc(article.description || '') : '';
        var ordre = isEdit ? article.ordre : 0;
        var actif = !isEdit || article.actif == 1;

        var sectionOptions = ['promo', 'vedette', 'base'].map(function (s) {
            var sel = (isEdit && article.section === s) ? ' selected' : (!isEdit && s === 'base' ? ' selected' : '');
            var labels = { promo: 'Promotions', vedette: 'En vedette', base: 'Articles de base' };
            return '<option value="' + s + '"' + sel + '>' + labels[s] + '</option>';
        }).join('');

        var imgPreview = '';
        if (isEdit && article.image_path) {
            imgPreview = '<div class="bq-form-cur-img">' +
                '<img src="/' + _bqEscAttr(article.image_path) + '" alt="Image actuelle">' +
                '</div>';
        }

        return '<h2 class="bq-modal-title">' + (isEdit ? 'Modifier l\'article' : 'Ajouter un article') + '</h2>' +
            '<div class="bq-mf-row"><label>Nom <span class="bq-mf-req">*</span>' +
            '<input type="text" id="bq-f-nom" value="' + nom + '" required maxlength="150"></label></div>' +
            '<div class="bq-mf-row"><label>Section <span class="bq-mf-req">*</span>' +
            '<select id="bq-f-section">' + sectionOptions + '</select></label></div>' +
            '<div class="bq-mf-row"><label>Description <span class="bq-mf-opt">(optionnel)</span>' +
            '<textarea id="bq-f-desc">' + desc + '</textarea></label></div>' +
            '<div class="bq-mf-row"><label>Ordre d\'affichage' +
            '<input type="number" id="bq-f-ordre" value="' + ordre + '" min="0" step="1"></label></div>' +
            '<div class="bq-mf-row"><label class="bq-mf-check-label">' +
            '<input type="checkbox" id="bq-f-actif"' + (actif ? ' checked' : '') + '> Visible (actif)</label></div>' +
            '<div class="bq-mf-row"><label>Image ' +
            (isEdit ? '<span class="bq-mf-opt">(laisser vide pour conserver)</span>' : '<span class="bq-mf-req">*</span>') +
            imgPreview +
            '<input type="file" id="bq-f-img" accept=".jpg,.jpeg,.png,.webp"></label></div>' +
            '<div id="bq-f-err" class="bq-form-err" hidden></div>' +
            '<button class="bq-modal-submit" onclick="bqSaveArticle()">' +
            (isEdit ? 'Enregistrer les modifications' : 'Créer l\'article') + '</button>';
    }

    window.bqOpenModalAdd = function (section) {
        if (!_bqIsAdmin) return;
        _bqEditingId = null;
        _bqOpenModal(_bqArticleFormHtml(null));
        if (section) {
            var sel = document.getElementById('bq-f-section');
            if (sel) sel.value = section;
        }
    };

    window.bqEditArticle = function (id) {
        if (!_bqIsAdmin) return;
        var article = _bqArticles.find(function (a) { return a.id == id; });
        if (!article) return;
        _bqEditingId = id;
        _bqOpenModal(_bqArticleFormHtml(article));
    };

    window.bqSaveArticle = function () {
        var nomEl = document.getElementById('bq-f-nom');
        var nom = nomEl ? nomEl.value : '';
        if (!nom.trim()) { _bqShowFormErr('Le nom est requis.'); return; }

        var section   = document.getElementById('bq-f-section').value;
        var desc      = document.getElementById('bq-f-desc').value;
        var ordre     = document.getElementById('bq-f-ordre').value;
        var actif     = document.getElementById('bq-f-actif').checked ? '1' : '0';
        var fileInput = document.getElementById('bq-f-img');

        var fd = new FormData();
        if (_bqEditingId) fd.append('id', _bqEditingId);
        fd.append('nom', nom.trim());
        fd.append('section', section);
        fd.append('description', desc);
        fd.append('ordre', ordre || '0');
        fd.append('actif', actif);
        if (fileInput && fileInput.files[0]) fd.append('image', fileInput.files[0]);

        var btn = document.querySelector('.bq-modal-submit');
        if (btn) { btn.disabled = true; btn.textContent = 'Envoi en cours…'; }

        fetch('/php/boutique/upsert_article.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    _bqShowFormErr(data.error || 'Erreur inconnue');
                    if (btn) { btn.disabled = false; btn.textContent = _bqEditingId ? 'Enregistrer les modifications' : 'Créer l\'article'; }
                    return;
                }
                bqCloseModal();
                _bqRefresh();
            })
            .catch(function () {
                _bqShowFormErr('Erreur de communication avec le serveur.');
                if (btn) btn.disabled = false;
            });
    };

    window.bqDeleteArticle = function (id) {
        if (!_bqIsAdmin) return;
        var article = _bqArticles.find(function (a) { return a.id == id; });
        var nom = article ? article.nom : '#' + id;
        if (!confirm('Supprimer l\'article "' + nom + '" ?\nCette action est irréversible.')) return;

        var fd = new FormData();
        fd.append('id', id);
        fetch('/php/boutique/delete_article.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    _bqRefresh();
                } else {
                    alert('Erreur : ' + (data.error || 'Suppression impossible'));
                }
            })
            .catch(function () { alert('Erreur de communication avec le serveur.'); });
    };

    /* ── Config HelloAsso ── */
    window.bqOpenConfigModal = function () {
        if (!_bqIsAdmin) return;
        var cur = _bqEscAttr(_bqHelloAssoUrl);
        _bqOpenModal(
            '<h2 class="bq-modal-title">Lien HelloAsso</h2>' +
            '<p class="bq-modal-desc">Saisissez l\'URL du formulaire de commande HelloAsso de la boutique.</p>' +
            '<div class="bq-mf-row"><label>URL HelloAsso <span class="bq-mf-req">*</span>' +
            '<input type="url" id="bq-cfg-url" value="' + cur + '" placeholder="https://www.helloasso.com/…" required>' +
            '</label></div>' +
            '<div id="bq-f-err" class="bq-form-err" hidden></div>' +
            '<button class="bq-modal-submit" onclick="bqSaveConfig()">Enregistrer</button>'
        );
    };

    window.bqSaveConfig = function () {
        var urlEl = document.getElementById('bq-cfg-url');
        var url = urlEl ? urlEl.value : '';
        if (!url.trim()) { _bqShowFormErr('L\'URL est requise.'); return; }

        var fd = new FormData();
        fd.append('helloasso_url', url.trim());

        var btn = document.querySelector('.bq-modal-submit');
        if (btn) { btn.disabled = true; btn.textContent = 'Enregistrement…'; }

        fetch('/php/boutique/save_config.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    _bqShowFormErr(data.error || 'Erreur inconnue');
                    if (btn) { btn.disabled = false; btn.textContent = 'Enregistrer'; }
                    return;
                }
                _bqHelloAssoUrl = url.trim();
                var iframe = document.getElementById('bq-helloasso-iframe');
                if (iframe) {
                    iframe.src = _bqHelloAssoUrl;
                    iframe.style.height = '700px';
                }
                var section = document.getElementById('bq-section-helloasso');
                if (section) section.hidden = false;
                bqCloseModal();
            })
            .catch(function () {
                _bqShowFormErr('Erreur de communication avec le serveur.');
                if (btn) btn.disabled = false;
            });
    };

    /* ── Utilitaires ── */
    function _bqRefresh() {
        fetch('/php/boutique/get_articles.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    _bqArticles = data.articles || [];
                }
                _bqRenderSections();
            })
            .catch(function () {
                _bqRenderSections();
            });
    }

    function _bqShowFormErr(msg) {
        var el = document.getElementById('bq-f-err');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
    }

    function _bqEsc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function _bqEscAttr(str) {
        return String(str || '').replace(/'/g, '&#39;').replace(/"/g, '&quot;');
    }

    function _bqSetupKeyboard() {
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                bqCloseLb();
                bqCloseModal();
            }
        });
    }

})();
