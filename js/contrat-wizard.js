/* ============================================================
   AB ENGINS SARL — Assistant Nouveau contrat (4 étapes)
   ============================================================ */
"use strict";

const STATUT_LABEL = { valide: "valide", expire: "expiré", suspendu: "suspendu" };

const state = {
  clients: [],
  client: null,
  dateEffet: "",
  dureeJours: 30,
  engines: [],
  selectedEngines: new Set(),
  montantHt: null,
};

function showError(msg) {
  const box = $("#wizardError");
  box.textContent = msg;
  box.hidden = false;
}
function clearError() { $("#wizardError").hidden = true; }

function goToStep(n) {
  document.querySelectorAll(".wizard-step").forEach((s) => { s.hidden = true; });
  const target = n === "done" ? $("#step-done") : $(`#step-${n}`);
  target.hidden = false;
  clearError();

  document.querySelectorAll(".step-item").forEach((item) => {
    const step = Number(item.dataset.step);
    item.classList.toggle("active", step === n);
    item.classList.toggle("done", step < n);
  });
}

/* ---------------- Etape 1 : client ---------------- */
async function loadClients() {
  const select = $("#clientSelect");
  try {
    const data = await apiFetch("api/clients.php");
    state.clients = data.clients;
    select.innerHTML = '<option value="">— Sélectionner un client —</option>' +
      state.clients.map((c) => `<option value="${c.id}">${c.nom}</option>`).join("");
  } catch (err) {
    showError("Impossible de charger les clients : " + err.message);
  }
}

function onClientChange() {
  const id = Number($("#clientSelect").value);
  const summary = $("#clientSummary");
  const client = state.clients.find((c) => c.id === id) || null;
  state.client = client;

  if (!client) {
    summary.hidden = true;
    $("#toStep2").disabled = true;
    return;
  }

  $("#sumRepresentant").textContent = client.representant || "—";
  $("#sumSecteur").textContent = client.secteur || "—";
  $("#sumPermis").textContent = client.permis || "Aucun permis enregistré";
  const statut = client.statut_permis;
  $("#sumStatutPermis").innerHTML = statut
    ? `<span class="status status-${statut}">${STATUT_LABEL[statut] || statut}</span>`
    : "—";
  summary.hidden = false;
  $("#toStep2").disabled = statut === 'expire' || statut === 'suspendu';
  if ($("#toStep2").disabled) {
    showError("Ce client n'a pas de permis valide : impossible de créer un contrat.");
  } else {
    clearError();
  }
}

/* ---------------- Etape 2 : engins & durée ---------------- */
async function loadEngines() {
  const dateEffet = $("#dateEffet").value;
  const duree = Number($("#dureeJours").value);
  state.dateEffet = dateEffet;
  state.dureeJours = duree;

  const list = $("#engineList");
  if (!dateEffet || !duree || duree <= 0) {
    list.innerHTML = '<li class="hint">Renseignez une date d\'effet et une durée pour voir les engins disponibles.</li>';
    return;
  }

  list.innerHTML = '<li class="hint">Chargement…</li>';
  try {
    const data = await apiFetch(`api/engins.php?date_effet=${encodeURIComponent(dateEffet)}&duree_jours=${duree}`);
    state.engines = data.engins;
    state.selectedEngines.clear();
    renderEngines();
    updateStep2NextState();
  } catch (err) {
    list.innerHTML = "";
    showError("Impossible de charger les engins : " + err.message);
  }
}

function renderEngines() {
  const list = $("#engineList");
  list.innerHTML = "";
  state.engines.forEach((e) => {
    const selected = state.selectedEngines.has(e.id);
    const li = el(`
      <li class="engine-item ${selected ? "selected" : ""} ${e.disponible_periode ? "" : "unavailable"}">
        <input type="checkbox" ${selected ? "checked" : ""} ${e.disponible_periode ? "" : "disabled"} data-id="${e.id}" />
        <span class="engine-name">${e.type} ${e.modele || ""} <span class="engine-code">— ${e.code}</span></span>
        <span class="status ${e.disponible_periode ? "status-valide" : "status-suspendu"}">${e.disponible_periode ? "disponible" : "indisponible"}</span>
      </li>`);
    if (e.disponible_periode) {
      li.addEventListener("click", (evt) => {
        if (evt.target.tagName !== "INPUT") {
          const box = li.querySelector("input");
          box.checked = !box.checked;
        }
        toggleEngine(e.id, li.querySelector("input").checked);
        li.classList.toggle("selected", state.selectedEngines.has(e.id));
      });
    }
    list.appendChild(li);
  });
}

