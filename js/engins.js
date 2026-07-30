/* ============================================================
   AB ENGINS SARL — Page Engins
   ============================================================ */
"use strict";

const DISPO_LABEL = { disponible: "disponible", loue: "loué", maintenance: "maintenance" };
const DISPO_CLASS = { disponible: "status-actif", loue: "status-resilie", maintenance: "status-suspendu" };
let enginsCache = [];

function renderEngineGrid(list) {
  const grid = $("#engineGrid");
  const empty = $("#emptyState");
  grid.innerHTML = "";

  if (!list.length) { empty.hidden = false; return; }
  empty.hidden = true;

  list.forEach((e) => {
    const card = el(`
      <article class="engine-card">
        <div class="engine-card-head">
          <span class="mono">${e.code}</span>
          <div class="kebab-wrap">
            <button class="kebab-btn" data-kebab="${e.id}">⋮</button>
          </div>
        </div>
        <h4>${e.type}${e.modele ? " " + e.modele : ""}</h4>
        <small>N° série ${e.numero_serie || "—"}</small>
        <small>État : ${e.etat}</small>
        <div class="engine-card-foot">
          <span class="status ${DISPO_CLASS[e.disponibilite]}">${DISPO_LABEL[e.disponibilite]}</span>
        </div>
      </article>`);

    const kebabBtn = card.querySelector(".kebab-btn");
    kebabBtn.addEventListener("click", (evt) => {
      evt.stopPropagation();
      closeAllMenus();
      const menu = el(`
        <div class="kebab-menu">
          <button data-set="disponible">Marquer disponible</button>
          <button data-set="maintenance">Mettre en maintenance</button>
          <button data-set="loue">Marquer loué</button>
        </div>`);
      menu.querySelectorAll("button").forEach((b) => {
        b.addEventListener("click", async (ev) => {
          ev.stopPropagation();
          await setDisponibilite(e.id, b.dataset.set);
          menu.remove();
        });
      });
      kebabBtn.parentElement.appendChild(menu);
    });

    grid.appendChild(card);
  });
}

function closeAllMenus() { document.querySelectorAll(".kebab-menu").forEach((m) => m.remove()); }

async function setDisponibilite(id, disponibilite) {
  try {
    await apiFetch("api/engins.php", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id_engin: id, disponibilite }),
    });
    await loadEngins();
  } catch (err) {
    alert("Erreur : " + err.message);
  }
}

function applyTypeFilter(type) {
  if (!type) return renderEngineGrid(enginsCache);
  renderEngineGrid(enginsCache.filter((e) => e.type === type));
}

async function loadEngins() {
  try {
    const data = await apiFetch("api/engins.php");
    enginsCache = data.engins;
    renderEngineGrid(enginsCache);

    const types = [...new Set(enginsCache.map((e) => e.type))].sort();
    const select = $("#filterType");
    const current = select.value;
    select.innerHTML = '<option value="">Tous les types</option>' + types.map((t) => `<option value="${t}">${t}</option>`).join("");
    select.value = current;
  } catch (err) {
    $("#emptyState").hidden = false;
    $("#emptyState").textContent = "Impossible de charger les engins : " + err.message;
  }
}

function openEnginModal() { $("#enginModalOverlay").hidden = false; }
function closeEnginModal() {
  $("#enginModalOverlay").hidden = true;
  $("#enginForm").reset();
  $("#enginFormError").hidden = true;
}

document.addEventListener("DOMContentLoaded", () => {
  loadEngins();
  document.addEventListener("click", closeAllMenus);

  $("#filterType").addEventListener("change", (e) => applyTypeFilter(e.target.value));
  $("#openNewEngin").addEventListener("click", openEnginModal);
  $("#closeNewEngin").addEventListener("click", closeEnginModal);
  $("#cancelNewEngin").addEventListener("click", closeEnginModal);
  $("#enginModalOverlay").addEventListener("click", (e) => { if (e.target.id === "enginModalOverlay") closeEnginModal(); });

  $("#enginForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const errorBox = $("#enginFormError");
    const submitBtn = $("#submitNewEngin");
    errorBox.hidden = true;

    const payload = Object.fromEntries(new FormData(e.target).entries());
    submitBtn.disabled = true;
    submitBtn.textContent = "Enregistrement…";
    try {
      await apiFetch("api/engins.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      closeEnginModal();
      await loadEngins();
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Enregistrer";
    }
  });
});
