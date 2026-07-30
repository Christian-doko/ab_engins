<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'engins';
$pageTitle = 'Suivi du parc d\'engins';
$pageSubtitle = 'Engins enregistrés';
$pageScripts = ['js/engins.js'];

require __DIR__ . '/partials/head.php';
?>
        <section class="card">
          <div class="clients-toolbar">
            <div class="search-field">
              <label for="filterType">Filtrer</label>
              <select id="filterType">
                <option value="">Tous les types</option>
              </select>
            </div>
            <button class="btn btn-primary" id="openNewEngin" type="button">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
              Ajouter engin
            </button>
          </div>

          <div class="engine-grid" id="engineGrid"><!-- injecté --></div>
          <p class="empty-state" id="emptyState" hidden>Aucun engin dans cette catégorie.</p>
        </section>

        <div class="modal-overlay" id="enginModalOverlay" hidden>
          <div class="modal">
            <div class="modal-head">
              <h3>Ajouter un engin</h3>
              <button class="icon-btn" id="closeNewEngin" type="button" aria-label="Fermer">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <form id="enginForm">
              <div class="form-grid">
                <label>Code interne
                  <input type="text" name="code_engin" placeholder="ENG-031" required />
                </label>
                <label>Type d'engin
                  <input type="text" name="type_engin" placeholder="Bulldozer" required />
                </label>
                <label>Modèle
                  <input type="text" name="modele_engin" placeholder="D7G" />
                </label>
                <label>N° de série
                  <input type="text" name="numero_serie" />
                </label>
                <label class="span-2">État
                  <select name="etat_engin">
                    <option value="bon">Bon</option>
                    <option value="moyen">Moyen</option>
                    <option value="defectueux">Défectueux</option>
                  </select>
                </label>
              </div>
              <p class="form-error" id="enginFormError" hidden></p>
              <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="cancelNewEngin">Annuler</button>
                <button type="submit" class="btn btn-primary" id="submitNewEngin">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
<?php require __DIR__ . '/partials/foot.php'; ?>
