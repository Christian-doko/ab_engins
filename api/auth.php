<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    if ($method === 'GET') {
        // Vérifie si une session est déjà ouverte (appelé au chargement de chaque page).
        if (empty($_SESSION['user'])) {
            json_out(['authenticated' => false], 401);
        }
        json_out(['authenticated' => true, 'user' => $_SESSION['user']]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        // Déconnexion
        if (($body['action'] ?? '') === 'logout') {
            $_SESSION = [];
            session_destroy();
            json_out(['ok' => true]);
        }

        // Connexion
        $identifiant = trim((string) ($body['identifiant'] ?? ''));
        $motDePasse = (string) ($body['mot_de_passe'] ?? '');

        if ($identifiant === '' || $motDePasse === '') {
            json_out(['error' => 'Identifiant et mot de passe requis'], 422);
        }

        // La colonne id_client n'existe qu'après la migration espace client :
        // on la lit prudemment pour rester compatible avec un schéma non migré.
        $hasClientCol = (bool) $pdo->query("SHOW COLUMNS FROM utilisateur LIKE 'id_client'")->fetch();
        $clientSelect = $hasClientCol ? 'u.id_client, cl.nom_client,' : 'NULL AS id_client, NULL AS nom_client,';
        $clientJoin = $hasClientCol ? 'LEFT JOIN client cl ON cl.id_client = u.id_client' : '';

        $stmt = $pdo->prepare(
            "SELECT u.id_utilisateur, u.identifiant, u.mot_de_passe_hash, u.role, u.actif,
                    {$clientSelect}
                    e.nom_employe, e.prenom_employe
             FROM utilisateur u
             LEFT JOIN employe e ON e.id_employe = u.id_employe
             {$clientJoin}
             WHERE u.identifiant = :identifiant"
        );
        $stmt->execute(['identifiant' => $identifiant]);
        $row = $stmt->fetch();

        if (!$row || !$row['actif'] || !password_verify($motDePasse, $row['mot_de_passe_hash'])) {
            json_out(['error' => 'Identifiant ou mot de passe incorrect'], 401);
        }

        $user = [
            'id' => (int) $row['id_utilisateur'],
            'identifiant' => $row['identifiant'],
            'role' => $row['role'],
            'id_client' => $row['id_client'] !== null ? (int) $row['id_client'] : null,
            'nom_complet' => trim(($row['prenom_employe'] ?? '') . ' ' . ($row['nom_employe'] ?? ''))
                ?: ($row['nom_client'] ?? '')
                ?: $row['identifiant'],
        ];

        session_regenerate_id(true);
        $_SESSION['user'] = $user;

        $pdo->prepare("UPDATE utilisateur SET derniere_connexion = NOW() WHERE id_utilisateur = :id")
            ->execute(['id' => $user['id']]);

        json_out(['ok' => true, 'user' => $user]);
    }

    json_out(['error' => 'Méthode non supportée'], 405);
} catch (Throwable $e) {
    handle_error($e);
}
