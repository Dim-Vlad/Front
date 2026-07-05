<?php
require_once __DIR__ . '/../../php/auth.php';
require_login();
if (!has_any_role(['entraineur', 'arbitre', 'bureau', 'moderateur', 'admin'])) {
    header('Location: /pages/auth/tableau-de-bord.php');
    exit;
}
$isMod = has_any_role(['admin', 'moderateur']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Documents et ressources pour les entraineurs, arbitres et marqueurs du VBO.">
    <link href="/css/styles.css?v=20260705" rel="stylesheet">
    <link href="/css/leClub/espace-entraineur.css?v=20260623" rel="stylesheet">
    <title>Ressources - VBO</title>
    <link rel="icon" href="/images/favicon-36x36.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div id="menu"></div>

    <div id="content">
        <div class="header-content">
            <img class="logo-club" src="/images/logo-club/LogoVBO.png" alt="Logo du club">
            <div class="text-content">
                <h1>Espace Ressources</h1>
                <p class="explication">Documents et outils pour les entraineurs, arbitres et marqueurs du VBO.</p>
            </div>
        </div>
    </div>

    <a href="/pages/auth/tableau-de-bord.php" class="back-btn">← Retour au tableau de bord</a>

    <main class="ent-main">

        <?php if ($isMod): ?>
        <div class="ent-admin-toolbar">
            <span class="ent-admin-badge">Mode modération</span>
            <button class="btn-new-section" onclick="openAddSection()">＋ Nouvelle section</button>
        </div>
        <?php endif; ?>

        <div id="sections-container">
            <p class="ent-loading">Chargement des documents…</p>
        </div>

        <!-- Carte FFVB (statique) -->
        <div class="ent-ffvb-card">
            <div class="ent-ffvb-icon">🏐</div>
            <div class="ent-ffvb-body">
                <h2>Ressources Volley</h2>
                <p>Retrouvez des documents et ressources utiles mis à disposition par la FFVB.</p>
                <div class="ent-credentials">
                    <span>Identifiant : <strong>ffvolley</strong></span>
                    <span>Mot de passe : <strong>ffvolley2021</strong></span>
                </div>
            </div>
            <a href="https://ressourcesvolley.com/" target="_blank" rel="noopener" class="btn-ent-link">Accéder au site ↗</a>
        </div>

    </main>

    <!-- PDF Modal -->
    <div id="pdfModal" class="pdf-modal" onclick="if(event.target===this)closePdfModal()">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <span class="pdf-modal-title" id="pdf-modal-title"></span>
                <div class="pdf-modal-btns">
                    <button class="btn-pdf-action" onclick="printPdf()">🖨 Imprimer</button>
                    <a id="pdf-download-btn" href="#" download class="btn-pdf-action">⬇ Télécharger</a>
                    <button class="btn-pdf-close" onclick="closePdfModal()" title="Fermer">✕</button>
                </div>
            </div>
            <iframe id="pdf-iframe" src="" title="Visionneuse PDF"></iframe>
            <div id="pdf-fallback">
                <p>L'aperçu PDF n'est pas disponible sur cet appareil.</p>
                <a id="pdf-open-link" href="#" target="_blank">Ouvrir le PDF ↗</a>
            </div>
        </div>
    </div>

    <?php if ($isMod): ?>

    <!-- Modale document -->
    <div id="docModal" class="ent-modal" onclick="if(event.target===this)closeDocModal()">
        <div class="ent-modal-content">
            <h2 class="ent-modal-title" id="docModalTitle">Ajouter un document</h2>
            <form id="docForm" onsubmit="submitDoc(event)" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="docLabel">Titre</label>
                    <input type="text" id="docLabel" name="label" required placeholder="Ex : Feuille de composition">
                </div>
                <div class="form-group">
                    <label for="docSection">Section</label>
                    <select id="docSection" name="section_id" required></select>
                </div>
                <div class="form-group">
                    <label>Type de document</label>
                    <div class="radio-group">
                        <label><input type="radio" name="docType" value="pdf" checked onchange="toggleDocType()"> Fichier PDF</label>
                        <label><input type="radio" name="docType" value="link" onchange="toggleDocType()"> Lien externe</label>
                    </div>
                </div>
                <div class="form-group" id="pdfGroup">
                    <label for="docFile">Fichier PDF</label>
                    <input type="file" id="docFile" name="fichier" accept=".pdf">
                    <small id="pdfKeepHint" style="display:none">
                        Laissez vide pour conserver le fichier actuel.
                    </small>
                </div>
                <div class="form-group" id="linkGroup" style="display:none">
                    <label for="docUrl">URL</label>
                    <input type="url" id="docUrl" name="url" placeholder="https://…">
                </div>
                <p class="form-status" id="docStatus"></p>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeDocModal()">Annuler</button>
                    <button type="submit" class="btn-save" id="docSaveBtn">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modale section -->
    <div id="sectionModal" class="ent-modal" onclick="if(event.target===this)closeSectionModal()">
        <div class="ent-modal-content">
            <h2 class="ent-modal-title" id="sectionModalTitle">Nouvelle section</h2>
            <form id="sectionForm" onsubmit="submitSection(event)">
                <div class="form-group">
                    <label for="sectionLabel">Nom de la section</label>
                    <input type="text" id="sectionLabel" name="label" required placeholder="Ex : Pour les marqueurs">
                </div>
                <p class="form-status" id="sectionStatus"></p>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeSectionModal()">Annuler</button>
                    <button type="submit" class="btn-save">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php endif; ?>

    <div id="footer"></div>

    <?php if ($isMod): ?><script>const IS_MOD = true;</script><?php endif; ?>
    <script src="/js/espace-entraineur.js?v=20260623"></script>
    <script src="/js/main.js?v=20260705"></script>
</body>
</html>
