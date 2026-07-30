/* ============================================================
   AB ENGINS SARL — Détail facture
   ============================================================ */
"use strict";

const MODE_LABEL = { especes: "Espèces", virement: "Virement bancaire", mobile_money: "Mobile Money", cheque: "Chèque" };

function fmtFcfa(n) { return new Intl.NumberFormat("fr-FR").format(n); }

async function loadFacture() {
  try {
    const data = await apiFetch(`api/factures.php?id=${FACTURE_ID}`);
    render(data);
  } catch (err) {
    $("#detailError").textContent = "Impossible de charger la facture : " + err.message;
    $("#detailError").hidden = false;
  }
}

function render(data) {
  const f = data.facture;
  $("#factureHeader").hidden = false;
  $("#factureNumero").textContent = `Facture ${f.numero_facture}`;

  const tbody = $("#lignesBody");
  tbody.innerHTML = "";
  data.lignes.forEach((l) => {
    tbody.appendChild(el(`
      <tr>
        <td>${l.designation_ligne}</td>
        <td>${Number(l.quantite)}</td>
        <td>${fmtFcfa(l.prix_unitaire)}</td>
        <td>${fmtFcfa(l.montant_ligne)}</td>
      </tr>`));
  });

  $("#totauxCard").innerHTML = `
    <div class="recap-row"><span>Total HT</span><strong>${fmtFcfa(f.montant_ht)} FCFA</strong></div>
    <div class="recap-row"><span>TVA ${f.taux_tva}%</span><strong>${fmtFcfa(f.montant_ttc - f.montant_ht)} FCFA</strong></div>
    <div class="recap-row"><span>TOTAL TTC</span><strong>${fmtFcfa(f.montant_ttc)} FCFA</strong></div>
  `;

  const statutLabel = FACTURE_STATUT_LABEL[f.statut_paiement] || f.statut_paiement;
  const statutClass = FACTURE_STATUT_CLASS[f.statut_paiement] || "";
  $("#statutBadge").textContent = statutLabel;
  $("#statutBadge").className = `status ${statutClass}`;
  $("#montantPaye").textContent = fmtFcfa(f.total_paye) + " FCFA";
  $("#montantReste").textContent = fmtFcfa(f.reste) + " FCFA";

  const list = $("#paymentList");
  list.innerHTML = "";
  if (!data.paiements.length) {
    list.appendChild(el(`<li class="payment-item">Aucun paiement enregistré</li>`));
  } else {
    data.paiements.forEach((p) => {
      list.appendChild(el(`
        <li class="payment-item">
          <span class="pdot"></span>
          ${p.date_paiement.split("-").reverse().join("/")} — ${fmtFcfa(p.montant_paye)} FCFA — ${MODE_LABEL[p.mode_paiement] || p.mode_paiement}
        </li>`));
    });
  }

  $("#openPaiement").disabled = f.reste <= 0;
  if (f.reste <= 0) $("#openPaiement").textContent = "Facture soldée";
}

function openPaiementModal() {
  $("#paiementModalOverlay").hidden = false;
  const dateInput = document.querySelector('#paiementForm input[name="date_paiement"]');
  if (!dateInput.value) dateInput.value = new Date().toISOString().slice(0, 10);
}
function closePaiementModal() {
  $("#paiementModalOverlay").hidden = true;
  $("#paiementForm").reset();
  $("#paiementFormError").hidden = true;
}

document.addEventListener("DOMContentLoaded", () => {
  loadFacture();

  $("#openPaiement").addEventListener("click", openPaiementModal);
  $("#closePaiement").addEventListener("click", closePaiementModal);
  $("#cancelPaiement").addEventListener("click", closePaiementModal);
  $("#paiementModalOverlay").addEventListener("click", (e) => { if (e.target.id === "paiementModalOverlay") closePaiementModal(); });

  $("#paiementForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const errorBox = $("#paiementFormError");
    const submitBtn = $("#submitPaiement");
    errorBox.hidden = true;

    const payload = Object.fromEntries(new FormData(e.target).entries());
    payload.action = "paiement";
    payload.id_facture = FACTURE_ID;

    submitBtn.disabled = true;
    submitBtn.textContent = "Enregistrement…";
    try {
      await apiFetch("api/factures.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      closePaiementModal();
      await loadFacture();
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Enregistrer";
    }
  });
});
