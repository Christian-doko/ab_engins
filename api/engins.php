<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
requireStaff();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    if ($method === 'GET') {
        $dateEffet = $_GET['date_effet'] ?? null;
        $dureeJours = isset($_GET['duree_jours']) ? (int) $_GET['duree_jours'] : null;

        if ($dateEffet && $dureeJours && $dureeJours > 0) {
            // Disponibilité pour une période donnée : même règle que le trigger
            // trg_check_chevauchement_engin (contrat_engin.date_mise_disposition
            // vs contrat.date_fin_prevue, uniquement pour les contrats actifs).
            $stmt = $pdo->prepare(
                "SELECT e.id_engin, e.code_engin, e.type_engin, e.modele_engin, e.numero_serie, e.etat_engin, e.disponibilite,
                        NOT EXISTS (
                            SELECT 1 FROM contrat_engin ce
                            INNER JOIN contrat c ON c.id_contrat = ce.id_contrat
                            WHERE ce.id_engin = e.id_engin
                              AND c.statut_contrat = 'actif'
                              AND ce.date_mise_disposition < DATE_ADD(:date_effet, INTERVAL :duree DAY)
                              AND c.date_fin_prevue > :date_effet2
                        ) AS disponible_periode
                 FROM engin e
                 WHERE e.disponibilite != 'maintenance'
                 ORDER BY e.type_engin, e.code_engin"
            );
            $stmt->execute([
                'date_effet' => $dateEffet,
                'duree' => $dureeJours,
                'date_effet2' => $dateEffet,
            ]);
        } else {
            $stmt = $pdo->query(
                "SELECT id_engin, code_engin, type_engin, modele_engin, numero_serie, etat_engin, disponibilite,
                        (disponibilite = 'disponible') AS disponible_periode
                 FROM engin
                 ORDER BY type_engin, code_engin"
            );
        }

        $engins = array_map(function ($e) {
            return [
                'id' => (int) $e['id_engin'],
                'code' => $e['code_engin'],
                'type' => $e['type_engin'],
                'modele' => $e['modele_engin'],
                'numero_serie' => $e['numero_serie'],
                'etat' => $e['etat_engin'],
                'disponibilite' => $e['disponibilite'],
                'disponible_periode' => (bool) $e['disponible_periode'],
            ];
        }, $stmt->fetchAll());

        json_out(['engins' => $engins]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $code = trim((string) ($body['code_engin'] ?? ''));
        $type = trim((string) ($body['type_engin'] ?? ''));
        $modele = trim((string) ($body['modele_engin'] ?? ''));
        $numeroSerie = trim((string) ($body['numero_serie'] ?? ''));
        $etat = (string) ($body['etat_engin'] ?? 'bon');

        if ($code === '' || $type === '') {
            json_out(['error' => 'Code interne et type d\'engin sont obligatoires'], 422);
        }
        if (!in_array($etat, ['bon', 'moyen', 'defectueux'], true)) {
            json_out(['error' => 'État invalide'], 422);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO engin (code_engin, type_engin, modele_engin, numero_serie, etat_engin, disponibilite)
             VALUES (:code, :type, :modele, :serie, :etat, 'disponible')"
        );
        try {
            $stmt->execute([
                'code' => $code,
                'type' => $type,
                'modele' => $modele ?: null,
                'serie' => $numeroSerie ?: null,
                'etat' => $etat,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                json_out(['error' => 'Ce code interne ou ce numéro de série est déjà utilisé'], 409);
            }
            throw $e;
        }

        json_out(['ok' => true, 'id_engin' => (int) $pdo->lastInsertId()], 201);
    }

    if ($method === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($body['id_engin'] ?? 0);
        $disponibilite = (string) ($body['disponibilite'] ?? '');

        if ($id <= 0 || !in_array($disponibilite, ['disponible', 'loue', 'maintenance'], true)) {
            json_out(['error' => 'Engin et disponibilité (disponible, loue ou maintenance) requis'], 422);
        }

        $pdo->prepare("UPDATE engin SET disponibilite = :d WHERE id_engin = :id")
            ->execute(['d' => $disponibilite, 'id' => $id]);

        json_out(['ok' => true]);
    }

    json_out(['error' => 'Méthode non supportée'], 405);
} catch (Throwable $e) {
    handle_error($e);
}