function toggleEngine(id, checked) {
  if (checked) state.selectedEngines.add(id);
  else state.selectedEngines.delete(id);
  updateStep2NextState();
}

function updateStep2NextState() {
  $("#toStep3").disabled = state.selectedEngines.size === 0;
}

/* ---------------- Etape 3 : tarification ---------------- */
function onEnterStep3() {
  if ($("#montantHt").value === "") {
    const suggestion = state.selectedEngines.size * state.dureeJours * 80000;
    $("#montantHt").value = suggestion;
  }
}

/* ---------------- Etape 4 : signature ---------------- */
function onEnterStep4() {
  state.montantHt = Number($("#montantHt").value);
  if (!$("#dateSignature").value) {
    $("#dateSignature").value = new Date().toISOString().slice(0, 10);
  }
  renderRecap();
}

function renderRecap() {
  const engineNames = state.engines
    .filter((e) => state.selectedEngines.has(e.id))
    .map((e) => `${e.type} ${e.modele || ""}`.trim())
    .join(", ");
  const fmt = new Intl.NumberFormat("fr-FR").format(state.montantHt);

  $("#recapCard").innerHTML = `
    <div class="recap-row"><span>Client</span><strong>${state.client.nom}</strong></div>
    <div class="recap-row"><span>Permis</span><strong>${state.client.permis || "—"}</strong></div>
    <div class="recap-row"><span>Engins</span><strong>${engineNames}</strong></div>
    <div class="recap-row"><span>Période</span><strong>${state.dureeJours} j à partir du ${state.dateEffet}</strong></div>
    <div class="recap-row"><span>Montant HT</span><strong>${fmt} FCFA</strong></div>
  `;
}

async function submitContrat() {
  clearError();
  const btn = $("#submitContrat");
  btn.disabled = true;
  btn.textContent = "Création…";

  const payload = {
    id_client: state.client.id,
    date_effet: state.dateEffet,
    duree_jours: state.dureeJours,
    montant_ht: state.montantHt,
    engins: Array.from(state.selectedEngines),
    date_signature: $("#dateSignature").value,
    lieu_signature: $("#lieuSignature").value,
    tacite_reconduction: $("#taciteReconduction").checked,
  };

  try {
    const res = await apiFetch("api/contrats.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    $("#doneRef").textContent = `Référence interne : contrat n°${res.id_contrat}`;
    goToStep("done");
  } catch (err) {
    showError(err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = "Créer le contrat";
  }
}

function resetWizard() {
  state.client = null;
  state.dateEffet = "";
  state.dureeJours = 30;
  state.engines = [];
  state.selectedEngines = new Set();
  state.montantHt = null;

  $("#clientSelect").value = "";
  $("#clientSummary").hidden = true;
  $("#toStep2").disabled = true;
  $("#dateEffet").value = "";
  $("#dureeJours").value = 30;
  $("#engineList").innerHTML = "";
  $("#montantHt").value = "";
  $("#dateSignature").value = "";
  $("#lieuSignature").value = "";
  $("#taciteReconduction").checked = false;

  goToStep(1);
}

document.addEventListener("DOMContentLoaded", () => {
  loadClients();

  $("#clientSelect").addEventListener("change", onClientChange);
  $("#dateEffet").addEventListener("change", loadEngines);
  $("#dureeJours").addEventListener("change", loadEngines);

  document.querySelectorAll("[data-next]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const next = Number(btn.dataset.next);
      if (next === 3) onEnterStep3();
      if (next === 4) {
        if (!Number($("#montantHt").value) || Number($("#montantHt").value) <= 0) {
          showError("Merci de renseigner un montant valide.");
          return;
        }
        onEnterStep4();
      }
      goToStep(next);
    });
  });

  document.querySelectorAll("[data-back]").forEach((btn) => {
    btn.addEventListener("click", () => goToStep(Number(btn.dataset.back)));
  });

  $("#submitContrat").addEventListener("click", submitContrat);
  $("#newAnother").addEventListener("click", resetWizard);
});
