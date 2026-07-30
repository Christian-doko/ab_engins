/* ============================================================
   AB ENGINS SARL — Page Clients
   ============================================================ */
"use strict";

const STATUT_LABEL = { valide: "valide", expire: "expiré", suspendu: "suspendu" };
let clientsCache = [];

function renderClients(list) {
  const tbody = document.querySelector("#clientTable tbody");
  const empty = $("#emptyState");
  tbody.innerHTML = "";

  if (!list.length) {
    empty.hidden = false;
    return;
  }
  empty.hidden = true;

  list.forEach((c) => {
    const statut = c.statut_permis;
    const statutHtml = statut
      ? `<span class="status status-${statut}">${STATUT_LABEL[statut] || statut}</span>`
      : `<span class="status" style="background:var(--bg);color:var(--muted)">—</span>`;
    tbody.appendChild(el(`
      <tr>
        <td><strong>${c.nom}</strong></td>
        <td>${c.secteur}</td>
        <td>${c.telephone || "—"}</td>
        <td>${c.permis || "—"}</td>
        <td>${statutHtml}</td>
      </tr>`));
  });
}

function applyFilter(term) {
  const t = term.trim().toLowerCase();
  if (!t) return renderClients(clientsCache);
  renderClients(clientsCache.filter((c) =>
    (c.nom + " " + c.telephone + " " + c.secteur).toLowerCase().includes(t)
  ));
}

async function loadClients() {
  try {
    const data = await apiFetch("api/clients.php");
    clientsCache = data.clients;
    renderClients(clientsCache);
    fillSecteurs(data.secteurs);
  } catch (err) {
    $("#emptyState").hidden = false;
    $("#emptyState").textContent = "Impossible de charger les clients : " + err.message;
  }
}

function fillSecteurs(secteurs) {
  const select = $("#secteurSelect");
  select.innerHTML = secteurs.map((s) => `<option value="${s.id_secteur}">${s.libelle_secteur}</option>`).join("");
}

function openModal() { $("#clientModalOverlay").hidden = false; }
function closeModal() {
  $("#clientModalOverlay").hidden = true;
  $("#clientForm").reset();
  $("#clientFormError").hidden = true;
}

document.addEventListener("DOMContentLoaded", () => {
  loadClients();

  $("#searchClient").addEventListener("input", (e) => applyFilter(e.target.value));
  $("#openNewClient").addEventListener("click", openModal);
  $("#closeNewClient").addEventListener("click", closeModal);
  $("#cancelNewClient").addEventListener("click", closeModal);
  $("#clientModalOverlay").addEventListener("click", (e) => { if (e.target.id === "clientModalOverlay") closeModal(); });

  $("#clientForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const errorBox = $("#clientFormError");
    const submitBtn = $("#submitNewClient");
    errorBox.hidden = true;

    const form = new FormData(e.target);
    const payload = Object.fromEntries(form.entries());

    submitBtn.disabled = true;
    submitBtn.textContent = "Enregistrement…";
    try {
      await apiFetch("api/clients.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      closeModal();
      await loadClients();
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Enregistrer";
    }
  });
});
