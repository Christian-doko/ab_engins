/* ============================================================
   AB ENGINS SARL — Page Permis
   ============================================================ */
"use strict";

const STATUT_LABEL_P = { valide: "valide", expire: "expiré", suspendu: "suspendu" };
let permisCache = [];

function renderPermisTable(list) {
  const tbody = document.querySelector("#permisTable tbody");
  const empty = $("#emptyState");
  tbody.innerHTML = "";

  if (!list.length) { empty.hidden = false; return; }
  empty.hidden = true;

  list.forEach((p) => {
    const badge = `<span class="status status-${p.statut}">${STATUT_LABEL_P[p.statut] || p.statut}</span>`;
    const localisation = [p.arrondissement, p.departement, p.region].filter(Boolean).join(", ");
    let action = "";
    if (p.statut !== "expire") {
      const nextStatut = p.statut === "suspendu" ? "valide" : "suspendu";
      const label = p.statut === "suspendu" ? "Réactiver" : "Suspendre";
      action = `<button class="link" data-id="${p.id}" data-statut="${nextStatut}">${label}</button>`;
    }
    tbody.appendChild(el(`
      <tr>
        <td class="mono">${p.numero_permis}</td>
        <td>${p.client}</td>
        <td>${localisation || "—"}</td>
        <td>${p.date_expiration.split("-").reverse().join("/")} <small style="color:var(--muted)">(${p.jours_restants} j)</small></td>
        <td>${badge}</td>
        <td>${action}</td>
      </tr>`));
  });

  tbody.querySelectorAll("button[data-id]").forEach((btn) => {
    btn.addEventListener("click", () => updateStatut(Number(btn.dataset.id), btn.dataset.statut));
  });
}

async function updateStatut(id, statut) {
  try {
    await apiFetch("api/permis.php", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id_permis: id, statut_permis: statut }),
    });
    await loadPermis();
  } catch (err) {
    alert("Erreur : " + err.message);
  }
}

function applyFilterPermis(term) {
  const t = term.trim().toLowerCase();
  if (!t) return renderPermisTable(permisCache);
  renderPermisTable(permisCache.filter((p) =>
    (p.numero_permis + " " + p.client + " " + p.region).toLowerCase().includes(t)
  ));
}

async function loadPermis() {
  try {
    const data = await apiFetch("api/permis.php");
    permisCache = data.permis;
    renderPermisTable(permisCache);
    $("#permisClientSelect").innerHTML = data.clients.map((c) => `<option value="${c.id_client}">${c.nom_client}</option>`).join("");
  } catch (err) {
    $("#emptyState").hidden = false;
    $("#emptyState").textContent = "Impossible de charger les permis : " + err.message;
  }
}

function openPermisModal() { $("#permisModalOverlay").hidden = false; }
function closePermisModal() {
  $("#permisModalOverlay").hidden = true;
  $("#permisForm").reset();
  $("#permisFormError").hidden = true;
}

document.addEventListener("DOMContentLoaded", () => {
  loadPermis();

  $("#searchPermis").addEventListener("input", (e) => applyFilterPermis(e.target.value));
  $("#openNewPermis").addEventListener("click", openPermisModal);
  $("#closeNewPermis").addEventListener("click", closePermisModal);
  $("#cancelNewPermis").addEventListener("click", closePermisModal);
  $("#permisModalOverlay").addEventListener("click", (e) => { if (e.target.id === "permisModalOverlay") closePermisModal(); });

  $("#permisForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const errorBox = $("#permisFormError");
    const submitBtn = $("#submitNewPermis");
    errorBox.hidden = true;

    const payload = Object.fromEntries(new FormData(e.target).entries());
    submitBtn.disabled = true;
    submitBtn.textContent = "Enregistrement…";
    try {
      await apiFetch("api/permis.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      closePermisModal();
      await loadPermis();
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Enregistrer";
    }
  });
});
