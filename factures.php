<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'factures';
$pageTitle = 'Facturation';
$pageSubtitle = 'Factures émises';
$pageScripts = ['js/factures.js'];

require __DIR__ . '/partials/head.php';
?>
        <section class="card">
          <div class="clients-toolbar">
            <div class="search-field">
              <label for="filterStatutFacture">Filtrer par statut</label>
              <select id="filterStatutFacture">
                <option value="">Tous les statuts</option>
                <option value="paye">Payé</option>
                <option value="partiel">Partiel</option>
                <option value="en_retard">En retard</option>
                <option value="impaye">Impayé</option>
              </select>
            </div>
            <button class="btn btn-primary" id="openNewFacture" type="button">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
              Nouvelle facture
            </button>
          </div>

          <div class="table-wrap">
            <table class="table" id="facturesTable">
              <thead>
                <tr>
                  <th>N° Facture</th>
                  <th>Client</th>
                  <th>Date</th>
                  <th>Montant TTC</th>
                  <th>Reste à payer</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody><!-- injecté --></tbody>
            </table>
            <p class="empty-state" id="emptyState" hidden>Aucune facture ne correspond à ce filtre.</p>
          </div>
        </section>

        <!-- Modal nouvelle facture -->
        <div class="modal-overlay" id="factureModalOverlay" hidden>
          <div class="modal">
            <div class="modal-head">
              <h3>Nouvelle facture</h3>
              <button class="icon-btn" id="closeNewFacture" type="button" aria-label="Fermer">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <form id="factureForm">
              <div class="form-grid">
                <label class="span-2">Contrat (sans facture existante)
                  <select id="factureContratSelect" name="id_contrat" required></select>
                </label>
                <label>Date de facture
                  <input type="date" name="date_facture" required />
                </label>
                <label>Taux TVA (%)
                  <input type="number" step="0.01" name="taux_tva" value="19.25" />
                </label>
              </div>

              <div class="lignes-facture" id="lignesFacture"></div>
              <button type="button" class="link" id="addLigne">+ Ajouter une ligne</button>

              <p class="form-error" id="factureFormError" hidden></p>
              <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="cancelNewFacture">Annuler</button>
                <button type="submit" class="btn btn-primary" id="submitNewFacture">Créer la facture</button>
              </div>
            </form>
          </div>
        </div>
<?php require __DIR__ . '/partials/foot.php'; ?>
