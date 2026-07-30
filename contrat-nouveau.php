<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'contrats';
$pageTitle = 'Nouveau contrat de location';
$pageSubtitle = 'Assistant en 4 étapes';
$pageScripts = ['js/contrat-wizard.js'];

require __DIR__ . '/partials/head.php';
?>
        <section class="card">
          <!-- Stepper -->
          <div class="stepper" id="stepper">
            <div class="step-item active" data-step="1">
              <span class="step-circle">1</span>
              <span class="step-label">Client &amp; permis</span>
            </div>
            <div class="step-line"></div>
            <div class="step-item" data-step="2">
              <span class="step-circle">2</span>
              <span class="step-label">Engins &amp; durée</span>
            </div>
            <div class="step-line"></div>
            <div class="step-item" data-step="3">
              <span class="step-circle">3</span>
              <span class="step-label">Tarification</span>
            </div>
            <div class="step-line"></div>
            <div class="step-item" data-step="4">
              <span class="step-circle">4</span>
              <span class="step-label">Signature</span>
            </div>
          </div>

          <p class="wizard-error" id="wizardError" hidden></p>

          <!-- Etape 1 : Client & permis -->
          <div class="wizard-step" id="step-1">
            <h3 class="step-title">Étape 1 — Choix du client</h3>
            <div class="form-grid">
              <label class="span-2">Client
                <select id="clientSelect"></select>
              </label>
            </div>
            <div class="client-summary" id="clientSummary" hidden>
              <div><span>Représentant</span><strong id="sumRepresentant">—</strong></div>
              <div><span>Secteur</span><strong id="sumSecteur">—</strong></div>
              <div><span>Permis</span><strong id="sumPermis">—</strong></div>
              <div><span>Statut permis</span><strong id="sumStatutPermis">—</strong></div>
            </div>
            <div class="wizard-actions">
              <span></span>
              <button class="btn btn-primary" data-next="2" disabled id="toStep2">Suivant ›</button>
            </div>
          </div>

          <!-- Etape 2 : Engins & durée -->
          <div class="wizard-step" id="step-2" hidden>
            <h3 class="step-title">Étape 2 — Choix des engins et de la durée</h3>
            <div class="form-grid">
              <label>Date d'effet
                <input type="date" id="dateEffet" required />
              </label>
              <label>Durée (jours)
                <input type="number" id="dureeJours" min="1" value="30" required />
              </label>
            </div>
            <p class="hint">Engins disponibles sur la période (les engins déjà loués sont grisés — règle RG1 : pas de double affectation sur une période active).</p>
            <ul class="engine-list" id="engineList"><!-- injecté --></ul>
            <div class="wizard-actions">
              <button class="btn btn-ghost" data-back="1">‹ Retour</button>
              <button class="btn btn-primary" data-next="3" disabled id="toStep3">Suivant ›</button>
            </div>
          </div>

          <!-- Etape 3 : Tarification -->
          <div class="wizard-step" id="step-3" hidden>
            <h3 class="step-title">Étape 3 — Tarification</h3>
            <div class="form-grid">
              <label class="span-2">Montant total HT (FCFA)
                <input type="number" id="montantHt" min="1" step="1000" required />
              </label>
            </div>
            <p class="hint">Montant global de la location pour la durée et les engins sélectionnés.</p>
            <div class="wizard-actions">
              <button class="btn btn-ghost" data-back="2">‹ Retour</button>
              <button class="btn btn-primary" data-next="4" id="toStep4">Suivant ›</button>
            </div>
          </div>

          <!-- Etape 4 : Signature -->
          <div class="wizard-step" id="step-4" hidden>
            <h3 class="step-title">Étape 4 — Signature du contrat</h3>
            <div class="form-grid">
              <label>Date de signature
                <input type="date" id="dateSignature" required />
              </label>
              <label>Lieu de signature
                <input type="text" id="lieuSignature" placeholder="Douala" />
              </label>
              <label class="span-2 checkbox-label">
                <input type="checkbox" id="taciteReconduction" />
                Renouvellement automatique (tacite reconduction)
              </label>
            </div>

            <div class="recap-card" id="recapCard"><!-- injecté --></div>

            <div class="wizard-actions">
              <button class="btn btn-ghost" data-back="3">‹ Retour</button>
              <button class="btn btn-primary" id="submitContrat">Créer le contrat</button>
            </div>
          </div>

          <!-- Confirmation -->
          <div class="wizard-step" id="step-done" hidden>
            <div class="success-box">
              <svg viewBox="0 0 24 24" width="48" height="48"><path d="M20 6L9 17l-5-5"/></svg>
              <h3>Contrat créé avec succès</h3>
              <p id="doneRef"></p>
              <a class="btn btn-primary" href="index.php">Retour au tableau de bord</a>
              <button class="btn btn-ghost" id="newAnother">Créer un autre contrat</button>
            </div>
          </div>
        </section>
<?php require __DIR__ . '/partials/foot.php'; ?>
