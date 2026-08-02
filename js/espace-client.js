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
const ETAT_LABEL = { bon: "Bon", moyen: "Moyen", defectueux: "Défectueux" };
const DISPO_LABEL = { disponible: "Disponible", loue: "En location", maintenance: "Maintenance" };
const DISPO_CLASS = { disponible: "status-valide", loue: "status-suspendu", maintenance: "status-expire" };

let parcEngins = [];

/* ---------------- Onglets ---------------- */
function activerOnglet(nom) {
  document.querySelectorAll("#portailTabs .tab").forEach((b) => {
    b.classList.toggle("active", b.dataset.tab === nom);
  });
  document.querySelectorAll(".tab-panel").forEach((p) => {
    p.hidden = p.dataset.panel !== nom;
  });
}

/* ---------------- Rendu ---------------- */
function renderKpis(d) {
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
}

function renderContrats(contrats) {
  const tb = $("#contratsBody");
  $("#contratsCount").textContent = `${contrats.length} contrat(s)`;
  tb.innerHTML = contrats.length ? "" : `<tr><td colspan="6">Aucun contrat.</td></tr>`;
  contrats.forEach((c) => {
    const ref = `CT-${String(c.date_effet).slice(0, 4)}-${String(c.id_contrat).padStart(3, "0")}`;
    tb.appendChild(el(`<tr>
      <td><strong>${ref}</strong></td>
      <td>${c.engins || "—"}</td>
      <td>${dateFr(c.date_effet)} → ${dateFr(c.date_fin_prevue)}</td>
      <td>${fcfa(c.montant_ht)}</td>
      <td><span class="status ${CONTRAT_CLASS[c.statut_contrat] || ""}">${CONTRAT_LABEL[c.statut_contrat] || c.statut_contrat}</span></td>
      <td><a class="link" href="contrat-imprimable.php?id=${c.id_contrat}" target="_blank" rel="noopener">Imprimer / PDF</a></td>
    </tr>`));
  });
}

function renderFactures(factures, totalDu) {
  const tb = $("#facturesBody");
  $("#factureDu").textContent = `Reste à payer : ${fcfa(totalDu)}`;
  tb.innerHTML = factures.length ? "" : `<tr><td colspan="7">Aucune facture.</td></tr>`;
  factures.forEach((f) => {
    tb.appendChild(el(`<tr>
      <td><strong>${f.numero_facture}</strong></td>
      <td>${dateFr(f.date_facture)}</td>
      <td>${fcfa(f.montant_ttc)}</td>
      <td>${fcfa(f.total_paye)}</td>
      <td><strong>${fcfa(f.reste_a_payer)}</strong></td>
      <td><span class="status ${FACTURE_STATUT_CLASS[f.statut_paiement] || ""}">${FACTURE_STATUT_LABEL[f.statut_paiement] || f.statut_paiement}</span></td>
      <td><a class="link" href="facture-imprimable.php?id=${f.id_facture}" target="_blank" rel="noopener">Imprimer / PDF</a></td>
    </tr>`));
  });
}

function renderPermis(permis) {
  const pl = $("#permisList");
  pl.innerHTML = permis.length ? "" : `<li>Aucun permis enregistré.</li>`;
  permis.forEach((p) => {
    pl.appendChild(el(`<li class="permit-item">
      <div><strong>${p.numero_permis}</strong><br /><small>${p.region} — expire le ${dateFr(p.date_expiration)}${p.jours_restants >= 0 ? ` (dans ${p.jours_restants} j)` : ""}</small></div>
      <span class="status ${PERMIS_CLASS[p.statut] || ""}">${p.statut}</span>
    </li>`));
  });
}

function renderInterventions(interventions) {
  const il = $("#interventionsList");
  il.innerHTML = interventions.length ? "" : `<li>Aucune intervention.</li>`;
  interventions.forEach((i) => {
    il.appendChild(el(`<li class="assist-item">
      <div class="assist-item-head">
        <div><strong>${i.engin}</strong><br /><small>${dateFr(i.date_intervention)} — ${i.motif_intervention || "motif non précisé"}</small></div>
        <span class="status status-${i.statut_intervention}">${ASSIST_LABEL[i.statut_intervention] || i.statut_intervention}</span>
      </div>
    </li>`));
  });
}

