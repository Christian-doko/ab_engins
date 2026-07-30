# AB ENGINS

Application web de gestion de location d'engins de chantier : clients, contrats, engins, permis, factures et assistance technique.

## Stack

- **Backend** : PHP 8 (API JSON dans `api/`, pages dans la racine)
- **Base de données** : MySQL (scripts dans `sql/`)
- **Frontend** : HTML/CSS/JS vanilla (`js/`, `css/`, gabarits dans `partials/`)

## Lancer en local (XAMPP)

1. Placer le dossier dans `htdocs/`.
2. Créer la base : importer dans phpMyAdmin, dans l'ordre :
   - `sql/script_ab_engins.sql` (schéma)
   - `sql/auth_utilisateur.sql` (comptes)
   - `sql/seed_ab_engins.sql` puis `sql/seed_extra_contracts.sql` (données de démo)
3. Ouvrir `http://localhost/AB%20ENGINS/login.php`.

## Assistant IA

La page **Assistant IA** (`assistant.php`) permet d'interroger les données en langage naturel
(« Quels permis expirent ce mois-ci ? », « Quelles factures sont impayées ? »). Le backend
(`api/agent.php`) appelle l'API Claude (Anthropic) avec des outils **en lecture seule** branchés
sur la base : l'IA ne modifie jamais les données et l'accès exige une session connectée.

Configuration de la clé API ([console Anthropic](https://platform.claude.com)) :

- **En local (XAMPP)** : créer `api/secrets.local.php` (non versionné) contenant :
  ```php
  <?php return 'sk-ant-votre-cle';
  ```
- **Sur Railway** : ajouter la variable `ANTHROPIC_API_KEY` dans les Variables du service PHP.

## Déployer sur Railway

1. Pousser ce dépôt sur GitHub.
2. Sur [railway.app](https://railway.app) : **New Project → Deploy from GitHub repo** (le `Dockerfile` est détecté automatiquement).
3. Ajouter un service **MySQL** dans le même projet Railway.
4. Dans les **Variables** du service PHP, référencer celles du service MySQL :
   - `DB_HOST` = `${{MySQL.MYSQLHOST}}`
   - `DB_PORT` = `${{MySQL.MYSQLPORT}}`
   - `DB_NAME` = `${{MySQL.MYSQLDATABASE}}`
   - `DB_USER` = `${{MySQL.MYSQLUSER}}`
   - `DB_PASS` = `${{MySQL.MYSQLPASSWORD}}`
5. Importer les scripts `sql/` dans la base Railway (via l'onglet Data ou `mysql` en ligne de commande).
6. Générer un domaine public : **Settings → Networking → Generate Domain** (accepter le port
   détecté automatiquement, ou saisir **8080**).

> Le fichier `railway.json` force Railway à construire avec le `Dockerfile`. L'application est
> servie par le serveur web intégré de PHP : l'image `php:8.3-apache` provoquait une erreur
> *More than one MPM loaded* au démarrage du conteneur.

> **Note Vercel** : Vercel n'exécute pas PHP nativement. Railway héberge ici l'application complète (pages + API + MySQL). Vercel ne devient utile que si le frontend est un jour séparé en application statique/React.
"# ab_engins" 
