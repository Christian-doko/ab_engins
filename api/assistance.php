<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
requireStaff();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT a.id_assistance, a.date_intervention, a.motif_intervention, a.description_probleme,
                    a.resolution, a.statut_intervention,
                    c.nom_client AS client, e.type_engin, e.modele_engin, e.code_engin,
                    CONCAT(emp.prenom_employe, ' ', emp.nom_employe) AS technicien
             FROM assistance a
             INNER JOIN contrat_engin ce ON ce.id_contrat_engin = a.id_contrat_engin
             INNER JOIN contrat ct ON ct.id_contrat = ce.id_contrat
             INNER JOIN client c ON c.id_client = ct.id_client
             INNER JOIN engin e ON e.id_engin = ce.id_engin
             INNER JOIN employe emp ON emp.id_employe = a.id_employe
             ORDER BY a.date_intervention DESC"
        );
        $interventions = array_map(function ($a) {
            return [
                'id' => (int) $a['id_assistance'],
                'date_intervention' => $a['date_intervention'],
                'client' => $a['client'],
                'engin' => trim($a['type_engin'] . ' ' . $a['modele_engin']) . ' (' . $a['code_engin'] . ')',
                'motif' => $a['motif_intervention'],
                'description' => $a['description_probleme'],
                'resolution' => $a['resolution'],
                'statut' => $a['statut_intervention'],
                'technicien' => $a['technicien'],
            ];
        }, $stmt->fetchAll());

        // Candidats pour une nouvelle intervention : engins actuellement affectés à un contrat.
        $enginesContrat = $pdo->query(
            "SELECT ce.id_contrat_engin, c.nom_client AS client, e.type_engin, e.modele_engin, e.code_engin
             FROM contrat_engin ce
             INNER JOIN contrat ct ON ct.id_contrat = ce.id_contrat
             INNER JOIN client c ON c.id_client = ct.id_client
             INNER JOIN engin e ON e.id_engin = ce.id_engin
             ORDER BY ct.date_effet DESC"
        )->fetchAll();

        $techniciens = $pdo->query(
            "SELECT id_employe, CONCAT(prenom_employe, ' ', nom_employe) AS nom, poste
             FROM employe
             WHERE poste IN ('technicien', 'mecanicien')
             ORDER BY nom"
        )->fetchAll();

        json_out(['interventions' => $interventions, 'contrats_engins' => $enginesContrat, 'techniciens' => $techniciens]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $idContratEngin = (int) ($body['id_contrat_engin'] ?? 0);
        $idEmploye = (int) ($body['id_employe'] ?? 0);
        $motif = trim((string) ($body['motif_intervention'] ?? ''));
        $description = trim((string) ($body['description_probleme'] ?? ''));

        if ($idContratEngin <= 0 || $idEmploye <= 0 || $motif === '') {
            json_out(['error' => 'Engin concerné, technicien et motif sont obligatoires'], 422);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO assistance (motif_intervention, description_probleme, id_contrat_engin, id_employe)
             VALUES (:motif, :description, :ce, :employe)"
        );
        $stmt->execute([
            'motif' => $motif,
            'description' => $description ?: null,
            'ce' => $idContratEngin,
            'employe' => $idEmploye,
        ]);

        json_out(['ok' => true, 'id_assistance' => (int) $pdo->lastInsertId()], 201);
    }

    if ($method === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($body['id_assistance'] ?? 0);
        $statut = (string) ($body['statut_intervention'] ?? '');
        $resolution = trim((string) ($body['resolution'] ?? ''));

        if ($id <= 0 || !in_array($statut, ['en_attente', 'en_cours', 'resolu'], true)) {
            json_out(['error' => 'Intervention et statut valides sont obligatoires'], 422);
        }
        if ($statut === 'resolu' && $resolution === '') {
            json_out(['error' => 'Merci de décrire la résolution avant de clore l\'intervention'], 422);
        }

        $pdo->prepare("UPDATE assistance SET statut_intervention = :s, resolution = COALESCE(NULLIF(:r, ''), resolution) WHERE id_assistance = :id")
            ->execute(['s' => $statut, 'r' => $resolution, 'id' => $id]);

        json_out(['ok' => true]);
    }

    json_out(['error' => 'Méthode non supportée'], 405);
} catch (Throwable $e) {
    handle_error($e);
}
