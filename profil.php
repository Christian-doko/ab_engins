<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'profil';
$pageTitle = 'Mon profil';
$pageSubtitle = 'Compte et sécurité';
$pageScripts = ['js/profil.js'];

require __DIR__ . '/partials/head.php';
?>
        <p class="form-error" id="profilError" hidden></p>

        <section class="grid-2">
          <article class="card">
            <div class="card-head"><h3>Mon compte</h3></div>
            <dl class="profil-list" id="profilCompte"></dl>
          </article>

          <article class="card">
            <div class="card-head"><h3>Fiche employé</h3></div>
            <dl class="profil-list" id="profilEmploye"></dl>
          </article>
        </section>

        <section class="card">
          <div class="card-head"><h3>Changer mon mot de passe</h3></div>
          <form id="pwdForm">
            <div class="form-grid">
              <label>Mot de passe actuel
                <input type="password" name="ancien" required autocomplete="current-password" />
              </label>
              <label>Nouveau mot de passe (8 caractères min.)
                <input type="password" name="nouveau" required minlength="8" autocomplete="new-password" />
              </label>
            </div>
            <p class="form-error" id="pwdError" hidden></p>
            <p class="form-success" id="pwdSuccess" hidden>Mot de passe modifié avec succès.</p>
            <div class="modal-actions">
              <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </div>
          </form>
        </section>
<?php require __DIR__ . '/partials/foot.php'; ?>
