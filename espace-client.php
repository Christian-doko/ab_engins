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
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
  <div class="portail-client">
    <header class="portail-topbar">
      <div class="brand">
        <span class="brand-badge">AB</span>
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

    <main class="portail-content">
      <section class="welcome">
        <div>
          <h2>Bonjour <?= htmlspecialchars($currentUser['nom_complet']) ?> 👋</h2>
          <p>Retrouvez ici vos contrats, factures, permis et interventions.</p>
        </div>
      </section>

      <p class="form-error" id="portailError" hidden></p>

      <section class="kpi-grid" id="portailKpis"><!-- injecté --></section>

      <section class="card">
        <div class="card-head"><h3>Mes contrats</h3></div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>N° Contrat</th><th>Engins</th><th>Période</th><th>Montant HT</th><th>Statut</th></tr></thead>
            <tbody id="contratsBody"></tbody>
          </table>
        </div>
      </section>

      <section class="card">
        <div class="card-head"><h3>Mes factures</h3></div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>N° Facture</th><th>Date</th><th>Montant TTC</th><th>Payé</th><th>Reste à payer</th><th>Statut</th></tr></thead>
            <tbody id="facturesBody"></tbody>
          </table>
        </div>
      </section>

      <section class="grid-2">
        <article class="card">
          <div class="card-head"><h3>Mes permis d'exploitation</h3></div>
          <ul class="permit-list" id="permisList"></ul>
        </article>
        <article class="card">
          <div class="card-head"><h3>Interventions récentes</h3></div>
          <ul class="assist-list" id="interventionsList"></ul>
        </article>
      </section>
    </main>
  </div>

  <script src="js/shell.js"></script>
  <script src="js/espace-client.js"></script>
</body>
</html>
