/* ============================================================
   AB ENGINS SARL — Espace client (données du client connecté)
   ============================================================ */
"use strict";

const fcfa = (n) => Number(n).toLocaleString("fr-FR", { maximumFractionDigits: 0 }) + " FCFA";
const dateFr = (d) => new Date(String(d).replace(" ", "T")).toLocaleDateString("fr-FR");

const CONTRAT_LABEL = { actif: "Actif", termine: "Terminé", resilie: "Résilié", renouvele: "Renouvelé" };
const CONTRAT_CLASS = { actif: "status-valide", termine: "status-suspendu", resilie: "status-expire", renouvele: "status-valide" };
const PERMIS_CLASS = { valide: "status-valide", expire: "status-expire", suspendu: "status-suspendu" };
const ASSIST_LABEL = { en_attente: "En attente", en_cours: "En cours", resolu: "Résolu" };

async function loadPortail() {
  const errorBox = $("#portailError");
  errorBox.hidden = true;
  try {
    const d = await apiFetch("api/espace-client.php");

    // KPI
    const contratsActifs = d.contrats.filter((c) => c.statut_contrat === "actif").length;
    const permisValides = d.permis.filter((p) => p.statut === "valide").length;
    const kpi = (label, value) => `
      <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">${label}</span></div>
        <div class="kpi-value">${value}</div>
      </article>`;
    $("#portailKpis").innerHTML =
      kpi("Contrats actifs", contratsActifs) +
      kpi("Reste à payer", fcfa(d.total_du)) +
      kpi("Permis valides", permisValides) +
      kpi("Interventions", d.interventions.length);

    // Contrats
    const cb = $("#contratsBody");
    cb.innerHTML = d.contrats.length ? "" : `<tr><td colspan="5">Aucun contrat.</td></tr>`;
    d.contrats.forEach((c) => {
      cb.appendChild(el(`<tr>
        <td><strong>CT-${String(c.date_effet).slice(0, 4)}-${String(c.id_contrat).padStart(3, "0")}</strong></td>
        <td>${c.engins || "—"}</td>
        <td>${dateFr(c.date_effet)} → ${dateFr(c.date_fin_prevue)}</td>
        <td>${fcfa(c.montant_ht)}</td>
        <td><span class="status ${CONTRAT_CLASS[c.statut_contrat] || ""}">${CONTRAT_LABEL[c.statut_contrat] || c.statut_contrat}</span></td>
      </tr>`));
    });

    // Factures
    const fb = $("#facturesBody");
    fb.innerHTML = d.factures.length ? "" : `<tr><td colspan="6">Aucune facture.</td></tr>`;
    d.factures.forEach((f) => {
      fb.appendChild(el(`<tr>
        <td><strong>${f.numero_facture}</strong></td>
        <td>${dateFr(f.date_facture)}</td>
        <td>${fcfa(f.montant_ttc)}</td>
        <td>${fcfa(f.total_paye)}</td>
        <td><strong>${fcfa(f.reste_a_payer)}</strong></td>
        <td><span class="status ${FACTURE_STATUT_CLASS[f.statut_paiement] || ""}">${FACTURE_STATUT_LABEL[f.statut_paiement] || f.statut_paiement}</span></td>
      </tr>`));
    });

    // Permis
    const pl = $("#permisList");
    pl.innerHTML = d.permis.length ? "" : `<li>Aucun permis enregistré.</li>`;
    d.permis.forEach((p) => {
      pl.appendChild(el(`<li class="permit-item">
        <div><strong>${p.numero_permis}</strong><br /><small>${p.region} — expire le ${dateFr(p.date_expiration)}${p.jours_restants >= 0 ? ` (dans ${p.jours_restants} j)` : ""}</small></div>
        <span class="status ${PERMIS_CLASS[p.statut] || ""}">${p.statut}</span>
      </li>`));
    });

    // Interventions
    const il = $("#interventionsList");
    il.innerHTML = d.interventions.length ? "" : `<li>Aucune intervention.</li>`;
    d.interventions.forEach((i) => {
      il.appendChild(el(`<li class="assist-item">
        <div class="assist-item-head">
          <div><strong>${i.engin}</strong><br /><small>${dateFr(i.date_intervention)} — ${i.motif_intervention || "motif non précisé"}</small></div>
          <span class="status status-${i.statut_intervention}">${ASSIST_LABEL[i.statut_intervention] || i.statut_intervention}</span>
        </div>
      </li>`));
    });
  } catch (e) {
    errorBox.textContent = "Erreur : " + e.message;
    errorBox.hidden = false;
  }
}

document.addEventListener("DOMContentLoaded", loadPortail);
