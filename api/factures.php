<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
requireStaff();

$method = $_SERVER['REQUEST_METHOD'];

function recomputeStatutPaiement(PDO $pdo, int $idFacture): void {
    $stmt = $pdo->prepare(
        "SELECT f.montant_ttc, f.date_facture, COALESCE(SUM(p.montant_paye), 0) AS total_paye
         FROM facture f
         LEFT JOIN paiement p ON p.id_facture = f.id_facture
         WHERE f.id_facture = :id
         GROUP BY f.id_facture"
    );
    $stmt->execute(['id' => $idFacture]);
    $row = $stmt->fetch();
    if (!$row) return;

    $ttc = (float) $row['montant_ttc'];
    $paye = (float) $row['total_paye'];
    $joursDepuisFacture = (int) ((strtotime('today') - strtotime($row['date_facture'])) / 86400);

    if ($paye >= $ttc) {
        $statut = 'paye';
    } elseif ($paye > 0) {
        $statut = 'partiel';
    } elseif ($joursDepuisFacture > 30) {
        $statut = 'en_retard';
    } else {
        $statut = 'impaye';
    }

    $pdo->prepare("UPDATE facture SET statut_paiement = :s WHERE id_facture = :id")
        ->execute(['s' => $statut, 'id' => $idFacture]);
}

