<?php
declare(strict_types=1);
require_once __DIR__ . '/api/config.php';

// Accès réservé aux comptes clients connectés ; le personnel a son interface interne.
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['user']['role'] ?? '') !== 'client') {
    header('Location: index.php');
    exit;
}
$currentUser = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AB ENGINS — Espace client</title>
  <link rel="icon" href="assets/logo.svg" type="image/svg+xml" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
  <div class="portail-client">
    <header class="portail-topbar">
      <div class="brand">
        <img class="brand-logo" src="assets/logo.svg" alt="AB ENGINS" />
        <span class="brand-text">
          <strong>AB ENGINS</strong>
          <small>Espace client</small>
        </span>
      </div>
      <div class="topbar-actions">
        <div class="user">
          <div class="user-meta">
            <strong><?= htmlspecialchars($currentUser['nom_complet']) ?></strong>
            <small>Client</small>
          </div>
        </div>
        <button class="icon-btn" title="Se déconnecter" id="logoutBtn">
          <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
        </button>
      </div>
    </header>

    <nav class="portail-tabs" id="portailTabs">
      <button class="tab active" data-tab="accueil" type="button">Tableau de bord</button>
      <button class="tab" data-tab="contrats" type="button">Mes contrats</button>
      <button class="tab" data-tab="factures" type="button">Mes factures</button>
      <button class="tab" data-tab="engins" type="button">Parc d'engins</button>
      <button class="tab" data-tab="profil" type="button">Mon profil</button>
    </nav>

    <main class="portail-content">
      <p class="form-error" id="portailError" hidden></p>

      <!-- ===================== Tableau de bord ===================== -->
      <section class="tab-panel" data-panel="accueil">
        <div class="welcome">
          <div>
            <h2>Bonjour <?= htmlspecialchars($currentUser['nom_complet']) ?> 👋</h2>
            <p>Retrouvez ici vos contrats, factures, permis et interventions.</p>
          </div>
        </div>

        <section class="kpi-grid" id="portailKpis"></section>

        <div class="grid-2">
          <article class="card">
            <div class="card-head"><h3>Mes permis d'exploitation</h3></div>
            <ul class="permit-list" id="permisList"></ul>
          </article>
          <article class="card">
            <div class="card-head"><h3>Interventions récentes</h3></div>
            <ul class="assist-list" id="interventionsList"></ul>
          </article>
        </div>
      </section>

      <!-- ===================== Contrats ===================== -->
      <section class="tab-panel" data-panel="contrats" hidden>
        <section class="card">
          <div class="card-head">
            <h3>Mes contrats de location</h3>
            <span class="chip" id="contratsCount">—</span>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th>N° Contrat</th><th>Engins</th><th>Période</th><th>Montant HT</th><th>Statut</th><th>Document</th></tr>
              </thead>
              <tbody id="contratsBody"></tbody>
            </table>
          </div>
        </section>
      </section>

      <!-- ===================== Factures ===================== -->
      <section class="tab-panel" data-panel="factures" hidden>
        <section class="card">
          <div class="card-head">
            <h3>Mes factures</h3>
            <span class="chip chip-warn" id="factureDu">—</span>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th>N° Facture</th><th>Date</th><th>Montant TTC</th><th>Payé</th><th>Reste à payer</th><th>Statut</th><th>Document</th></tr>
              </thead>
              <tbody id="facturesBody"></tbody>
            </table>
          </div>
        </section>
      </section>

      <!-- ===================== Parc d'engins ===================== -->
      <section class="tab-panel" data-panel="engins" hidden>
        <section class="card">
          <div class="clients-toolbar">
            <div class="search-field">
              <label for="filtreEngin">Filtrer</label>
              <select id="filtreEngin">
                <option value="">Tout le parc</option>
                <option value="chez_moi">Engins en location chez moi</option>
                <option value="disponible">Disponibles</option>
                <option value="loue">En location</option>
                <option value="maintenance">En maintenance</option>
              </select>
            </div>
            <span class="chip" id="enginsCount">—</span>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th>Code</th><th>Type</th><th>Modèle</th><th>État</th><th>Disponibilité</th></tr>
              </thead>
              <tbody id="enginsBody"></tbody>
            </table>
          </div>
          <p class="empty-state" id="enginsEmpty" hidden>Aucun engin ne correspond à ce filtre.</p>
        </section>
      </section>

      <!-- ===================== Profil ===================== -->
      <section class="tab-panel" data-panel="profil" hidden>
        <div class="grid-2">
          <article class="card">
            <div class="card-head"><h3>Informations de mon entreprise</h3></div>
            <dl class="profil-list" id="profilClient"></dl>
          </article>

          <article class="card">
            <div class="card-head"><h3>Mon compte</h3></div>
            <dl class="profil-list" id="profilCompte"></dl>

            <h4 style="margin-top:22px;font-size:13.5px;">Changer mon mot de passe</h4>
            <form id="pwdForm" style="margin-top:10px;">
              <div class="form-grid">
                <label class="span-2">Mot de passe actuel
                  <input type="password" name="ancien" required autocomplete="current-password" />
                </label>
                <label class="span-2">Nouveau mot de passe (8 caractères min.)
                  <input type="password" name="nouveau" required minlength="8" autocomplete="new-password" />
                </label>
              </div>
              <p class="form-error" id="pwdError" hidden></p>
              <p class="form-success" id="pwdSuccess" hidden>Mot de passe modifié.</p>
              <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
              </div>
            </form>
          </article>
        </div>
      </section>
    </main>
  </div>

  <script src="js/shell.js"></script>
  <script src="js/espace-client.js"></script>
</body>
</html>
