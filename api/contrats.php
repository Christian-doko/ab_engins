<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/migrations.php';
requireStaff();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();
    // Regle de gestion : pas de contrat sans permis (cf. sql/migration_permis_obligatoire.sql).
    ensurePermisObligatoire($pdo);

    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT ct.id_contrat, ct.date_effet, ct.duree_jours, ct.date_fin_prevue,
                    ct.statut_contrat, ct.montant_ht, c.nom_client AS client,
                    GROUP_CONCAT(CONCAT(e.type_engin, ' ', e.modele_engin) SEPARATOR ', ') AS engins
             FROM contrat ct
             INNER JOIN client c ON c.id_client = ct.id_client
             LEFT JOIN contrat_engin ce ON ce.id_contrat = ct.id_contrat
             LEFT JOIN engin e ON e.id_engin = ce.id_engin
             GROUP BY ct.id_contrat
             ORDER BY ct.date_effet DESC"
        );
        $contrats = array_map(function ($c) {
            return [
                'id' => (int) $c['id_contrat'],
                'reference' => sprintf('CT-%s-%03d', substr($c['date_effet'], 0, 4), (int) $c['id_contrat']),
                'client' => $c['client'],
                'engins' => $c['engins'] ?: '—',
                'date_effet' => $c['date_effet'],
                'date_fin_prevue' => $c['date_fin_prevue'],
                'duree_jours' => (int) $c['duree_jours'],
                'montant_ht' => (float) $c['montant_ht'],
                'statut' => $c['statut_contrat'],
            ];
        }, $stmt->fetchAll());
        json_out(['contrats' => $contrats]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $idClient = (int) ($body['id_client'] ?? 0);
        $idPermis = !empty($body['id_permis']) ? (int) $body['id_permis'] : 0;
        $dateEffet = (string) ($body['date_effet'] ?? '');
        $dureeJours = (int) ($body['duree_jours'] ?? 0);
        $dateSignature = (string) ($body['date_signature'] ?? date('Y-m-d'));
        $lieuSignature = trim((string) ($body['lieu_signature'] ?? ''));
        $taciteReconduction = !empty($body['tacite_reconduction']);
        $montantHt = (float) ($body['montant_ht'] ?? 0);
        $enginIds = array_values(array_unique(array_map('intval', (array) ($body['engins'] ?? []))));

        if ($idClient <= 0 || $dateEffet === '' || $dureeJours <= 0 || $montantHt <= 0 || empty($enginIds)) {
            json_out(['error' => 'Client, date d\'effet, durée, montant et au moins un engin sont obligatoires'], 422);
        }

        // Le client doit presenter un permis d'exploitation : on verifie ici pour
        // renvoyer un message clair (la base refuserait de toute facon via le trigger).
        if ($idPermis <= 0) {
            json_out(['error' => 'Le client doit présenter un permis d\'exploitation pour obtenir un contrat'], 422);
        }
        $stmtPermis = $pdo->prepare(
            'SELECT numero_permis, id_client, date_delivrance, date_expiration, statut_permis
             FROM permis WHERE id_permis = :id'
        );
        $stmtPermis->execute(['id' => $idPermis]);
        $permis = $stmtPermis->fetch();

        if (!$permis) {
            json_out(['error' => 'Permis introuvable'], 422);
        }
        if ((int) $permis['id_client'] !== $idClient) {
            json_out(['error' => 'Le permis sélectionné appartient à un autre client'], 422);
        }
        if ($permis['statut_permis'] === 'suspendu') {
            json_out(['error' => "Le permis {$permis['numero_permis']} est suspendu : aucun contrat ne peut être établi"], 422);
        }
        if ($dateSignature < $permis['date_delivrance'] || $dateSignature > $permis['date_expiration']) {
            $exp = (new DateTime($permis['date_expiration']))->format('d/m/Y');
            json_out(['error' => "Le permis {$permis['numero_permis']} n'est pas valide à la date de signature (expire le {$exp}). Enregistrez son renouvellement avant de créer le contrat."], 422);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO contrat (date_signature, lieu_signature, date_effet, duree_jours, tacite_reconduction, montant_ht, id_client, id_permis)
                 VALUES (:date_signature, :lieu_signature, :date_effet, :duree_jours, :tacite, :montant, :id_client, :id_permis)"
            );
            $stmt->execute([
                'date_signature' => $dateSignature,
                'lieu_signature' => $lieuSignature ?: null,
                'date_effet' => $dateEffet,
                'duree_jours' => $dureeJours,
                'tacite' => $taciteReconduction ? 1 : 0,
                'montant' => $montantHt,
                'id_client' => $idClient,
                'id_permis' => $idPermis,
            ]);
            $idContrat = (int) $pdo->lastInsertId();

            $stmtCe = $pdo->prepare(
                "INSERT INTO contrat_engin (id_contrat, id_engin, date_mise_disposition, etat_depart)
                 VALUES (:id_contrat, :id_engin, :date_effet, 'Bon état, remis au client')"
            );
            $stmtMaj = $pdo->prepare("UPDATE engin SET disponibilite = 'loue' WHERE id_engin = :id_engin");

            foreach ($enginIds as $idEngin) {
                $stmtCe->execute(['id_contrat' => $idContrat, 'id_engin' => $idEngin, 'date_effet' => $dateEffet]);
                $stmtMaj->execute(['id_engin' => $idEngin]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            // Les triggers metier remontent un SIGNAL SQLSTATE 45000 :
            // non-chevauchement des engins, et validite du permis presente.
            if ($e instanceof PDOException && $e->getCode() === '45000') {
                $message = (string) ($e->errorInfo[2] ?? '');
                if (stripos($message, 'permis') !== false) {
                    json_out(['error' => 'Permis refusé par la base : ' . $message], 422);
                }
                json_out(['error' => 'Un ou plusieurs engins sélectionnés sont déjà loués sur cette période'], 409);
            }
            throw $e;
        }

        json_out(['ok' => true, 'id_contrat' => $idContrat], 201);
    }

    json_out(['error' => 'Méthode non supportée'], 405);
} catch (Throwable $e) {
    handle_error($e);
}
