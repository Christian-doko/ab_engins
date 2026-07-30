<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$idFacture = (int) ($_GET['id'] ?? 0);
if ($idFacture <= 0) {
    header('Location: factures.php');
    exit;
}

$activeNav = 'factures';
$pageTitle = 'Détail de la facture';
$pageSubtitle = 'Facturation';
$pageScripts = ['js/facture-detail.js'];

require __DIR__ . '/partials/head.php';
?>
        <script>const FACTURE_ID = <?= json_encode($idFacture) ?>;</script>

        <p class="wizard-error" id="detailError" hidden></p>

        <section class="facture-header" id="factureHeader" hidden>
          <div class="card">
            <div class="card-head">
              <h3 id="factureNumero">—</h3>
              <a class="link" href="factures.php">← Retour</a>
            </div>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr><th>Désignation</th><th>Qté</th><th>P.U. (FCFA)</th><th>Montant</th></tr>
                </thead>
                <tbody id="lignesBody"><!-- injecté --></tbody>
              </table>
            </div>
            <div class="recap-card" id="totauxCard"></div>
          </div>

          <div class="card">
            <div class="card-head"><h3>Statut</h3></div>
            <span class="status" id="statutBadge" style="font-size:13px;padding:8px 16px;"></span>
            <div class="amounts" style="margin-top:16px;">
              <div>Payé : <strong id="montantPaye">—</strong></div>
              <div class="reste">Reste : <strong id="montantReste">—</strong></div>
            </div>
            <button class="btn btn-primary" id="openPaiement" style="margin-top:16px;width:100%;justify-content:center;">Enregistrer paiement</button>

            <h4 style="margin-top:22px;font-size:13.5px;">Historique des paiements</h4>
            <ul class="payment-list" id="paymentList"><!-- injecté --></ul>
          </div>
        </section>

        <div class="modal-overlay" id="paiementModalOverlay" hidden>
          <div class="modal">
            <div class="modal-head">
              <h3>Enregistrer un paiement</h3>
              <button class="icon-btn" id="closePaiement" type="button" aria-label="Fermer">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <form id="paiementForm">
              <div class="form-grid">
                <label>Montant versé (FCFA)
                  <input type="number" name="montant_paye" min="1" required />
                </label>
                <label>Mode de paiement
                  <select name="mode_paiement" required>
                    <option value="virement">Virement bancaire</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="especes">Espèces</option>
                    <option value="cheque">Chèque</option>
                  </select>
                </label>
                <label class="span-2">Date du versement
                  <input type="date" name="date_paiement" required />
                </label>
              </div>
              <p class="form-error" id="paiementFormError" hidden></p>
              <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="cancelPaiement">Annuler</button>
                <button type="submit" class="btn btn-primary" id="submitPaiement">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
<?php require __DIR__ . '/partials/foot.php'; ?>
