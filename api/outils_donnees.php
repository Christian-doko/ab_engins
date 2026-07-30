<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/**
 * Outils d'accès aux données pour l'assistant IA (lecture seule).
 * Chaque outil correspond à une requête SQL paramétrée sur la base ab_engins.
 */

function runTool(string $name, array $input): array {
    $pdo = db();
    $statutExpr = permitStatusExpr('p');

    switch ($name) {
        case 'stats_generales':
            $contratsActifs = (int) $pdo->query("SELECT COUNT(*) FROM contrat WHERE statut_contrat = 'actif'")->fetchColumn();
            $totalEngins = (int) $pdo->query("SELECT COUNT(*) FROM engin")->fetchColumn();
            $enginsDispo = (int) $pdo->query("SELECT COUNT(*) FROM engin WHERE disponibilite = 'disponible'")->fetchColumn();
            $permisBientot = (int) $pdo->query(
                "SELECT COUNT(*) FROM permis p
                 WHERE {$statutExpr} = 'valide'
                   AND p.date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            )->fetchColumn();
            $impaye = (float) $pdo->query(
                "SELECT COALESCE(SUM(f.montant_ttc - COALESCE(pa.total_paye, 0)), 0)
                 FROM facture f
                 LEFT JOIN (SELECT id_facture, SUM(montant_paye) AS total_paye FROM paiement GROUP BY id_facture) pa
                   ON pa.id_facture = f.id_facture
                 WHERE f.statut_paiement IN ('impaye','partiel','en_retard')"
            )->fetchColumn();
            return [
                'contrats_actifs' => $contratsActifs,
                'engins_total' => $totalEngins,
                'engins_disponibles' => $enginsDispo,
                'permis_expirant_30j' => $permisBientot,
                'total_impaye_fcfa' => $impaye,
            ];

        case 'lister_clients':
            $sql = "SELECT c.nom_client, c.nom_representant, c.telephone_client, c.email_client, s.libelle_secteur
                    FROM client c INNER JOIN secteur_activite s ON s.id_secteur = c.id_secteur";
            $params = [];
            if (!empty($input['recherche'])) {
                $sql .= " WHERE c.nom_client LIKE ?";
                $params[] = '%' . $input['recherche'] . '%';
            }
            $st = $pdo->prepare($sql . " ORDER BY c.nom_client LIMIT 50");
            $st->execute($params);
            return $st->fetchAll();

        case 'lister_permis':
            $sql = "SELECT p.numero_permis, c.nom_client, p.region, p.date_expiration,
                           DATEDIFF(p.date_expiration, CURDATE()) AS jours_restants,
                           {$statutExpr} AS statut
                    FROM permis p INNER JOIN client c ON c.id_client = p.id_client WHERE 1=1";
            $params = [];
            if (!empty($input['statut'])) {
                $sql .= " AND {$statutExpr} = ?";
                $params[] = $input['statut'];
            }
            if (!empty($input['expire_avant_jours'])) {
                $sql .= " AND p.date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)";
                $params[] = (int) $input['expire_avant_jours'];
            }
            $st = $pdo->prepare($sql . " ORDER BY p.date_expiration ASC LIMIT 50");
            $st->execute($params);
            return $st->fetchAll();

        case 'lister_contrats':
            $sql = "SELECT ct.id_contrat, c.nom_client, ct.date_effet, ct.date_fin_prevue,
                           ct.montant_ht, ct.statut_contrat,
                           GROUP_CONCAT(CONCAT(e.type_engin, ' ', COALESCE(e.modele_engin, '')) SEPARATOR ', ') AS engins
                    FROM contrat ct
                    INNER JOIN client c ON c.id_client = ct.id_client
                    LEFT JOIN contrat_engin ce ON ce.id_contrat = ct.id_contrat
                    LEFT JOIN engin e ON e.id_engin = ce.id_engin
                    WHERE 1=1";
            $params = [];
            if (!empty($input['statut'])) {
                $sql .= " AND ct.statut_contrat = ?";
                $params[] = $input['statut'];
            }
            if (!empty($input['client'])) {
                $sql .= " AND c.nom_client LIKE ?";
                $params[] = '%' . $input['client'] . '%';
            }
            $st = $pdo->prepare($sql . " GROUP BY ct.id_contrat ORDER BY ct.date_effet DESC LIMIT 50");
            $st->execute($params);
            return $st->fetchAll();

        case 'lister_factures':
            $sql = "SELECT f.numero_facture, c.nom_client, f.date_facture, f.montant_ttc,
                           COALESCE(pa.total_paye, 0) AS total_paye,
                           (f.montant_ttc - COALESCE(pa.total_paye, 0)) AS reste_a_payer,
                           f.statut_paiement
                    FROM facture f
                    INNER JOIN contrat ct ON ct.id_contrat = f.id_contrat
                    INNER JOIN client c ON c.id_client = ct.id_client
                    LEFT JOIN (SELECT id_facture, SUM(montant_paye) AS total_paye FROM paiement GROUP BY id_facture) pa
                      ON pa.id_facture = f.id_facture
                    WHERE 1=1";
            $params = [];
            if (!empty($input['statut_paiement'])) {
                $sql .= " AND f.statut_paiement = ?";
                $params[] = $input['statut_paiement'];
            }
            if (!empty($input['client'])) {
                $sql .= " AND c.nom_client LIKE ?";
                $params[] = '%' . $input['client'] . '%';
            }
            $st = $pdo->prepare($sql . " ORDER BY f.date_facture DESC LIMIT 50");
            $st->execute($params);
            return $st->fetchAll();

        case 'lister_engins':
            $sql = "SELECT code_engin, type_engin, modele_engin, etat_engin, disponibilite FROM engin WHERE 1=1";
            $params = [];
            if (!empty($input['disponibilite'])) {
                $sql .= " AND disponibilite = ?";
                $params[] = $input['disponibilite'];
            }
            if (!empty($input['etat'])) {
                $sql .= " AND etat_engin = ?";
                $params[] = $input['etat'];
            }
            $st = $pdo->prepare($sql . " ORDER BY type_engin, code_engin LIMIT 50");
            $st->execute($params);
            return $st->fetchAll();

        case 'lister_interventions':
            $sql = "SELECT a.date_intervention, c.nom_client,
                           CONCAT(e.type_engin, ' ', COALESCE(e.modele_engin, '')) AS engin,
                           a.motif_intervention, a.description_probleme, a.resolution,
                           a.statut_intervention,
                           CONCAT(emp.prenom_employe, ' ', emp.nom_employe) AS technicien
                    FROM assistance a
                    INNER JOIN contrat_engin ce ON ce.id_contrat_engin = a.id_contrat_engin
                    INNER JOIN contrat ct ON ct.id_contrat = ce.id_contrat
                    INNER JOIN client c ON c.id_client = ct.id_client
                    INNER JOIN engin e ON e.id_engin = ce.id_engin
                    INNER JOIN employe emp ON emp.id_employe = a.id_employe
                    WHERE 1=1";
            $params = [];
            if (!empty($input['statut'])) {
                $sql .= " AND a.statut_intervention = ?";
                $params[] = $input['statut'];
            }
            $st = $pdo->prepare($sql . " ORDER BY a.date_intervention DESC LIMIT 50");
            $st->execute($params);
            return $st->fetchAll();

        default:
            return ['erreur' => "Outil inconnu : {$name}"];
    }
}
