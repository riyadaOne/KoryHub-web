# KoryHub

**La plateforme d'échange de code des développeurs africains.**

Partout en Afrique, on réécrit sans cesse le même code : paiement Mobile Money, repli SMS quand les données coupent, reçus PDF, synchronisation hors ligne… KoryHub permet de partager ces briques de code éprouvées, appelées **kory**, et de les réutiliser en une commande.

> Le cauri fut la première monnaie d'échange de l'Afrique ; partageons des kory entre développeurs.

**Démo :** https://koryhub.site · **Documentation :** https://docs.koryhub.site · **CLI :** [riyadaOne/KoryHub-cli](https://github.com/riyadaOne/KoryHub-cli)

Projet présenté à la **Coupe d'Afrique des Développeurs (CADEV 2026)** par l'équipe KoryHub (Tchad).

---

## Ce que fait KoryHub

- **Publier un kory** depuis son terminal (`kory push`) : le code, son architecture en diagramme, ses prérequis et un README qui explique le problème résolu et les pièges évités. Les secrets (clés, mots de passe) sont détectés avant toute publication, sur le poste puis sur le serveur.
- **Trouver et installer** : recherche avec filtres (catégorie, langage, certifiés), fiche détaillée (README, architecture cliquable, fichiers, versions, avis), puis `kory pull` avec vérification de l'empreinte SHA-256.
- **Communauté** : avis (marqués « A utilisé ce module » quand l'auteur de l'avis l'a vraiment téléchargé), favoris, suivi des nouvelles versions, profils d'auteurs, notifications.
- **Certification « Vérifié en Prod »** : contrôles automatiques, dossier étudié par l'équipe de modération, rapport PDF figé.
- **Confiance** : signalements, modération, avertissements de sécurité, administration avec supervision, sauvegardes et journal d'audit en ajout seul.
- **Quatre langues** : français, anglais, portugais et arabe (de droite à gauche).

## Les quatre espaces

| Adresse | Rôle |
|---|---|
| `koryhub.site` | site public et espace connecté |
| `api.koryhub.site` | API du CLI (`/v1`) |
| `docs.koryhub.site` | documentation |
| `admin.koryhub.site` | administration (double authentification obligatoire) |

Les domaines se règlent dans `.env` (`KORYHUB_DOMAIN_*`).

## Technologies

Laravel 13 · PHP 8.5 · Livewire 4 · Flux · Tailwind CSS 4 · MySQL 8 · Pest · Laravel Scout (index MySQL) · Mermaid (diagrammes) · spatie/laravel-pdf avec Chrome (rapports) · gitleaks (recherche de secrets) · spatie/laravel-backup (sauvegardes).

## Installer en local

### Prérequis

PHP 8.5 (extensions `mbstring`, `intl`, `pdo_mysql`, `zip`, `gd`, `bcmath`, `sockets`), Composer, MySQL 8, Node.js 22, [gitleaks](https://github.com/gitleaks/gitleaks) 8.30, Google Chrome (rapports PDF).

### 1. Domaines locaux

Ajouter au fichier `hosts` :

```
127.0.0.1 koryhub.test api.koryhub.test docs.koryhub.test admin.koryhub.test
```

### 2. Base de données

Deux utilisateurs : l'un pour les migrations (tous droits), l'autre pour l'application (droits limités).

```sql
CREATE DATABASE koryhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'koryhub_migrate'@'localhost' IDENTIFIED BY '…';
CREATE USER 'koryhub_app'@'localhost' IDENTIFIED BY '…';
GRANT ALL PRIVILEGES ON koryhub.* TO 'koryhub_migrate'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES ON koryhub.* TO 'koryhub_app'@'localhost';
SET PERSIST log_bin_trust_function_creators = 1;
```

Le dernier réglage permet de créer les déclencheurs qui protègent le journal d'audit.

### 3. Application

```
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Compléter `.env` : mots de passe MySQL, `KORYHUB_DEMO_PASSWORD` pour les comptes de démonstration. Puis :

```
php artisan migrate --database=mysql_migrate
php artisan db:seed
php artisan storage:link
composer run dev
```

Ouvrir http://koryhub.test:8000. `composer run dev` lance le serveur, Vite et la file d'attente.

## Tests

Les tests Pest tournent sur une base MySQL dédiée (`koryhub_test`, connexion `mysql_testing`) :

```
php artisan test --filter=NomDuTest
```

## Organisation du code

| Dossier | Contenu |
|---|---|
| `app/Services/` | règles métier : publication, téléchargement, certification, modération, audit |
| `app/Http/Controllers/Api/V1/` | API du CLI |
| `resources/views/pages/` | écrans Livewire (composants à fichier unique) |
| `resources/docs/{fr,en,ar,pt}/` | documentation |
| `lang/{fr,en,ar,pt}/` | traductions de l'interface |
| `routes/` | un fichier par espace : `web.php`, `api.php`, `docs.php`, `admin.php` |
| `tests/Feature/` | tests par domaine |

## Équipe

**Équipe KoryHub, Tchad** — RIYADA ONE TECHNOLOGIES SARL, N'Djamena.

- Skorzeny Kemleyogoto, fondateur et développeur
- Koryam Genevieve, développeuse frontend

Développé avec l'aide de Claude Code (Anthropic). La déclaration d'usage de l'IA est jointe à la soumission CADEV.

## Licence

© 2026 RIYADA ONE TECHNOLOGIES SARL. Tous droits réservés.

Les composants tiers gardent leur licence (MIT, BSD, Apache-2.0 pour l'essentiel ; aucun composant GPL, AGPL ou LGPL n'est intégré).
