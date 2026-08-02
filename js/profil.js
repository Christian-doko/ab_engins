/* ============================================================
   AB ENGINS SARL — Profil de l'utilisateur connecté (personnel)
   ============================================================ */
"use strict";

const ROLE_LIBELLE = { admin: "Administrateur", agent: "Agent", technicien: "Technicien", client: "Client" };
const POSTE_LIBELLE = {
  conducteur: "Conducteur", mecanicien: "Mécanicien", motorboy: "Motorboy",
  agent_reception: "Agent de réception", technicien: "Technicien",
};

const ligne = (label, valeur) => `<div><dt>${label}</dt><dd>${valeur || "—"}</dd></div>`;

function fmtDate(dt) {
  if (!dt) return null;
  return new Date(String(dt).replace(" ", "T")).toLocaleString("fr-FR", {
    day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit",
  });
}

async function loadProfil() {
  const errorBox = $("#profilError");
  errorBox.hidden = true;
  try {
    const { profil } = await apiFetch("api/profil.php");

    $("#profilCompte").innerHTML =
      ligne("Nom", profil.nom_complet) +
      ligne("Identifiant", profil.identifiant) +
      ligne("Rôle", ROLE_LIBELLE[profil.role] || profil.role) +
      ligne("Statut du compte", profil.actif ? "Actif" : "Désactivé") +
      ligne("Dernière connexion", fmtDate(profil.derniere_connexion) || "Première connexion");

    $("#profilEmploye").innerHTML = profil.employe
      ? ligne("Nom", profil.employe.nom) +
        ligne("Poste", POSTE_LIBELLE[profil.employe.poste] || profil.employe.poste) +
        ligne("Téléphone", profil.employe.telephone)
      : ligne("Employé lié", "Aucun employé rattaché à ce compte");
  } catch (e) {
    errorBox.textContent = "Erreur : " + e.message;
    errorBox.hidden = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  loadProfil();

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
      loadProfil();
    } catch (e) {
      err.textContent = e.message;
      err.hidden = false;
    }
  });
});
