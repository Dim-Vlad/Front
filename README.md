# Site Web — Volley Ball Ollioulais (VBO)

Site officiel du club de volley-ball d'Ollioules (Var, 83).  
Stack 100 % vanilla : PHP 8, MySQL, HTML/CSS, JavaScript — aucun framework.

---

## Stack technique

| Couche | Technologie |
|---|---|
| Back-end | PHP 8 (PDO, sessions) |
| Base de données | MySQL / MariaDB |
| Front-end | HTML5, CSS3, JavaScript ES6 (IIFE modules) |
| Police | Poppins (Google Fonts) |
| Déploiement | GitHub Actions → FTP OVH |

---

## Prérequis locaux

- [XAMPP](https://www.apachefriends.org/) (Apache + PHP 8 + MySQL)
- phpMyAdmin pour les migrations
- Git

---

## Installation locale

**1. Cloner le dépôt**
```bash
git clone <url-du-repo>
```

**2. Placer le dossier dans `htdocs`**  
Le projet doit être servi depuis `http://localhost/` (racine), pas un sous-dossier.

**3. Configurer la base de données**

Ouvrir [`php/config.php`](php/config.php) et s'assurer que le bloc **Local** est actif :
```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'vbo_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
```

**4. Créer la base et exécuter les migrations**

Dans phpMyAdmin, créer une base `vbo_db` (utf8mb4), puis exécuter dans cet ordre les fichiers du dossier [`_local/`](_local/) :

```
migration_journal.sql          — tables de logs (journal_activites, journal_connexions)
migration_parametres.sql       — table de configuration clé/valeur
migration_multiroles.sql       — tables utilisateurs et rôles
migration_equipes.sql          — équipes du club
migration_entrainements.sql    — créneaux d'entraînement
migration_evenements.sql       — événements et manifestations
migration_partenaires.sql      — partenaires du club
migration_staff.sql            — membres du staff technique
migration_licence.sql          — configuration page licences
migration_galerie.sql          — photos soumises par les membres
migration_boutique.sql         — articles boutique + URL HelloAsso
```

**5. Lancer XAMPP** et ouvrir `http://localhost/`

---

## Structure du projet

```
/
├── _local/              Migrations SQL (à exécuter une seule fois)
├── commun/              Composants partagés (menu.html, footer.html)
├── css/
│   ├── styles.css       Styles globaux + variables CSS
│   ├── boutique/        Styles de la boutique
│   ├── legal/           Styles mentions légales et politique de confidentialité
│   ├── galerie/
│   ├── leClub/
│   ├── evenements/
│   └── ...
├── documents/           PDF téléchargeables (licences, statuts, PV AG…)
├── images/
│   ├── boutique/        Images des articles boutique (gérées via l'admin)
│   ├── logo-club/
│   ├── partenaires/
│   └── social/          Icônes réseaux sociaux
├── js/
│   ├── main.js          Script global (menu, footer, logo actif)
│   ├── boutique.js
│   ├── galerie.js
│   ├── evenements.js
│   └── ...
├── pages/
│   ├── home.php
│   ├── boutique.html
│   ├── nousContacter.html
│   ├── auth/            Connexion et tableau de bord membres
│   ├── legal/           Mentions légales et politique de confidentialité
│   ├── leClub/          Bénévoles, inscription, staff, entraînements…
│   ├── evenements/
│   ├── galerie/
│   └── partenaires/
├── php/
│   ├── auth.php         Fonctions d'authentification et PDO
│   ├── config.php       Constantes de connexion BDD (local / prod)
│   ├── journal_log.php  Fonction log_activite()
│   ├── session_status.php
│   ├── boutique/        Endpoints API boutique
│   ├── equipes/         CRUD équipes
│   ├── evenements/      CRUD événements
│   ├── staff/           CRUD staff (membres, photos, documents)
│   ├── entrainements/   CRUD créneaux d'entraînement
│   ├── partenaires/     CRUD partenaires
│   ├── logo/            Gestion logo du club
│   ├── licence/         Gestion page licences
│   ├── contact/         Envois d'emails (contact, galerie, stage)
│   ├── misc/            Formulaires annexes (manif sportive, remboursement)
│   ├── galerie/         Endpoints API galerie
│   └── rgpd/            Script de purge des logs
├── photos/
│   ├── galerie/         Photos validées + en attente de modération
│   └── saison-*/        Archives par saison
└── .github/workflows/
    └── deploy.yml       CI/CD déploiement FTP vers OVH
```

---

## Authentification et rôles

Système de session PHP. Les fonctions sont centralisées dans [`php/auth.php`](php/auth.php).

| Fonction | Description |
|---|---|
| `is_logged_in()` | Vérifie si l'utilisateur est connecté |
| `has_role($role)` | Vérifie un rôle unique |
| `has_any_role($roles)` | Vérifie parmi une liste de rôles |
| `require_login()` | Redirige vers la connexion si non connecté |
| `current_user()` | Retourne les données de l'utilisateur courant |
| `get_pdo()` | Retourne l'instance PDO (singleton) |

**Rôles disponibles** : `admin`, `moderateur`, `entraineur`, `arbitre`, `bureau`

L'état de session côté JavaScript est récupéré via `/php/session_status.php`  
(retourne `{ logged_in, roles, prenom, nom, … }`).

---

## Modules CRUD

### Boutique
- **Page** : [`pages/boutique.html`](pages/boutique.html)
- **JS** : [`js/boutique.js`](js/boutique.js) — module IIFE, chargement asynchrone, lightbox, modales admin
- **API** : [`php/boutique/`](php/boutique/) — `get_articles.php`, `upsert_article.php`, `delete_article.php`, `get_config.php`, `save_config.php`
- **Images** : uploadées dans [`images/boutique/`](images/boutique/) (JPG/PNG/WEBP, 15 Mo max)
- Trois sections : **Promotions**, **En vedette**, **Articles de base**
- URL HelloAsso configurable par l'admin (stockée dans la table `parametres`)

### Galerie photos
- Soumission publique avec double consentement RGPD
- Modération par admin/modérateur avant publication
- Photos stockées dans [`photos/galerie/`](photos/galerie/) (`attente/` puis dossier saison courant)

### Événements
- Création, modification, suppression par admin/modérateur
- Marquage "terminé" avec bascule en ligne

### Staff
- Fiches membres avec photo et documents téléchargeables
- Visibilité activable/désactivable par l'admin

### Partenaires
- Logos avec lien externe et ordre d'affichage configurable

### Entraînements & équipes
- Créneaux et salles gérés via le tableau de bord

---

## Journalisation des actions

Toute action admin/modérateur est tracée via `log_activite()` dans [`php/journal_log.php`](php/journal_log.php).

```php
log_activite($pdo, 'ajout' | 'modification' | 'suppression', $entite, $details);
```

**Purge des logs** (à planifier via cron ou exécuter manuellement) :
```bash
php php/rgpd/purge_logs.php
```

| Table | Durée de conservation |
|---|---|
| `journal_connexions` | 90 jours |
| `journal_activites` | 365 jours |

---

## Variables CSS globales

Définies dans [`css/styles.css`](css/styles.css) :

```css
--primary-color:   #acc2ab   /* Vert sauge (accents, badges, fond cartes) */
--secondary-color: #063E0B   /* Vert foncé (titres, boutons principaux) */
--bg-dark:         #223224   /* Vert très foncé (footer, barres admin) */
--font-poppins:    'Poppins', sans-serif
```

---

## Déploiement (production OVH)

Le déploiement est **automatique** à chaque push sur `master` via GitHub Actions  
([`.github/workflows/deploy.yml`](.github/workflows/deploy.yml)).

**Secrets à configurer** dans GitHub (`Settings → Secrets and variables → Actions`) :

| Secret | Description |
|---|---|
| `FTP_SERVER` | Adresse du serveur FTP OVH |
| `FTP_USERNAME` | Identifiant FTP |
| `FTP_PASSWORD` | Mot de passe FTP |

**Avant de pousser en production**, activer le bloc production dans [`php/config.php`](php/config.php) :
```php
define('DB_HOST',    'volleykdimitri.mysql.db');
define('DB_NAME',    'volleykdimitri');
define('DB_USER',    'volleykdimitri');
define('DB_PASS',    '...');
```

> Les migrations SQL doivent être appliquées manuellement sur la base OVH via phpMyAdmin.  
> `config.php` **ne doit jamais être commité** avec les identifiants de production.

---

## RGPD

- Pages légales : [`pages/legal/mentions-legales.html`](pages/legal/mentions-legales.html) et [`pages/legal/politique-confidentialite.html`](pages/legal/politique-confidentialite.html)
- Consentement photo requis dans le formulaire de la galerie (double case à cocher)
- Purge automatisable des logs via [`php/rgpd/purge_logs.php`](php/rgpd/purge_logs.php)
- Liens mentions légales et politique de confidentialité dans le footer sur toutes les pages

---

## Développeur

Dimitri Garrigues — [DG-Dev](vCard/vCard-DG-Dev.html)
