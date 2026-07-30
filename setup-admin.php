<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';

/**
 * Création du premier compte administrateur — à usage unique.
 * Sécurité : refuse de s'exécuter dès qu'un utilisateur existe,
 * donc cette page devient inerte après la création du compte.
 */

header('Content-Type: text/html; charset=utf-8');

$error = '';
$done = false;

try {
    $pdo = db();
    $nbUsers = (int) $pdo->query('SELECT COUNT(*) FROM utilisateur')->fetchColumn();

    if ($nbUsers > 0) {
        $error = 'Un compte existe déjà : cette page est désactivée. Utilisez la page de connexion.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $identifiant = trim((string) ($_POST['identifiant'] ?? ''));
        $mdp = (string) ($_POST['mot_de_passe'] ?? '');
        $mdp2 = (string) ($_POST['mot_de_passe2'] ?? '');

        if ($identifiant === '' || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $identifiant)) {
            $error = "Identifiant invalide (3 à 50 caractères : lettres, chiffres, point, tiret, underscore).";
        } elseif (strlen($mdp) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($mdp !== $mdp2) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        } else {
            $st = $pdo->prepare(
                "INSERT INTO utilisateur (identifiant, mot_de_passe_hash, role, actif)
                 VALUES (:id, :hash, 'admin', TRUE)"
            );
            $st->execute([
                'id' => $identifiant,
                'hash' => password_hash($mdp, PASSWORD_DEFAULT),
            ]);
            $done = true;
        }
    }
} catch (Throwable $e) {
    $error = 'Erreur : ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Premier administrateur — AB ENGINS</title>
<style>
body{font-family:system-ui,sans-serif;max-width:460px;margin:48px auto;padding:0 16px;color:#16211b}
h1{font-size:22px}
label{display:block;margin:14px 0 4px;font-weight:600;font-size:14px}
input{width:100%;padding:10px 12px;border:1px solid #e6ece8;border-radius:8px;font:inherit;box-sizing:border-box}
button,a.btn{display:inline-block;margin-top:18px;background:#1a6b39;color:#fff;border:none;padding:10px 18px;border-radius:8px;font:inherit;cursor:pointer;text-decoration:none}
.err{color:#dc2626}.ok{color:#1a6b39}
</style>
</head>
<body>
<h1>Créer le premier administrateur</h1>
<?php if ($done): ?>
  <p class="ok"><strong>Compte administrateur créé.</strong> Cette page est désormais désactivée.</p>
  <a class="btn" href="login.php">Aller à la connexion</a>
<?php else: ?>
  <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($error === '' || $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <form method="post" autocomplete="off">
    <label for="identifiant">Identifiant</label>
    <input id="identifiant" name="identifiant" required value="<?= htmlspecialchars((string) ($_POST['identifiant'] ?? '')) ?>">
    <label for="mot_de_passe">Mot de passe (8 caractères minimum)</label>
    <input id="mot_de_passe" name="mot_de_passe" type="password" required>
    <label for="mot_de_passe2">Confirmer le mot de passe</label>
    <input id="mot_de_passe2" name="mot_de_passe2" type="password" required>
    <button type="submit">Créer le compte</button>
  </form>
  <?php endif; ?>
<?php endif; ?>
</body>
</html>
