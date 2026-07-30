<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();
    $statutExpr = permitStatusExpr('p');

    if ($method === 'GET') {
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "SELECT p.id_permis, p.numero_permis, p.region, p.departement, p.arrondissement,
                       p.foret_concernee, p.superficie_ha, p.date_delivrance, p.date_expiration,
                       {$statutExpr} AS statut_permis,
                       DATEDIFF(p.date_expiration, CURDATE()) AS jours_restants,
                       c.id_client, c.nom_client AS client
                FROM permis p
                INNER JOIN client c ON c.id_client = p.id_client";
        $params = [];
        if ($q !== '') {
            $sql .= " WHERE p.numero_permis LIKE :q OR c.nom_client LIKE :q OR p.region LIKE :q";
            $params['q'] = "%{$q}%";
        }
        $sql .= " ORDER BY p.date_expiration ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $permis = array_map(function ($p) {
            return [
                'id' => (int) $p['id_permis'],
                'numero_permis' => $p['numero_permis'],
                'client' => $p['client'],
                'id_client' => (int) $p['id_client'],
                'region' => $p['region'],
                'departement' => $p['departement'],
                'arrondissement' => $p['arrondissement'],
                'foret_concernee' => $p['foret_concernee'],
                'superficie_ha' => $p['superficie_ha'] !== null ? (float) $p['superficie_ha'] : null,
                'date_delivrance' => $p['date_delivrance'],
                'date_expiration' => $p['date_expiration'],
                'statut' => $p['statut_permis'],
                'jours_restants' => (int) $p['jours_restants'],
            ];
        }, $stmt->fetchAll());

        $clients = $pdo->query("SELECT id_client, nom_client FROM client ORDER BY nom_client")->fetchAll();

        json_out(['permis' => $permis, 'clients' => $clients]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $idClient = (int) ($body['id_client'] ?? 0);
        $numero = trim((string) ($body['numero_permis'] ?? ''));
        $region = trim((string) ($body['region'] ?? ''));
        $departement = trim((string) ($body['departement'] ?? ''));
        $arrondissement = trim((string) ($body['arrondissement'] ?? ''));
        $foret = trim((string) ($body['foret_concernee'] ?? ''));
        $superficie = isset($body['superficie_ha']) && $body['superficie_ha'] !== '' ? (float) $body['superficie_ha'] : null;
        $dateDelivrance = (string) ($body['date_delivrance'] ?? '');
        $dateExpiration = (string) ($body['date_expiration'] ?? '');

        if ($idClient <= 0 || $numero === '' || $region === '' || $departement === '' || $arrondissement === '' || $dateDelivrance === '' || $dateExpiration === '') {
            json_out(['error' => 'Client, numéro, région, département, arrondissement et dates sont obligatoires'], 422);
        }
        if ($dateExpiration <= $dateDelivrance) {
            json_out(['error' => "La date d'expiration doit être postérieure à la date de délivrance"], 422);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO permis (numero_permis, region, departement, arrondissement, foret_concernee, superficie_ha, date_delivrance, date_expiration, id_client)
             VALUES (:numero, :region, :departement, :arrondissement, :foret, :superficie, :delivrance, :expiration, :client)"
        );
        try {
            $stmt->execute([
                'numero' => $numero,
                'region' => $region,
                'departement' => $departement,
                'arrondissement' => $arrondissement,
                'foret' => $foret ?: null,
                'superficie' => $superficie,
                'delivrance' => $dateDelivrance,
                'expiration' => $dateExpiration,
                'client' => $idClient,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                json_out(['error' => 'Ce numéro de permis existe déjà'], 409);
            }
            throw $e;
        }

        json_out(['ok' => true, 'id_permis' => (int) $pdo->lastInsertId()], 201);
    }

    if ($method === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($body['id_permis'] ?? 0);
        $statut = (string) ($body['statut_permis'] ?? '');

        if ($id <= 0 || !in_array($statut, ['valide', 'suspendu'], true)) {
            json_out(['error' => 'Permis et statut (valide ou suspendu) requis'], 422);
        }

        $pdo->prepare("UPDATE permis SET statut_permis = :statut WHERE id_permis = :id")
            ->execute(['statut' => $statut, 'id' => $id]);

        json_out(['ok' => true]);
    }

    json_out(['error' => 'Méthode non supportée'], 405);
} catch (Throwable $e) {
    handle_error($e);
}
