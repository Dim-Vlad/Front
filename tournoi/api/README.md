# API Tournoi VBO — intégrée au site du club (PHP + MySQL, OVH)

Backend de l'application **Tournoi VBO**. L'app vit dans le dossier **`/tournoi/`**
du site du Volley-Ball Ollioulais et **partage la base MySQL du club**. Comme tout
est servi par la même origine, l'authentification se fait par **cookie de session**
(pas de clé d'API, pas de token, pas de CORS).

```
Front/                 ← site du club (racine déployée dans /www/)
├── php/config.php      ← identifiants MySQL du club (déjà en place, NON versionnés)
└── tournoi/            ← l'application de tournois
    ├── index.html, sw.js, manifest.webmanifest, assets/, icons/
    └── api/            ← ce dossier
```

## Deux principes d'intégration

1. **Base partagée, tables préfixées.** L'app crée ses propres tables, toutes
   préfixées **`vbo_`** (`vbo_users`, `vbo_tournaments`, `vbo_matches`…). Elle ne
   touche JAMAIS aux tables du site (qui portent d'autres noms). Les deux cohabitent
   dans la même base sans collision.
2. **Identifiants réutilisés.** `_bootstrap.php` lit en priorité les constantes
   déjà définies par le site dans **`../../php/config.php`** (`DB_HOST`, `DB_NAME`,
   `DB_USER`, `DB_PASS`, `DB_CHARSET`). **Rien à ressaisir** : pas de `config.php`
   à créer pour l'app. (Un `api/config.php` local reste possible en repli si l'app
   est un jour servie hors du site — modèle : `config.example.php`.)

> Comptes séparés : l'app a ses **propres** comptes (admin + référents de tournoi),
> indépendants des comptes du site. On se connecte donc séparément au site et à
> l'app. C'est voulu (rôles et usages différents).

## Contenu du dossier

| Fichier | Rôle |
|---|---|
| `index.php` | Point d'entrée de l'API. Toutes les requêtes passent par `?action=…`. |
| `_bootstrap.php` | Connexion à la base (via `php/config.php` du club), session, helpers. **Non public.** |
| `schema.sql` | Création des tables `vbo_*`. À importer une fois dans phpMyAdmin. |
| `install.php` | Création du premier compte admin de l'app. **À supprimer après usage.** |
| `config.example.php` | Modèle de config **de repli** (inutile dans le cas intégré). |
| `.htaccess` | Protège les fichiers sensibles (config, schéma, bootstrap). |

## Installation (une seule fois)

### 1. Importer les tables de l'app dans la base du club
Ouvrez **phpMyAdmin** (espace client OVH → Hébergement → Bases de données →
phpMyAdmin), sélectionnez **la base du club**, onglet **Importer**, envoyez
`tournoi/api/schema.sql`, puis exécutez. Cela crée uniquement les tables `vbo_*` ;
les tables existantes du site ne sont pas modifiées.

> Aucune base à créer, aucun `config.php` à écrire pour l'app : elle réutilise les
> identifiants du club (`php/config.php`, déjà présents sur le serveur).

### 2. Déployer les fichiers
Le site se déploie tout seul : à chaque **push sur la branche `master`**, l'action
GitHub envoie tout le dépôt sur OVH (`/www/`) par FTP. Le dossier `tournoi/` part
avec le reste → l'app est en ligne sur `https://votre-site/tournoi/`.

### 3. Créer le compte administrateur de l'app
Ouvrez dans le navigateur :

```
https://votre-site/tournoi/api/install.php
```

Saisissez nom, e-mail et mot de passe (8 caractères min.) de l'admin, validez.

### 4. Supprimer / neutraliser install.php ⚠️
La page se **bloque d'elle-même** dès qu'un compte existe (elle renvoie « déjà
installé »). Comme le déploiement FTP la re-téléverse à chaque push, elle ne peut
pas être supprimée durablement côté Git — mais ce n'est pas un risque : elle
refuse de créer un second compte. Si vous voulez la retirer complètement,
supprimez `tournoi/api/install.php` du dépôt après la première installation.

C'est fini : ouvrez `https://votre-site/tournoi/`, connectez-vous en admin.

## Mises à jour suivantes
Poussez sur `master` : l'app est redéployée. Le **service worker** met en cache la
coquille ; `CACHE` dans `sw.js` est incrémenté à chaque évolution (actuellement
`vbo-tournoi-v8`) pour forcer la mise à jour côté navigateur. La base ne bouge pas.

## Dépannage
- **« Configuration absente… »** → `php/config.php` du club introuvable depuis
  `tournoi/api/` (vérifiez l'arborescence `/www/php/config.php`).
- **« Connexion à la base impossible »** → identifiants du club erronés, ou version
  PHP OVH < 8 (passez l'hébergement en **PHP 8.x**).
- **« Base non prête » sur install.php** → `schema.sql` n'a pas été importé dans la
  base du club.
- **Les comptes du site ne marchent pas dans l'app** → normal : l'app a ses propres
  comptes (créés via `install.php` puis par l'admin).

## Référence des actions (`?action=…`)

| Action | Méthode | Accès | Rôle |
|---|---|---|---|
| `auth.login` | POST | public | Connexion (e-mail + mot de passe). |
| `auth.logout` | POST | connecté | Déconnexion. |
| `auth.me` | GET | public | Utilisateur courant (ou `null`). |
| `auth.changePassword` | POST | connecté | Change son propre mot de passe. |
| `tournaments.list` | GET | connecté | Liste des tournois. |
| `tournaments.save` | POST | admin | Crée/met à jour un tournoi. |
| `tournaments.setArchived` | POST | admin | Archive/désarchive. |
| `tournaments.setStatus` | POST | admin | Statut (draft/live/closed). |
| `tournaments.remove` | POST | admin | Supprime un tournoi. |
| `tournaments.saveStructure` | POST | admin | Enregistre gymnases/équipes/poules/matchs. |
| `tournaments.loadStructure` | GET | connecté | Recharge la structure d'un tournoi. |
| `referents.list` | GET | admin | Liste des référents. |
| `referents.create` | POST | admin | Crée un compte référent. |
| `referents.setStatus` | POST | admin | Active/suspend un référent. |
| `referents.remove` | POST | admin | Supprime un référent. |

> Les comptes **capitaines** (saisie des scores) et le **temps réel** ne sont pas
> encore branchés côté application ; la table `vbo_score_entries` est déjà prévue
> pour cette étape ultérieure.
