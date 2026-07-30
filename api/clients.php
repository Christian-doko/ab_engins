<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();
    $statutExpr = permitStatusExpr('perm', true);

    if ($method === 'GET') {
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "SELECT c.id_client, c.nom_client, c.nom_representant, c.telephone_client,
                       c.email_client, c.adresse_client, s.libelle_secteur AS secteur,
                       perm.numero_permis, perm.date_expiration,
                       {$statutExpr} AS statut_permis
                FROM client c
                INNER JOIN secteur_activite s ON s.id_secteur = c.id_secteur
                LEFT JOIN (
                    SELECT p.*, ROW_NUMBER() OVER (PARTITION BY p.id_client ORDER BY p.date_expiration DESC) AS rn
                    FROM permis p
                ) perm ON perm.id_client = c.id_client AND perm.rn = 1";

        $params = [];
        if ($q !== '') {
            $sql .= " WHERE c.nom_client LIKE :q OR c.telephone_client LIKE :q OR s.libelle_secteur LIKE :q";
            $params['q'] = "%{$q}%";
        }
        $sql .= " ORDER BY c.nom_client ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clients = array_map(function ($c) {
            return [
                'id' => (int) $c['id_client'],
                'nom' => $c['nom_client'],
                'representant' => $c['nom_representant'],
                'telephone' => $c['telephone_client'],
                'email' => $c['email_client'],
                'adresse' => $c['adresse_client'],
                'secteur' => $c['secteur'],
                'permis' => $c['numero_permis'],
                'statut_permis' => $c['statut_permis'] ?? null,
            ];
        }, $stmt->fetchAll());

        $secteurs = $pdo->query("SELECT id_secteur, libelle_secteur FROM secteur_activite ORDER BY libelle_secteur")->fetchAll();

        json_out(['clients' => $clients, 'secteurs' => $secteurs]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $nom = trim((string) ($body['nom_client'] ?? ''));
        $representant = trim((string) ($body['nom_representant'] ?? ''));
        $cni = trim((string) ($body['cni_representant'] ?? ''));
        $telephone = trim((string) ($body['telephone_client'] ?? ''));
        $adresse = trim((string) ($body['adresse_client'] ?? ''));
        $email = trim((string) ($body['email_client'] ?? ''));
        $idSecteur = (int) ($body['id_secteur'] ?? 0);

        if ($nom === '' || $representant === '' || $cni === '' || $idSecteur <= 0) {
            json_out(['error' => 'Raison sociale, représentant, CNI et secteur sont obligatoires'], 422);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO client (nom_client, nom_representant, cni_representant, telephone_client, adresse_client, email_client, id_secteur)
             VALUES (:nom, :representant, :cni, :telephone, :adresse, :email, :secteur)"
        );
        try {
            $stmt->execute([
                'nom' => $nom,
                'representant' => $representant,
                'cni' => $cni,
                'telephone' => $telephone ?: null,
                'adresse' => $adresse ?: null,
                'email' => $email ?: null,
                'secteur' => $idSecteur,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                json_out(['error' => 'Ce numéro de CNI est déjà enregistré pour un autre client'], 409);
            }
            throw $e;
        }

        json_out(['ok' => true, 'id_client' => (int) $pdo->lastInsertId()], 201);
    }

    json_out(['error' => 'Méthode non supportée'], 405);
} catch (Throwable $e) {
    handle_error($e);
}
