<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/**
 * Profil de l'utilisateur connecte (personnel ou client).
 * GET  : informations du compte et de l'entite rattachee.
 * POST : changement de son propre mot de passe (ancien exige).
 */

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    if ($method === 'GET') {
        $hasClientCol = (bool) $pdo->query("SHOW COLUMNS FROM utilisateur LIKE 'id_client'")->fetch();
        $selectClient = $hasClientCol ? 'u.id_client,' : 'NULL AS id_client,';

        $stmt = $pdo->prepare(
            "SELECT u.identifiant, u.role, u.actif, u.derniere_connexion, u.id_employe, {$selectClient}
                    e.nom_employe, e.prenom_employe, e.poste, e.telephone_employe
             FROM utilisateur u
             LEFT JOIN employe e ON e.id_employe = u.id_employe
             WHERE u.id_utilisateur = :id"
        );
        $stmt->execute(['id' => $user['id']]);
        $row = $stmt->fetch();
        if (!$row) {
            json_out(['error' => 'Compte introuvable'], 404);
        }

        $profil = [
            'identifiant' => $row['identifiant'],
            'role' => $row['role'],
            'actif' => (bool) $row['actif'],
            'derniere_connexion' => $row['derniere_connexion'],
            'nom_complet' => $user['nom_complet'],
            'employe' => null,
            'client' => null,
        ];

        if ($row['id_employe']) {
            $profil['employe'] = [
                'nom' => trim(($row['prenom_employe'] ?? '') . ' ' . ($row['nom_employe'] ?? '')),
                'poste' => $row['poste'],
                'telephone' => $row['telephone_employe'],
            ];
        }

        if ($row['id_client']) {
            $cl = $pdo->prepare(
                "SELECT c.nom_client, c.nom_representant, c.cni_representant, c.telephone_client,
                        c.email_client, c.adresse_client, s.libelle_secteur
                 FROM client c
                 INNER JOIN secteur_activite s ON s.id_secteur = c.id_secteur
                 WHERE c.id_client = :id"
            );
            $cl->execute(['id' => (int) $row['id_client']]);
            $profil['client'] = $cl->fetch() ?: null;
        }

        // Statistiques d'activite propres au compte.
        if ($row['id_client']) {
            $idClient = (int) $row['id_client'];
            $profil['stats'] = [
                'contrats' => (int) $pdo->query("SELECT COUNT(*) FROM contrat WHERE id_client = {$idClient}")->fetchColumn(),
                'factures' => (int) $pdo->query("SELECT COUNT(*) FROM facture f INNER JOIN contrat ct ON ct.id_contrat = f.id_contrat WHERE ct.id_client = {$idClient}")->fetchColumn(),
                'permis' => (int) $pdo->query("SELECT COUNT(*) FROM permis WHERE id_client = {$idClient}")->fetchColumn(),
            ];
        }

        json_out(['profil' => $profil]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (($body['action'] ?? '') !== 'changer_mot_de_passe') {
            json_out(['error' => 'Action inconnue'], 400);
        }

        $ancien = (string) ($body['ancien_mot_de_passe'] ?? '');
        $nouveau = (string) ($body['nouveau_mot_de_passe'] ?? '');

        if (strlen($nouveau) < 8) {
            json_out(['error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères'], 422);
        }
        if ($ancien === $nouveau) {
            json_out(['error' => 'Le nouveau mot de passe doit être différent de l\'actuel'], 422);
        }

        $stmt = $pdo->prepare('SELECT mot_de_passe_hash FROM utilisateur WHERE id_utilisateur = :id');
        $stmt->execute(['id' => $user['id']]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($ancien, $hash)) {
            json_out(['error' => 'Mot de passe actuel incorrect'], 403);
        }

        $pdo->prepare('UPDATE utilisateur SET mot_de_passe_hash = :h WHERE id_utilisateur = :id')
            ->execute(['h' => password_hash($nouveau, PASSWORD_DEFAULT), 'id' => $user['id']]);

        json_out(['ok' => true]);
    }

    json_out(['error' => 'Méthode non supportée'], 405);
} catch (Throwable $e) {
    handle_error($e);
}
