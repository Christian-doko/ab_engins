<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'dashboard';
$pageTitle = 'Tableau de bord';
$pageSubtitle = "Vue d'ensemble";
$showToday = true;
$pageScripts = ['js/app.js'];

require __DIR__ . '/partials/head.php';

$prenom = trim(explode(' ', $currentUser['nom_complet'])[0] ?? $currentUser['identifiant']);
?>
        <!-- Bienvenue -->
        <section class="welcome">
          <div>
            <h2>Bonjour <?= htmlspecialchars($prenom) ?> 👋</h2>
            <p>Voici l'activité de votre parc d'engins aujourd'hui.</p>
          </div>
          <a class="btn btn-primary" href="contrat-nouveau.php">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Nouveau contrat
          </a>
        </section>

        <!-- KPI -->
        <section class="kpi-grid" id="kpiGrid"><!-- injecté --></section>

        <!-- Middle grid -->
        <section class="grid-2">
          <!-- Chart -->
          <article class="card">
            <div class="card-head">
              <h3>Locations par mois</h3>
              <span class="chip">Nombre de contrats</span>
            </div>
            <div class="chart" id="chart"><!-- injecté --></div>
          </article>

          <!-- Permits -->
          <article class="card">
            <div class="card-head">
              <h3>Permis à renouveler</h3>
              <span class="chip chip-warn" id="permitChip">—</span>
            </div>
            <ul class="permit-list" id="permitList"><!-- injecté --></ul>
          </article>
        </section>

        <!-- Recent contracts -->
        <section class="card">
          <div class="card-head">
            <h3>Derniers contrats</h3>
            <a class="link" href="contrat-nouveau.php">+ Nouveau contrat →</a>
          </div>
          <div class="table-wrap">
            <table class="table" id="contractTable">
              <thead>
                <tr>
                  <th>N° Contrat</th>
                  <th>Client</th>
                  <th>Engin</th>
                  <th>Date d'effet</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody><!-- injecté --></tbody>
            </table>
          </div>
        </section>
<?php require __DIR__ . '/partials/foot.php'; ?>
