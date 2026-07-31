<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/**
 * Gestion des comptes utilisateurs — réservé au rôle admin.
 * GET : liste des comptes + employés rattachables.
 * POST : create | toggle_actif | reset_password.
 */

$currentUser = requireAuth();
if (($currentUser['role'] ?? '') !== 'admin') {
    json_out(['error' => 'Accès réservé aux administrateurs'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    if ($method === 'GET') {
        $utilisateurs = $pdo->query(
            "SELECT u.id_utilisateur, u.identifiant, u.role, u.actif, u.derniere_connexion,
                    TRIM(CONCAT(COALESCE(e.prenom_employe, ''), ' ', COALESCE(e.nom_employe, ''))) AS employe
             FROM utilisateur u
             LEFT JOIN employe e ON e.id_employe = u.id_employe
             ORDER BY u.identifiant"
        )->fetchAll();

        $employes = $pdo->query(
            "SELECT id_employe, CONCAT(prenom_employe, ' ', nom_employe) AS nom, poste
             FROM employe ORDER BY nom_employe"
        )->fetchAll();

        json_out([
            'utilisateurs' => array_map(fn($u) => [
                'id' => (int) $u['id_utilisateur'],
                'identifiant' => $u['identifiant'],
                'role' => $u['role'],
                'actif' => (bool) $u['actif'],
                'derniere_connexion' => $u['derniere_connexion'],
                'employe' => $u['employe'] ?: null,
            ], $utilisateurs),
            'employes' => $employes,
            'moi' => (int) $currentUser['id'],
        ]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = (string) ($body['action'] ?? 'create');

        if ($action === 'create') {
            $identifiant = trim((string) ($body['identifiant'] ?? ''));
            $mdp = (string) ($body['mot_de_passe'] ?? '');
            $role = (string) ($body['role'] ?? 'agent');
            $idEmploye = isset($body['id_employe']) && $body['id_employe'] !== '' ? (int) $body['id_employe'] : null;

            if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $identifiant)) {
                json_out(['error' => 'Identifiant invalide (3 à 50 caractères : lettres, chiffres, point, tiret, underscore)'], 422);
            }
            if (strlen($mdp) < 8) {
                json_out(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], 422);
            }
            if (!in_array($role, ['admin', 'agent', 'technicien'], true)) {
                json_out(['error' => 'Rôle invalide'], 422);
            }

            $exists = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE identifiant = :i');
            $exists->execute(['i' => $identifiant]);
            if ((int) $exists->fetchColumn() > 0) {
                json_out(['error' => 'Cet identifiant existe déjà'], 409);
            }

            $pdo->prepare(
                "INSERT INTO utilisateur (identifiant, mot_de_passe_hash, role, actif, id_employe)
                 VALUES (:i, :h, :r, TRUE, :e)"
            )->execute([
                'i' => $identifiant,
                'h' => password_hash($mdp, PASSWORD_DEFAULT),
                'r' => $role,
                'e' => $idEmploye,
            ]);
            json_out(['ok' => true], 201);
        }

        if ($action === 'toggle_actif') {
            $id = (int) ($body['id'] ?? 0);
            if ($id === (int) $currentUser['id']) {
                json_out(['error' => 'Vous ne pouvez pas désactiver votre propre compte'], 422);
            }
            $existe = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE id_utilisateur = :id');
            $existe->execute(['id' => $id]);
            if ((int) $existe->fetchColumn() === 0) {
                json_out(['error' => "Utilisateur introuvable (action toggle_actif, id reçu : {$id})"], 404);
            }
            $pdo->prepare('UPDATE utilisateur SET actif = NOT actif WHERE id_utilisateur = :id')
                ->execute(['id' => $id]);
            json_out(['ok' => true]);
        }

        if ($action === 'reset_password') {
            $id = (int) ($body['id'] ?? 0);
            $mdp = (string) ($body['mot_de_passe'] ?? '');
            if (strlen($mdp) < 8) {
                json_out(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], 422);
            }
            $existe = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE id_utilisateur = :id');
            $existe->execute(['id' => $id]);
            if ((int) $existe->fetchColumn() === 0) {
                json_out(['error' => "Utilisateur introuvable (action reset_password, id reçu : {$id})"], 404);
            }
            $pdo->prepare('UPDATE utilisateur SET mot_de_passe_hash = :h WHERE id_utilisateur = :id')
                ->execute(['h' => password_hash($mdp, PASSWORD_DEFAULT), 'id' => $id]);
            json_out(['ok' => true]);
        }

        json_out(['error' => 'Action inconnue'], 400);
    }

    json_out(['error' => 'Méthode non supportée'], 405);
} catch (Throwable $e) {
    handle_error($e);
}
