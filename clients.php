<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'clients';
$pageTitle = 'Gestion des clients';
$pageSubtitle = 'Clients enregistrés';
$pageScripts = ['js/clients.js'];

require __DIR__ . '/partials/head.php';
?>
        <section class="card">
          <div class="clients-toolbar">
            <div class="search-field">
              <label for="searchClient">Rechercher un client</label>
              <input type="text" id="searchClient" placeholder="nom, téléphone, secteur..." autocomplete="off" />
            </div>
            <button class="btn btn-primary" id="openNewClient" type="button">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
              Nouveau client
            </button>
          </div>

          <div class="table-wrap">
            <table class="table" id="clientTable">
              <thead>
                <tr>
                  <th>Client</th>
                  <th>Secteur</th>
                  <th>Téléphone</th>
                  <th>Permis</th>
                  <th>Statut permis</th>
                </tr>
              </thead>
              <tbody><!-- injecté --></tbody>
            </table>
            <p class="empty-state" id="emptyState" hidden>Aucun client ne correspond à cette recherche.</p>
          </div>
        </section>

        <!-- Modal nouveau client -->
        <div class="modal-overlay" id="clientModalOverlay" hidden>
          <div class="modal">
            <div class="modal-head">
              <h3>Nouveau client</h3>
              <button class="icon-btn" id="closeNewClient" type="button" aria-label="Fermer">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <form id="clientForm">
              <div class="form-grid">
                <label>Raison sociale
                  <input type="text" name="nom_client" required />
                </label>
                <label>Secteur d'activité
                  <select name="id_secteur" id="secteurSelect" required></select>
                </label>
                <label>Représentant légal
                  <input type="text" name="nom_representant" required />
                </label>
                <label>N° CNI représentant
                  <input type="text" name="cni_representant" required />
                </label>
                <label>Téléphone
                  <input type="text" name="telephone_client" />
                </label>
                <label>Email
                  <input type="email" name="email_client" />
                </label>
                <label class="span-2">Adresse
                  <input type="text" name="adresse_client" />
                </label>
              </div>
              <p class="form-error" id="clientFormError" hidden></p>
              <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="cancelNewClient">Annuler</button>
                <button type="submit" class="btn btn-primary" id="submitNewClient">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
<?php require __DIR__ . '/partials/foot.php'; ?>
