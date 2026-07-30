/* ============================================================
   AB ENGINS SARL — Page Assistance
   ============================================================ */
"use strict";

const ASSIST_STATUT_LABEL = { en_attente: "En attente", en_cours: "En cours", resolu: "Résolu" };
let assistCache = [];

function renderAssistList(list) {
  const ul = $("#assistList");
  const empty = $("#emptyState");
  ul.innerHTML = "";

  if (!list.length) { empty.hidden = false; return; }
  empty.hidden = true;

  list.forEach((a) => {
    const date = new Date(a.date_intervention.replace(" ", "T")).toLocaleDateString("fr-FR", { day: "2-digit", month: "2-digit", year: "numeric" });
    let actions = "";
    if (a.statut === "en_attente") actions += `<button data-id="${a.id}" data-action="en_cours">Prendre en charge</button>`;
    if (a.statut !== "resolu") actions += `<button data-id="${a.id}" data-action="resolu">Marquer résolu</button>`;

    const li = el(`
      <li class="assist-item">
        <div class="assist-item-head">
          <div>
            <strong>${a.client}</strong> — ${a.engin}
            <br /><small>${date} · Technicien : ${a.technicien}</small>
          </div>
          <span class="status status-${a.statut}">${ASSIST_STATUT_LABEL[a.statut]}</span>
        </div>
        <p><strong>Motif :</strong> ${a.motif}</p>
        ${a.description ? `<p><strong>Description :</strong> ${a.description}</p>` : ""}
        ${a.resolution ? `<p><strong>Résolution :</strong> ${a.resolution}</p>` : ""}
        <div class="assist-actions">${actions}</div>
      </li>`);

    li.querySelectorAll("button[data-action]").forEach((btn) => {
      btn.addEventListener("click", () => handleStatutChange(Number(btn.dataset.id), btn.dataset.action));
    });
    ul.appendChild(li);
  });
}

async function handleStatutChange(id, statut) {
  let resolution = "";
  if (statut === "resolu") {
    resolution = prompt("Décrire la résolution apportée :") || "";
    if (!resolution.trim()) return;
  }
  try {
    await apiFetch("api/assistance.php", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id_assistance: id, statut_intervention: statut, resolution }),
    });
    await loadAssist();
  } catch (err) {
    alert("Erreur : " + err.message);
  }
}

function applyStatutFilterAssist(statut) {
  if (!statut) return renderAssistList(assistCache);
  renderAssistList(assistCache.filter((a) => a.statut === statut));
}

async function loadAssist() {
  try {
    const data = await apiFetch("api/assistance.php");
    assistCache = data.interventions;
    renderAssistList(assistCache);
    $("#assistEngineSelect").innerHTML = data.contrats_engins.map((c) =>
      `<option value="${c.id_contrat_engin}">${c.client} — ${c.type_engin} ${c.modele_engin} (${c.code_engin})</option>`
    ).join("");
    $("#assistTechSelect").innerHTML = data.techniciens.map((t) => `<option value="${t.id_employe}">${t.nom}</option>`).join("");
  } catch (err) {
    $("#emptyState").hidden = false;
    $("#emptyState").textContent = "Impossible de charger les interventions : " + err.message;
  }
}

function openAssistModal() { $("#assistModalOverlay").hidden = false; }
function closeAssistModal() {
  $("#assistModalOverlay").hidden = true;
  $("#assistForm").reset();
  $("#assistFormError").hidden = true;
}

document.addEventListener("DOMContentLoaded", () => {
  loadAssist();

  $("#filterStatutAssist").addEventListener("change", (e) => applyStatutFilterAssist(e.target.value));
  $("#openNewAssist").addEventListener("click", openAssistModal);
  $("#closeNewAssist").addEventListener("click", closeAssistModal);
  $("#cancelNewAssist").addEventListener("click", closeAssistModal);
  $("#assistModalOverlay").addEventListener("click", (e) => { if (e.target.id === "assistModalOverlay") closeAssistModal(); });

  $("#assistForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const errorBox = $("#assistFormError");
    const submitBtn = $("#submitNewAssist");
    errorBox.hidden = true;

    const payload = Object.fromEntries(new FormData(e.target).entries());
    submitBtn.disabled = true;
    submitBtn.textContent = "Enregistrement…";
    try {
      await apiFetch("api/assistance.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      closeAssistModal();
      await loadAssist();
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Enregistrer";
    }
  });
});
