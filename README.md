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
6. Générer un domaine public : **Settings → Networking → Generate Domain**.

> **Note Vercel** : Vercel n'exécute pas PHP nativement. Railway héberge ici l'application complète (pages + API + MySQL). Vercel ne devient utile que si le frontend est un jour séparé en application statique/React.
"# ab_engins" 
