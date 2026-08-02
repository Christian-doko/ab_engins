/* ============================================================
   AB ENGINS SARL — Page Contrats (liste)
   ============================================================ */
"use strict";

const CONTRAT_STATUT_LABEL = { actif: "Actif", termine: "Terminé", resilie: "Résilié", renouvele: "Renouvelé" };
let contratsCache = [];

function renderContratsTable(list) {
  const tbody = document.querySelector("#contratsTable tbody");
  const empty = $("#emptyState");
  tbody.innerHTML = "";

  if (!list.length) { empty.hidden = false; return; }
  empty.hidden = true;

  const fmt = new Intl.NumberFormat("fr-FR");
  list.forEach((c) => {
    const debut = c.date_effet.split("-").reverse().join("/");
    const fin = c.date_fin_prevue.split("-").reverse().join("/");
    tbody.appendChild(el(`
      <tr>
        <td class="mono">${c.reference}</td>
        <td>${c.client}</td>
        <td>${c.engins}</td>
        <td>${debut} → ${fin} <small style="color:var(--muted)">(${c.duree_jours} j)</small></td>
        <td>${fmt.format(c.montant_ht)} FCFA</td>
        <td><span class="status status-${c.statut}">${CONTRAT_STATUT_LABEL[c.statut] || c.statut}</span></td>
        <td><a class="link" href="contrat-imprimable.php?id=${c.id}" target="_blank" rel="noopener">Imprimer / PDF</a></td>
      </tr>`));
  });
}

function applyStatutFilter(statut) {
  if (!statut) return renderContratsTable(contratsCache);
  renderContratsTable(contratsCache.filter((c) => c.statut === statut));
}

async function loadContrats() {
  try {
    const data = await apiFetch("api/contrats.php");
    contratsCache = data.contrats;
    renderContratsTable(contratsCache);
  } catch (err) {
    $("#emptyState").hidden = false;
    $("#emptyState").textContent = "Impossible de charger les contrats : " + err.message;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  loadContrats();
  $("#filterStatut").addEventListener("change", (e) => applyStatutFilter(e.target.value));
});
