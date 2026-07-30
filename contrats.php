<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'contrats';
$pageTitle = 'Contrats de location';
$pageSubtitle = 'Historique et suivi';
$pageScripts = ['js/contrats-list.js'];

require __DIR__ . '/partials/head.php';
?>
        <section class="card">
          <div class="clients-toolbar">
            <div class="search-field">
              <label for="filterStatut">Filtrer par statut</label>
              <select id="filterStatut">
                <option value="">Tous les statuts</option>
                <option value="actif">Actif</option>
                <option value="termine">Terminé</option>
                <option value="resilie">Résilié</option>
                <option value="renouvele">Renouvelé</option>
              </select>
            </div>
            <a class="btn btn-primary" href="contrat-nouveau.php">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
              Nouveau contrat
            </a>
          </div>

          <div class="table-wrap">
            <table class="table" id="contratsTable">
              <thead>
                <tr>
                  <th>N° Contrat</th>
                  <th>Client</th>
                  <th>Engin(s)</th>
                  <th>Période</th>
                  <th>Montant HT</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody><!-- injecté --></tbody>
            </table>
            <p class="empty-state" id="emptyState" hidden>Aucun contrat ne correspond à ce filtre.</p>
          </div>
        </section>
<?php require __DIR__ . '/partials/foot.php'; ?>
