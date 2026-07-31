/* ============================================================
   AB ENGINS SARL — Gestion des utilisateurs (admin)
   ============================================================ */
"use strict";

const ROLE_LABEL = { admin: "Administrateur", agent: "Agent", technicien: "Technicien", client: "Client" };
let pwdUserId = null;

function fmtDerniereConnexion(dt) {
  if (!dt) return "Jamais";
  return new Date(dt.replace(" ", "T")).toLocaleDateString("fr-FR", {
    day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit",
  });
}

async function loadUsers() {
  const errorBox = $("#usersError");
  errorBox.hidden = true;
  try {
    const data = await apiFetch("api/utilisateurs.php");
    renderUsers(data);
    renderEmployes(data.employes);
    renderClients(data.clients);
  } catch (e) {
    errorBox.textContent = "Erreur : " + e.message;
    errorBox.hidden = false;
  }
}

function renderUsers(data) {
  const tbody = $("#usersTable tbody");
  tbody.innerHTML = "";
  data.utilisateurs.forEach((u) => {
    const isMe = u.id === data.moi;
    const tr = el(`
      <tr>
        <td><strong>${u.identifiant}</strong>${isMe ? " <small>(vous)</small>" : ""}</td>
        <td>${ROLE_LABEL[u.role] || u.role}</td>
        <td>${u.liaison || "—"}</td>
        <td>${fmtDerniereConnexion(u.derniere_connexion)}</td>
        <td><span class="status ${u.actif ? "status-valide" : "status-expire"}">${u.actif ? "Actif" : "Désactivé"}</span></td>
        <td>
          <div class="assist-actions">
            ${isMe ? "" : `<button data-action="toggle" data-id="${u.id}">${u.actif ? "Désactiver" : "Réactiver"}</button>`}
            <button data-action="pwd" data-id="${u.id}" data-nom="${u.identifiant}">Mot de passe</button>
          </div>
        </td>
      </tr>`);
    tbody.appendChild(tr);
  });

  tbody.querySelectorAll("button[data-action]").forEach((btn) => {
    btn.addEventListener("click", () => {
      if (btn.dataset.action === "toggle") toggleActif(Number(btn.dataset.id));
      else openPwdModal(Number(btn.dataset.id), btn.dataset.nom);
    });
  });
}

function renderEmployes(employes) {
  const select = $("#employeSelect");
  select.querySelectorAll("option:not(:first-child)").forEach((o) => o.remove());
  employes.forEach((e) => {
    select.appendChild(el(`<option value="${e.id_employe}">${e.nom} (${e.poste})</option>`));
  });
}

function renderClients(clients) {
  const select = $("#clientSelect");
  select.querySelectorAll("option:not(:first-child)").forEach((o) => o.remove());
  (clients || []).forEach((c) => {
    select.appendChild(el(`<option value="${c.id_client}">${c.nom_client}</option>`));
  });
}

async function toggleActif(id) {
  try {
    await apiFetch("api/utilisateurs.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "toggle_actif", id }),
    });
    loadUsers();
  } catch (e) {
    const errorBox = $("#usersError");
    errorBox.textContent = "Erreur : " + e.message;
    errorBox.hidden = false;
  }
}

function openPwdModal(id, nom) {
  pwdUserId = id;
  $("#pwdModalTitle").textContent = `Mot de passe de ${nom}`;
  $("#pwdForm").reset();
  $("#pwdFormError").hidden = true;
  $("#pwdModalOverlay").hidden = false;
}

document.addEventListener("DOMContentLoaded", () => {
  loadUsers();

  // Modal nouvel utilisateur
  $("#openNewUser").addEventListener("click", () => {
    $("#userForm").reset();
    $("#userFormError").hidden = true;
    $("#employeField").hidden = false;
    $("#clientField").hidden = true;
    $("#userModalOverlay").hidden = false;
  });

  // Rôle client → on lie à un client ; sinon → à un employé (optionnel).
  $("#roleSelect").addEventListener("change", () => {
    const isClient = $("#roleSelect").value === "client";
    $("#employeField").hidden = isClient;
    $("#clientField").hidden = !isClient;
  });
  $("#closeNewUser").addEventListener("click", () => { $("#userModalOverlay").hidden = true; });
  $("#cancelNewUser").addEventListener("click", () => { $("#userModalOverlay").hidden = true; });

  $("#userForm").addEventListener("submit", async (ev) => {
    ev.preventDefault();
    const f = ev.target;
    const errorBox = $("#userFormError");
    errorBox.hidden = true;
    try {
      await apiFetch("api/utilisateurs.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "create",
          identifiant: f.identifiant.value.trim(),
          mot_de_passe: f.mot_de_passe.value,
          role: f.role.value,
          id_employe: f.id_employe.value,
          id_client: f.id_client.value,
        }),
      });
      $("#userModalOverlay").hidden = true;
      loadUsers();
    } catch (e) {
      errorBox.textContent = e.message;
      errorBox.hidden = false;
    }
  });

  // Modal mot de passe
  $("#closePwd").addEventListener("click", () => { $("#pwdModalOverlay").hidden = true; });
  $("#cancelPwd").addEventListener("click", () => { $("#pwdModalOverlay").hidden = true; });

  $("#pwdForm").addEventListener("submit", async (ev) => {
    ev.preventDefault();
    const errorBox = $("#pwdFormError");
    errorBox.hidden = true;
    try {
      await apiFetch("api/utilisateurs.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "reset_password",
          id: pwdUserId,
          mot_de_passe: ev.target.mot_de_passe.value,
        }),
      });
      $("#pwdModalOverlay").hidden = true;
      loadUsers();
    } catch (e) {
      errorBox.textContent = e.message;
      errorBox.hidden = false;
    }
  });
});
