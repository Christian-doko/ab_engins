<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

// Page réservée aux administrateurs.
if (($currentUser['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$activeNav = 'users';
$pageTitle = 'Utilisateurs';
$pageSubtitle = 'Comptes et accès à l\'application';
$pageScripts = ['js/utilisateurs.js'];

require __DIR__ . '/partials/head.php';
?>
        <section class="card">
          <div class="clients-toolbar">
            <div></div>
            <button class="btn btn-primary" id="openNewUser" type="button">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
              Nouvel utilisateur
            </button>
          </div>

          <div class="table-wrap">
            <table class="table" id="usersTable">
              <thead>
                <tr>
                  <th>Identifiant</th>
                  <th>Rôle</th>
                  <th>Lié à</th>
                  <th>Dernière connexion</th>
                  <th>Statut</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody><!-- injecté --></tbody>
            </table>
          </div>
          <p class="form-error" id="usersError" hidden></p>
        </section>

        <!-- Modal : nouvel utilisateur -->
        <div class="modal-overlay" id="userModalOverlay" hidden>
          <div class="modal">
            <div class="modal-head">
              <h3>Nouvel utilisateur</h3>
              <button class="icon-btn" id="closeNewUser" type="button" aria-label="Fermer">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <form id="userForm">
              <div class="form-grid">
                <label>Identifiant
                  <input type="text" name="identifiant" required autocomplete="off" />
                </label>
                <label>Rôle
                  <select name="role" id="roleSelect">
                    <option value="agent">Agent</option>
                    <option value="technicien">Technicien</option>
                    <option value="admin">Administrateur</option>
                    <option value="client">Client (espace client)</option>
                  </select>
                </label>
                <label>Mot de passe (8 caractères min.)
                  <input type="password" name="mot_de_passe" required autocomplete="new-password" />
                </label>
                <label id="employeField">Employé lié (optionnel)
                  <select name="id_employe" id="employeSelect">
                    <option value="">— Aucun —</option>
                  </select>
                </label>
                <label id="clientField" hidden>Client rattaché
                  <select name="id_client" id="clientSelect">
                    <option value="">— Choisir un client —</option>
                  </select>
                </label>
              </div>
              <p class="form-error" id="userFormError" hidden></p>
              <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="cancelNewUser">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer le compte</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal : réinitialiser le mot de passe -->
        <div class="modal-overlay" id="pwdModalOverlay" hidden>
          <div class="modal">
            <div class="modal-head">
              <h3 id="pwdModalTitle">Réinitialiser le mot de passe</h3>
              <button class="icon-btn" id="closePwd" type="button" aria-label="Fermer">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <form id="pwdForm">
              <div class="form-grid">
                <label class="span-2">Nouveau mot de passe (8 caractères min.)
                  <input type="password" name="mot_de_passe" required autocomplete="new-password" />
                </label>
              </div>
              <p class="form-error" id="pwdFormError" hidden></p>
              <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="cancelPwd">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
<?php require __DIR__ . '/partials/foot.php'; ?>