function renderEngins() {
  const filtre = $("#filtreEngin").value;
  const liste = parcEngins.filter((e) => {
    if (!filtre) return true;
    if (filtre === "chez_moi") return Number(e.chez_moi) === 1;
    return e.disponibilite === filtre;
  });

  const tb = $("#enginsBody");
  tb.innerHTML = "";
  $("#enginsCount").textContent = `${liste.length} engin(s)`;
  $("#enginsEmpty").hidden = liste.length > 0;

  liste.forEach((e) => {
    const chezMoi = Number(e.chez_moi) === 1 ? ` <span class="chip">chez moi</span>` : "";
    tb.appendChild(el(`<tr>
      <td><strong>${e.code_engin}</strong>${chezMoi}</td>
      <td>${e.type_engin}</td>
      <td>${e.modele_engin || "—"}</td>
      <td>${ETAT_LABEL[e.etat_engin] || e.etat_engin}</td>
      <td><span class="status ${DISPO_CLASS[e.disponibilite] || ""}">${DISPO_LABEL[e.disponibilite] || e.disponibilite}</span></td>
    </tr>`));
  });
}

function renderProfilClient(c) {
  const ligne = (label, valeur) => `<div><dt>${label}</dt><dd>${valeur || "—"}</dd></div>`;
  $("#profilClient").innerHTML =
    ligne("Raison sociale", c.nom_client) +
    ligne("Secteur d'activité", c.libelle_secteur) +
    ligne("Représentant", c.nom_representant) +
    ligne("Adresse", c.adresse_client) +
    ligne("Téléphone", c.telephone_client) +
    ligne("Adresse e-mail", c.email_client);
}

async function loadProfilCompte() {
  const ligne = (label, valeur) => `<div><dt>${label}</dt><dd>${valeur || "—"}</dd></div>`;
  try {
    const { profil } = await apiFetch("api/profil.php");
    $("#profilCompte").innerHTML =
      ligne("Identifiant", profil.identifiant) +
      ligne("Type de compte", "Client") +
      ligne("Dernière connexion", profil.derniere_connexion ? dateFr(profil.derniere_connexion) : "Première visite") +
      (profil.stats
        ? ligne("Contrats", profil.stats.contrats) + ligne("Factures", profil.stats.factures) + ligne("Permis", profil.stats.permis)
        : "");
    if (profil.client) renderProfilClient(profil.client);
  } catch (e) {
    $("#profilCompte").innerHTML = `<div><dt>Erreur</dt><dd>${e.message}</dd></div>`;
  }
}

/* ---------------- Chargement ---------------- */
async function loadPortail() {
  const errorBox = $("#portailError");
  errorBox.hidden = true;
  try {
    const d = await apiFetch("api/espace-client.php");
    parcEngins = d.engins || [];

    renderKpis(d);
    renderContrats(d.contrats);
    renderFactures(d.factures, d.total_du);
    renderPermis(d.permis);
    renderInterventions(d.interventions);
    renderEngins();
    renderProfilClient(d.client);
  } catch (e) {
    errorBox.textContent = "Erreur : " + e.message;
    errorBox.hidden = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  loadPortail();
  loadProfilCompte();

  document.querySelectorAll("#portailTabs .tab").forEach((b) => {
    b.addEventListener("click", () => activerOnglet(b.dataset.tab));
  });

  $("#filtreEngin").addEventListener("change", renderEngins);

  $("#pwdForm").addEventListener("submit", async (ev) => {
    ev.preventDefault();
    const err = $("#pwdError");
    const ok = $("#pwdSuccess");
    err.hidden = true;
    ok.hidden = true;
    try {
      await apiFetch("api/profil.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "changer_mot_de_passe",
          ancien_mot_de_passe: ev.target.ancien.value,
          nouveau_mot_de_passe: ev.target.nouveau.value,
        }),
      });
      ev.target.reset();
      ok.hidden = false;
    } catch (e) {
      err.textContent = e.message;
      err.hidden = false;
    }
  });
});
