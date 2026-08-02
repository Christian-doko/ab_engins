<?php
declare(strict_types=1);
require_once __DIR__ . '/api/config.php';

// Déjà connecté -> pas besoin de repasser par le formulaire.
if (!empty($_SESSION['user'])) {
    header('Location: ' . ($_SESSION['user']['role'] === 'client' ? 'espace-client.php' : 'index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AB ENGINS — Connexion</title>
  <link rel="icon" href="assets/logo.svg" type="image/svg+xml" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/styles.css" />
  <link rel="stylesheet" href="css/login.css" />
</head>
<body class="login-body">
  <div class="login-card">
    <img class="login-logo" src="assets/logo.svg" alt="AB ENGINS" />
    <h1>AB ENGINS SARL</h1>
    <p class="login-sub">Plateforme de gestion des locations</p>

    <form id="loginForm" novalidate>
      <label for="identifiant">Identifiant</label>
      <input type="text" id="identifiant" name="identifiant" autocomplete="username" required autofocus />

      <label for="motDePasse">Mot de passe</label>
      <input type="password" id="motDePasse" name="motDePasse" autocomplete="current-password" required />

      <p class="login-error" id="loginError" hidden></p>

      <button type="submit" class="btn btn-login" id="loginSubmit">SE CONNECTER</button>
    </form>

    <a href="#" class="login-forgot">Mot de passe oublié ?</a>
  </div>

  <script>
    const form = document.getElementById("loginForm");
    const errorBox = document.getElementById("loginError");
    const submitBtn = document.getElementById("loginSubmit");

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      errorBox.hidden = true;
      submitBtn.disabled = true;
      submitBtn.textContent = "Connexion…";

      try {
        const res = await fetch("api/auth.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            identifiant: document.getElementById("identifiant").value.trim(),
            mot_de_passe: document.getElementById("motDePasse").value,
          }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || "Connexion impossible");
        window.location.href = data.user && data.user.role === "client" ? "espace-client.php" : "index.php";
      } catch (err) {
        errorBox.textContent = err.message;
        errorBox.hidden = false;
        submitBtn.disabled = false;
        submitBtn.textContent = "SE CONNECTER";
      }
    });
  </script>
</body>
</html>
