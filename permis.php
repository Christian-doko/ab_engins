<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'permis';
$pageTitle = 'Suivi des permis';
$pageSubtitle = 'Autorisations d\'exploitation';
$pageScripts = ['js/permis.js'];

require __DIR__ . '/partials/head.php';
?>
        <section class="card">
          <div class="clients-toolbar">
            <div class="search-field">
              <label for="searchPermis">Rechercher un permis</label>
              <input type="text" id="searchPermis" placeholder="numéro, client, région..." autocomplete="off" />
            </div>
            <button class="btn btn-primary" id="openNewPermis" type="button">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
              Nouveau permis
            </button>
          </div>

          <div class="table-wrap">
            <table class="table" id="permisTable">
              <thead>
                <tr>
                  <th>N° Permis</th>
                  <th>Client</th>
                  <th>Localisation</th>
                  <th>Expiration</th>
                  <th>Statut</th>
                  <th></th>
                </tr>
              </thead>
              <tbody><!-- injecté --></tbody>
            </table>
            <p class="empty-state" id="emptyState" hidden>Aucun permis ne correspond à cette recherche.</p>
          </div>
        </section>

        <div class="modal-overlay" id="permisModalOverlay" hidden>
          <div class="modal">
            <div class="modal-head">
              <h3>Nouveau permis</h3>
              <button class="icon-btn" id="closeNewPermis" type="button" aria-label="Fermer">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <form id="permisForm">
              <div class="form-grid">
                <label class="span-2">Client
                  <select name="id_client" id="permisClientSelect" required></select>
                </label>
                <label>N° du permis
                  <input type="text" name="numero_permis" placeholder="PF-2026-001" required />
                </label>
                <label>Superficie (ha)
                  <input type="number" step="0.01" name="superficie_ha" />
                </label>
                <label>Région
                  <input type="text" name="region" required />
                </label>
                <label>Département
                  <input type="text" name="departement" required />
                </label>
                <label>Arrondissement
                  <input type="text" name="arrondissement" required />
                </label>
                <label>Forêt / parcelle concernée
                  <input type="text" name="foret_concernee" />
                </label>
                <label>Date de délivrance
                  <input type="date" name="date_delivrance" required />
                </label>
                <label>Date d'expiration
                  <input type="date" name="date_expiration" required />
                </label>
              </div>
              <p class="form-error" id="permisFormError" hidden></p>
              <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="cancelNewPermis">Annuler</button>
                <button type="submit" class="btn btn-primary" id="submitNewPermis">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
<?php require __DIR__ . '/partials/foot.php'; ?>
