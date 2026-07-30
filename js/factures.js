/* ============================================================
   AB ENGINS SARL — Page Facturation (liste)
   ============================================================ */
"use strict";

let facturesCache = [];

function renderFacturesTable(list) {
  const tbody = document.querySelector("#facturesTable tbody");
  const empty = $("#emptyState");
  tbody.innerHTML = "";

  if (!list.length) { empty.hidden = false; return; }
  empty.hidden = true;

  const fmt = new Intl.NumberFormat("fr-FR");
  list.forEach((f) => {
    tbody.appendChild(el(`
      <tr class="clickable-row" data-id="${f.id}">
        <td class="mono">${f.numero_facture}</td>
        <td>${f.client}</td>
        <td>${f.date_facture.split("-").reverse().join("/")}</td>
        <td>${fmt.format(f.montant_ttc)} FCFA</td>
        <td>${f.reste > 0 ? fmt.format(f.reste) + " FCFA" : "—"}</td>
        <td><span class="status ${FACTURE_STATUT_CLASS[f.statut_paiement]}">${FACTURE_STATUT_LABEL[f.statut_paiement]}</span></td>
      </tr>`));
  });

  tbody.querySelectorAll("tr[data-id]").forEach((row) => {
    row.style.cursor = "pointer";
    row.addEventListener("click", () => { window.location.href = `facture-detail.php?id=${row.dataset.id}`; });
  });
}

function applyStatutFilterFacture(statut) {
  if (!statut) return renderFacturesTable(facturesCache);
  renderFacturesTable(facturesCache.filter((f) => f.statut_paiement === statut));
}

let contratsDispo = [];

function ligneRow() {
  return el(`
    <div class="ligne-row">
      <input type="text" placeholder="Désignation" class="ligne-designation" required />
      <input type="number" placeholder="Qté" value="1" min="0.01" step="0.01" class="ligne-quantite" required />
      <input type="number" placeholder="Prix unitaire" min="1" class="ligne-prix" required />
      <button type="button" title="Retirer">×</button>
    </div>`);
}

function addLigneRow() {
  const row = ligneRow();
  row.querySelector("button").addEventListener("click", () => row.remove());
  $("#lignesFacture").appendChild(row);
}

async function loadFactures() {
  try {
    const data = await apiFetch("api/factures.php");
    facturesCache = data.factures;
    contratsDispo = data.contrats_disponibles;
    renderFacturesTable(facturesCache);
    $("#factureContratSelect").innerHTML = contratsDispo.length
      ? contratsDispo.map((c) => `<option value="${c.id_contrat}">${c.client} — contrat du ${c.date_effet.split("-").reverse().join("/")}</option>`).join("")
      : '<option value="">Aucun contrat sans facture</option>';
  } catch (err) {
    $("#emptyState").hidden = false;
    $("#emptyState").textContent = "Impossible de charger les factures : " + err.message;
  }
}

function openFactureModal() {
  $("#factureModalOverlay").hidden = false;
  $("#lignesFacture").innerHTML = "";
  addLigneRow();
  const dateInput = document.querySelector('#factureForm input[name="date_facture"]');
  if (!dateInput.value) dateInput.value = new Date().toISOString().slice(0, 10);
}
function closeFactureModal() {
  $("#factureModalOverlay").hidden = true;
  $("#factureForm").reset();
  $("#factureFormError").hidden = true;
}

document.addEventListener("DOMContentLoaded", () => {
  loadFactures();

  $("#filterStatutFacture").addEventListener("change", (e) => applyStatutFilterFacture(e.target.value));
  $("#openNewFacture").addEventListener("click", openFactureModal);
  $("#closeNewFacture").addEventListener("click", closeFactureModal);
  $("#cancelNewFacture").addEventListener("click", closeFactureModal);
  $("#factureModalOverlay").addEventListener("click", (e) => { if (e.target.id === "factureModalOverlay") closeFactureModal(); });
  $("#addLigne").addEventListener("click", addLigneRow);

  $("#factureForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const errorBox = $("#factureFormError");
    const submitBtn = $("#submitNewFacture");
    errorBox.hidden = true;

    const idContrat = Number($("#factureContratSelect").value);
    const dateFacture = document.querySelector('#factureForm input[name="date_facture"]').value;
    const tauxTva = document.querySelector('#factureForm input[name="taux_tva"]').value;

    const lignes = Array.from(document.querySelectorAll(".ligne-row")).map((row) => ({
      designation_ligne: row.querySelector(".ligne-designation").value.trim(),
      quantite: Number(row.querySelector(".ligne-quantite").value),
      prix_unitaire: Number(row.querySelector(".ligne-prix").value),
    })).filter((l) => l.designation_ligne && l.quantite > 0 && l.prix_unitaire > 0);

    if (!idContrat || !lignes.length) {
      errorBox.textContent = "Sélectionnez un contrat et renseignez au moins une ligne valide.";
      errorBox.hidden = false;
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Création…";
    try {
      await apiFetch("api/factures.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id_contrat: idContrat, date_facture: dateFacture, taux_tva: tauxTva, lignes }),
      });
      closeFactureModal();
      await loadFactures();
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Créer la facture";
    }
  });
});