try {
    $pdo = db();

    if ($method === 'GET' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $pdo->prepare(
            "SELECT f.*, ct.id_contrat, c.nom_client AS client
             FROM facture f
             INNER JOIN contrat ct ON ct.id_contrat = f.id_contrat
             INNER JOIN client c ON c.id_client = ct.id_client
             WHERE f.id_facture = :id"
        );
        $stmt->execute(['id' => $id]);
        $facture = $stmt->fetch();
        if (!$facture) json_out(['error' => 'Facture introuvable'], 404);

        $lignes = $pdo->prepare("SELECT id_ligne, designation_ligne, quantite, prix_unitaire, montant_ligne FROM ligne_facture WHERE id_facture = :id ORDER BY id_ligne");
        $lignes->execute(['id' => $id]);

        $paiements = $pdo->prepare("SELECT id_paiement, date_paiement, montant_paye, mode_paiement FROM paiement WHERE id_facture = :id ORDER BY date_paiement");
        $paiements->execute(['id' => $id]);
        $paiementsRows = $paiements->fetchAll();

        $totalPaye = array_sum(array_column($paiementsRows, 'montant_paye'));

        json_out([
            'facture' => [
                'id' => (int) $facture['id_facture'],
                'numero_facture' => $facture['numero_facture'],
                'date_facture' => $facture['date_facture'],
                'client' => $facture['client'],
                'id_contrat' => (int) $facture['id_contrat'],
                'montant_ht' => (float) $facture['montant_ht_facture'],
                'taux_tva' => (float) $facture['taux_tva'],
                'montant_ttc' => (float) $facture['montant_ttc'],
                'statut_paiement' => $facture['statut_paiement'],
                'total_paye' => (float) $totalPaye,
                'reste' => (float) $facture['montant_ttc'] - (float) $totalPaye,
            ],
            'lignes' => $lignes->fetchAll(),
            'paiements' => $paiementsRows,
        ]);
    }

    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT f.id_facture, f.numero_facture, f.date_facture, f.montant_ttc, f.statut_paiement,
                    c.nom_client AS client,
                    f.montant_ttc - COALESCE((SELECT SUM(p.montant_paye) FROM paiement p WHERE p.id_facture = f.id_facture), 0) AS reste
             FROM facture f
             INNER JOIN contrat ct ON ct.id_contrat = f.id_contrat
             INNER JOIN client c ON c.id_client = ct.id_client
             ORDER BY f.date_facture DESC"
        );
        $factures = array_map(function ($f) {
            return [
                'id' => (int) $f['id_facture'],
                'numero_facture' => $f['numero_facture'],
                'date_facture' => $f['date_facture'],
                'client' => $f['client'],
                'montant_ttc' => (float) $f['montant_ttc'],
                'reste' => (float) $f['reste'],
                'statut_paiement' => $f['statut_paiement'],
            ];
        }, $stmt->fetchAll());

        // Contrats sans facture (candidats pour "nouvelle facture").
        $contratsSansFacture = $pdo->query(
            "SELECT ct.id_contrat, c.nom_client AS client, ct.date_effet
             FROM contrat ct
             INNER JOIN client c ON c.id_client = ct.id_client
             LEFT JOIN facture f ON f.id_contrat = ct.id_contrat
             WHERE f.id_facture IS NULL
             ORDER BY ct.date_effet DESC"
        )->fetchAll();

        json_out(['factures' => $factures, 'contrats_disponibles' => $contratsSansFacture]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = (string) ($body['action'] ?? 'create');

        if ($action === 'paiement') {
            $idFacture = (int) ($body['id_facture'] ?? 0);
            $montant = (float) ($body['montant_paye'] ?? 0);
            $mode = (string) ($body['mode_paiement'] ?? '');
            $datePaiement = (string) ($body['date_paiement'] ?? date('Y-m-d'));

            if ($idFacture <= 0 || $montant <= 0 || !in_array($mode, ['especes', 'virement', 'mobile_money', 'cheque'], true)) {
                json_out(['error' => 'Facture, montant et mode de paiement valides sont obligatoires'], 422);
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO paiement (date_paiement, montant_paye, mode_paiement, id_facture) VALUES (:date, :montant, :mode, :id)")
                    ->execute(['date' => $datePaiement, 'montant' => $montant, 'mode' => $mode, 'id' => $idFacture]);
                recomputeStatutPaiement($pdo, $idFacture);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            json_out(['ok' => true], 201);
        }

        // Creation d'une nouvelle facture avec ses lignes.
        $idContrat = (int) ($body['id_contrat'] ?? 0);
        $dateFacture = (string) ($body['date_facture'] ?? date('Y-m-d'));
        $tauxTva = isset($body['taux_tva']) && $body['taux_tva'] !== '' ? (float) $body['taux_tva'] : 19.25;
        $lignes = (array) ($body['lignes'] ?? []);

        if ($idContrat <= 0 || empty($lignes)) {
            json_out(['error' => 'Contrat et au moins une ligne de facturation sont obligatoires'], 422);
        }

        $montantHt = 0.0;
        $lignesValidees = [];
        foreach ($lignes as $l) {
            $designation = trim((string) ($l['designation_ligne'] ?? ''));
            $quantite = (float) ($l['quantite'] ?? 0);
            $prixUnitaire = (float) ($l['prix_unitaire'] ?? 0);
            if ($designation === '' || $quantite <= 0 || $prixUnitaire <= 0) {
                json_out(['error' => 'Chaque ligne doit avoir une désignation, une quantité et un prix unitaire valides'], 422);
            }
            $montantHt += $quantite * $prixUnitaire;
            $lignesValidees[] = [$designation, $quantite, $prixUnitaire];
        }

        $numeroFacture = 'FA-' . substr($dateFacture, 0, 4) . '-' . str_pad((string) $idContrat, 3, '0', STR_PAD_LEFT) . '-' . substr((string) time(), -4);

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO facture (numero_facture, date_facture, montant_ht_facture, taux_tva, id_contrat)
                 VALUES (:numero, :date, :montant, :taux, :contrat)"
            )->execute([
                'numero' => $numeroFacture,
                'date' => $dateFacture,
                'montant' => $montantHt,
                'taux' => $tauxTva,
                'contrat' => $idContrat,
            ]);
            $idFacture = (int) $pdo->lastInsertId();

            $stmtLigne = $pdo->prepare(
                "INSERT INTO ligne_facture (designation_ligne, quantite, prix_unitaire, id_facture) VALUES (:d, :q, :p, :f)"
            );
            foreach ($lignesValidees as [$designation, $quantite, $prixUnitaire]) {
                $stmtLigne->execute(['d' => $designation, 'q' => $quantite, 'p' => $prixUnitaire, 'f' => $idFacture]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        json_out(['ok' => true, 'id_facture' => $idFacture], 201);
    }

    json_out(['error' => 'Méthode non supportée'], 405);
} catch (Throwable $e) {
    handle_error($e);
}
