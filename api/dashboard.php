<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
requireStaff();

try {
    $pdo = db();

    // ---- KPI 1 : contrats actifs ----
    $contratsActifs = (int) $pdo->query(
        "SELECT COUNT(*) FROM contrat WHERE statut_contrat = 'actif'"
    )->fetchColumn();

    // ---- KPI 2 : disponibilite du parc ----
    $totalEngins = (int) $pdo->query("SELECT COUNT(*) FROM engin")->fetchColumn();
    $enginsDispo = (int) $pdo->query(
        "SELECT COUNT(*) FROM engin WHERE disponibilite = 'disponible'"
    )->fetchColumn();
    $dispoPct = $totalEngins > 0 ? round(($enginsDispo / $totalEngins) * 100) : 0;

    // ---- KPI 3 : permis expirant sous 30 jours (statut recalcule, pas la colonne figee) ----
    $statutExpr = permitStatusExpr('p');
    $permisBientot = (int) $pdo->query(
        "SELECT COUNT(*) FROM permis p
         WHERE {$statutExpr} = 'valide'
           AND p.date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
    )->fetchColumn();

    // ---- KPI 4 : total impaye sur les factures ----
    $impaye = $pdo->query(
        "SELECT COALESCE(SUM(f.montant_ttc - COALESCE(p.total_paye, 0)), 0) AS reste
         FROM facture f
         LEFT JOIN (
             SELECT id_facture, SUM(montant_paye) AS total_paye
             FROM paiement GROUP BY id_facture
         ) p ON p.id_facture = f.id_facture
         WHERE f.statut_paiement IN ('impaye','partiel','en_retard')"
    )->fetchColumn();

    // ---- Graphique : contrats par mois (6 derniers mois glissants) ----
    $chartRows = $pdo->query(
        "SELECT DATE_FORMAT(date_effet, '%Y-%m') AS periode,
                COUNT(*) AS nb
         FROM contrat
         WHERE date_effet >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
         GROUP BY periode
         ORDER BY periode"
    )->fetchAll();

    $moisLabels = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Juin',
                   '07'=>'Juil','08'=>'Août','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
    $chart = [];
    $cursor = new DateTime('first day of -5 month');
    for ($i = 0; $i < 6; $i++) {
        $key = $cursor->format('Y-m');
        $match = array_values(array_filter($chartRows, fn($r) => $r['periode'] === $key));
        $chart[] = [
            'mois' => $moisLabels[$cursor->format('m')],
            'valeur' => $match ? (int) $match[0]['nb'] : 0,
        ];
        $cursor->modify('+1 month');
    }

    // ---- Permis a renouveler (uniquement ceux realistement proches de l'echeance) ----
    $permis = $pdo->query(
        "SELECT c.nom_client AS client, p.numero_permis, p.date_expiration,
                DATEDIFF(p.date_expiration, CURDATE()) AS jours_restants
         FROM permis p
         INNER JOIN client c ON c.id_client = p.id_client
         WHERE {$statutExpr} = 'valide'
           AND p.date_expiration <= DATE_ADD(CURDATE(), INTERVAL 180 DAY)
         ORDER BY p.date_expiration ASC
         LIMIT 5"
    )->fetchAll();

    // ---- Derniers contrats ----
    $contrats = $pdo->query(
        "SELECT ct.id_contrat, ct.date_signature, ct.date_effet, ct.statut_contrat,
                c.nom_client AS client,
                GROUP_CONCAT(CONCAT(e.type_engin, ' ', e.modele_engin) SEPARATOR ', ') AS engins
         FROM contrat ct
         INNER JOIN client c ON c.id_client = ct.id_client
         LEFT JOIN contrat_engin ce ON ce.id_contrat = ct.id_contrat
         LEFT JOIN engin e ON e.id_engin = ce.id_engin
         GROUP BY ct.id_contrat
         ORDER BY ct.date_effet DESC
         LIMIT 6"
    )->fetchAll();

    $contratsOut = array_map(function ($c) {
        return [
            'reference' => sprintf('CT-%s-%03d', substr($c['date_effet'], 0, 4), (int) $c['id_contrat']),
            'client' => $c['client'],
            'engin' => $c['engins'] ?: '—',
            'date_effet' => (new DateTime($c['date_effet']))->format('d/m/Y'),
            'statut' => $c['statut_contrat'],
        ];
    }, $contrats);

    $permisOut = array_map(function ($p) {
        return [
            'client' => $p['client'],
            'numero_permis' => $p['numero_permis'],
            'jours_restants' => (int) $p['jours_restants'],
            'expiration' => (new DateTime($p['date_expiration']))->format('d/m'),
        ];
    }, $permis);

    json_out([
        'kpis' => [
            'contrats_actifs' => $contratsActifs,
            'disponibilite_pct' => $dispoPct,
            'permis_bientot_expires' => $permisBientot,
            'factures_impayees' => (float) $impaye,
        ],
        'chart' => $chart,
        'permis_a_renouveler' => $permisOut,
        'derniers_contrats' => $contratsOut,
    ]);
} catch (Throwable $e) {
    handle_error($e);
}
