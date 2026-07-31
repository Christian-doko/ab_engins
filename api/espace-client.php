<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/**
 * Espace client — données du client connecté uniquement.
 * Chaque requête est filtrée par l'id_client stocké en session à la connexion :
 * un compte client ne peut jamais voir les données d'un autre client.
 */

$user = requireAuth();
if (($user['role'] ?? '') !== 'client' || empty($user['id_client'])) {
    json_out(['error' => 'Accès réservé aux comptes clients'], 403);
}
$idClient = (int) $user['id_client'];

try {
    $pdo = db();
    $statutExpr = permitStatusExpr('p');

    $client = $pdo->prepare(
        "SELECT c.nom_client, c.nom_representant, c.telephone_client, c.email_client,
                c.adresse_client, s.libelle_secteur
         FROM client c
         INNER JOIN secteur_activite s ON s.id_secteur = c.id_secteur
         WHERE c.id_client = :id"
    );
    $client->execute(['id' => $idClient]);
    $clientRow = $client->fetch();
    if (!$clientRow) {
        json_out(['error' => 'Client introuvable'], 404);
    }

    $contrats = $pdo->prepare(
        "SELECT ct.id_contrat, ct.date_effet, ct.date_fin_prevue, ct.montant_ht, ct.statut_contrat,
                GROUP_CONCAT(CONCAT(e.type_engin, ' ', COALESCE(e.modele_engin, '')) SEPARATOR ', ') AS engins
         FROM contrat ct
         LEFT JOIN contrat_engin ce ON ce.id_contrat = ct.id_contrat
         LEFT JOIN engin e ON e.id_engin = ce.id_engin
         WHERE ct.id_client = :id
         GROUP BY ct.id_contrat
         ORDER BY ct.date_effet DESC"
    );
    $contrats->execute(['id' => $idClient]);

    $factures = $pdo->prepare(
        "SELECT f.numero_facture, f.date_facture, f.montant_ttc, f.statut_paiement,
                COALESCE(pa.total_paye, 0) AS total_paye,
                (f.montant_ttc - COALESCE(pa.total_paye, 0)) AS reste_a_payer
         FROM facture f
         INNER JOIN contrat ct ON ct.id_contrat = f.id_contrat
         LEFT JOIN (SELECT id_facture, SUM(montant_paye) AS total_paye FROM paiement GROUP BY id_facture) pa
           ON pa.id_facture = f.id_facture
         WHERE ct.id_client = :id
         ORDER BY f.date_facture DESC"
    );
    $factures->execute(['id' => $idClient]);
    $facturesRows = $factures->fetchAll();
    $totalDu = array_sum(array_map(fn($f) => max(0.0, (float) $f['reste_a_payer']), $facturesRows));

    $permis = $pdo->prepare(
        "SELECT p.numero_permis, p.region, p.date_expiration,
                DATEDIFF(p.date_expiration, CURDATE()) AS jours_restants,
                {$statutExpr} AS statut
         FROM permis p
         WHERE p.id_client = :id
         ORDER BY p.date_expiration ASC"
    );
    $permis->execute(['id' => $idClient]);

    $interventions = $pdo->prepare(
        "SELECT a.date_intervention, a.motif_intervention, a.statut_intervention,
                CONCAT(e.type_engin, ' ', COALESCE(e.modele_engin, '')) AS engin
         FROM assistance a
         INNER JOIN contrat_engin ce ON ce.id_contrat_engin = a.id_contrat_engin
         INNER JOIN contrat ct ON ct.id_contrat = ce.id_contrat
         INNER JOIN engin e ON e.id_engin = ce.id_engin
         WHERE ct.id_client = :id
         ORDER BY a.date_intervention DESC
         LIMIT 20"
    );
    $interventions->execute(['id' => $idClient]);

    json_out([
        'client' => $clientRow,
        'contrats' => $contrats->fetchAll(),
        'factures' => $facturesRows,
        'total_du' => $totalDu,
        'permis' => $permis->fetchAll(),
        'interventions' => $interventions->fetchAll(),
    ]);
} catch (Throwable $e) {
    handle_error($e);
}
