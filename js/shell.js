/* ============================================================
   AB ENGINS SARL — Comportements partagés entre toutes les pages
   (menu mobile, déconnexion, wrapper fetch avec gestion de session expirée)
   ============================================================ */
"use strict";

const $ = (sel) => document.querySelector(sel);
const el = (html) => { const t = document.createElement("template"); t.innerHTML = html.trim(); return t.content.firstElementChild; };

// Partagé entre factures.js et facture-detail.js.
const FACTURE_STATUT_LABEL = { paye: "payé", partiel: "partiel", en_retard: "en retard", impaye: "impayé" };
const FACTURE_STATUT_CLASS = { paye: "status-valide", partiel: "status-suspendu", en_retard: "status-expire", impaye: "status-expire" };

// Wrapper fetch commun : redirige vers la connexion si la session a expiré (401),
// sinon laisse l'appelant gérer l'erreur (ex. repli sur données de démo).
async function apiFetch(url, options = {}) {
  const res = await fetch(url, {
    headers: { Accept: "application/json", ...(options.headers || {}) },
    ...options,
  });
  if (res.status === 401) {
    window.location.href = "login.php";
    throw new Error("Session expirée");
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || ("HTTP " + res.status));
  return data;
}

async function logout() {
  try {
    await fetch("api/auth.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "logout" }),
    });
  } finally {
    window.location.href = "login.php";
  }
}

function openSidebar()  { $("#sidebar").classList.add("open"); $("#overlay").classList.add("show"); }
function closeSidebar() { $("#sidebar").classList.remove("open"); $("#overlay").classList.remove("show"); }

function renderToday() {
  const target = $("#today");
  if (!target) return;
  const opts = { weekday: "long", day: "numeric", month: "long", year: "numeric" };
  const d = new Date().toLocaleDateString("fr-FR", opts);
  target.textContent = d.charAt(0).toUpperCase() + d.slice(1);
}

document.addEventListener("DOMContentLoaded", () => {
  renderToday();

  const burger = $("#burger");
  const overlay = $("#overlay");
  if (burger) burger.addEventListener("click", openSidebar);
  if (overlay) overlay.addEventListener("click", closeSidebar);

  document.querySelectorAll(".nav-item").forEach((a) => a.addEventListener("click", closeSidebar));

  const logoutBtn = $("#logoutBtn");
  if (logoutBtn) logoutBtn.addEventListener("click", (e) => { e.preventDefault(); logout(); });
});
