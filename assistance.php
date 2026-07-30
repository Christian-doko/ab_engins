<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'assist';
$pageTitle = 'Assistance technique';
$pageSubtitle = 'Interventions et dépannages';
$pageScripts = ['js/assistance.js'];

require __DIR__ . '/partials/head.php';
?>
        <section class="card">
          <div class="clients-toolbar">
            <div class="search-field">
              <label for="filterStatutAssist">Filtrer par statut</label>
              <select id="filterStatutAssist">
                <option value="">Tous les statuts</option>
                <option value="en_attente">En attente</option>
                <option value="en_cours">En cours</option>
                <option value="resolu">Résolu</option>
              </select>
            </div>
            <button class="btn btn-primary" id="openNewAssist" type="button">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
              Nouvelle intervention
            </button>
          </div>

          <ul class="assist-list" id="assistList"><!-- injecté --></ul>
          <p class="empty-state" id="emptyState" hidden>Aucune intervention ne correspond à ce filtre.</p>
        </section>

        <div class="modal-overlay" id="assistModalOverlay" hidden>
          <div class="modal">
            <div class="modal-head">
              <h3>Nouvelle intervention</h3>
              <button class="icon-btn" id="closeNewAssist" type="button" aria-label="Fermer">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <form id="assistForm">
              <div class="form-grid">
                <label class="span-2">Engin concerné (client — engin)
                  <select name="id_contrat_engin" id="assistEngineSelect" required></select>
                </label>
                <label class="span-2">Technicien
                  <select name="id_employe" id="assistTechSelect" required></select>
                </label>
                <label class="span-2">Motif
                  <input type="text" name="motif_intervention" placeholder="Panne hydraulique, dépannage..." required />
                </label>
                <label class="span-2">Description du problème
                  <input type="text" name="description_probleme" />
                </label>
              </div>
              <p class="form-error" id="assistFormError" hidden></p>
              <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="cancelNewAssist">Annuler</button>
                <button type="submit" class="btn btn-primary" id="submitNewAssist">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
<?php require __DIR__ . '/partials/foot.php'; ?>
