# Architecture du système AB ENGINS

> Les diagrammes ci-dessous sont rendus automatiquement par GitHub
> (ouvrir ce fichier sur github.com puis faire une capture d'écran pour le mémoire).

## 1. Architecture applicative (3 tiers)

```mermaid
flowchart TB
    subgraph NAV["Navigateur (client)"]
        UI["Pages HTML/CSS<br/>(gabarits partials/)"]
        JS["JavaScript vanilla (js/)<br/>fetch → API JSON"]
    end

    subgraph SRV["Serveur PHP 8"]
        GUARD["Contrôle de session + rôles<br/>(partials/guard.php, requireAuth)"]
        PAGES["Pages métier<br/>clients, contrats, engins,<br/>permis, factures, assistance"]
        API["API JSON (api/*.php)<br/>auth, dashboard, clients, contrats,<br/>engins, permis, factures,<br/>assistance, utilisateurs"]
        subgraph IA["Assistant IA (fait maison)"]
            AGENT["api/agent.php<br/>endpoint de chat"]
            MOTEUR["api/moteur_ia.php<br/>normalisation → TF-IDF →<br/>similarité cosinus → entités"]
            OUTILS["api/outils_donnees.php<br/>7 requêtes SQL lecture seule"]
        end
        PDF["facture-imprimable.php<br/>mise en page A4 → impression/PDF"]
    end

    subgraph BDD["MySQL"]
        TABLES["14 tables métier<br/>(client, contrat, engin, permis,<br/>facture, paiement, assistance...)"]
        TRIGGER["Triggers de règles métier :<br/>• un engin ne peut pas être sur 2 contrats<br/>actifs qui se chevauchent<br/>• pas de contrat sans permis valide<br/>appartenant au client signataire"]
        USERS["Table utilisateur<br/>(mots de passe hashés bcrypt)"]
    end

    UI --> JS
    JS -->|"fetch JSON (session PHP)"| API
    UI -->|"HTTP"| GUARD --> PAGES
    API --> TABLES
    PAGES --> TABLES
    AGENT --> MOTEUR --> OUTILS --> TABLES
    PDF --> TABLES
    TABLES --- TRIGGER
    GUARD --> USERS
```

## 2. Pipeline de l'assistant IA

```mermaid
flowchart LR
    Q["Question<br/>en français"] --> N["Normalisation<br/>+ tokenisation<br/>+ racinisation"]
    N --> V["Vectorisation<br/>TF-IDF"]
    V --> C["Similarité cosinus<br/>avec 8 intentions<br/>(seuil 0,12)"]
    C -->|"score < seuil"| F["Message d'aide<br/>(fallback)"]
    C -->|"intention retenue"| E["Extraction d'entités<br/>durées, statuts, clients<br/>+ désambiguïsation<br/>+ suivi de contexte"]
    E --> S["Requête SQL préparée<br/>(lecture seule, LIMIT 50)"]
    S --> R["Réponse en français<br/>(gabarits, FCFA, dates)"]
```

## 3. Chaîne de déploiement continu

```mermaid
flowchart LR
    DEV["Poste de développement<br/>XAMPP (Windows)<br/>= dépôt git local"]
    GH["GitHub<br/>Christian-doko/ab_engins<br/>(+ protection anti-secrets)"]
    subgraph RW["Railway (production)"]
        BUILD["Build Dockerfile<br/>php:8.3-cli + pdo_mysql"]
        APP["Conteneur applicatif<br/>serveur intégré PHP ($PORT)"]
        DB["Service MySQL managé<br/>(volume persistant)"]
    end
    WEB["Utilisateurs<br/>abengins-production<br/>.up.railway.app"]

    DEV -->|"git push"| GH -->|"déclenchement<br/>automatique"| BUILD --> APP
    APP <-->|"variables DB_*<br/>(réseau privé)"| DB
    WEB -->|"HTTPS"| APP
```

## Points à souligner dans le mémoire

- **Séparation en 3 tiers** : présentation (navigateur), traitement (PHP), données
  (MySQL) — chaque échange client↔serveur passe par le contrôle de session.
- **Règles de gestion en base** : les triggers garantissent l'intégrité même si
  une future interface contournait l'application. Deux règles sont implémentées
  au niveau du SGBD :
  1. un engin ne peut pas être affecté à deux contrats actifs sur des périodes
     qui se chevauchent ;
  2. **un contrat exige un permis d'exploitation** — `contrat.id_permis` est
     `NOT NULL`, et le permis présenté doit appartenir au client signataire,
     couvrir la date de signature et ne pas être suspendu
     (voir `sql/migration_permis_obligatoire.sql`).
- **Assistant IA autonome** : aucun service externe — tout le pipeline s'exécute
  dans le tiers traitement, les données ne quittent jamais le système.
- **Déploiement continu** : un `git push` suffit pour mettre la production à jour ;
  les secrets (identifiants base) sont des variables d'environnement, jamais dans
  le code — la protection anti-secrets de GitHub le vérifie à chaque push.
