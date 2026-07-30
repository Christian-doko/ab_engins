<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
requireAuth();

/**
 * Assistant IA AB ENGINS — endpoint de chat.
 * Reçoit {messages: [{role, content}...]} et interroge l'API Claude (Anthropic)
 * avec des outils en lecture seule branchés sur la base ab_engins.
 * Implémentation en HTTP brut (cURL) : aucune dépendance Composer requise.
 */

set_time_limit(180);

// ---------------------------------------------------------------------------
// Clé API : variable d'environnement en production (Railway),
// ou fichier api/secrets.local.php en local (non versionné) qui retourne la clé.
// ---------------------------------------------------------------------------
function anthropicApiKey(): string {
    $key = getenv('ANTHROPIC_API_KEY') ?: '';
    if ($key === '' && is_file(__DIR__ . '/secrets.local.php')) {
        $key = (string) (require __DIR__ . '/secrets.local.php');
    }
    if ($key === '') {
        json_out(['error' => "Clé API Anthropic absente : définir ANTHROPIC_API_KEY ou créer api/secrets.local.php"], 500);
    }
    return $key;
}

// ---------------------------------------------------------------------------
// Outils (lecture seule)
// ---------------------------------------------------------------------------
function agentTools(): array {
    return [
    [
        'name' => 'stats_generales',
        'description' => "Indicateurs globaux du parc : contrats actifs, disponibilité des engins, permis expirant sous 30 jours, total impayé des factures. Appeler cet outil pour toute question de vue d'ensemble.",
        'input_schema' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
    ],
    [
        'name' => 'lister_clients',
        'description' => "Liste les clients (nom, représentant, contact, secteur d'activité). Filtrable par texte de recherche sur le nom.",
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'recherche' => ['type' => 'string', 'description' => 'Filtre texte appliqué au nom du client (optionnel)'],
            ],
            'required' => [],
        ],
    ],
    [
        'name' => 'lister_permis',
        'description' => "Liste les permis d'exploitation avec leur client, date d'expiration et statut effectif (valide/expire/suspendu, recalculé à partir de la date). Utiliser expire_avant_jours pour trouver les permis à renouveler bientôt.",
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'statut' => ['type' => 'string', 'enum' => ['valide', 'expire', 'suspendu'], 'description' => 'Filtrer par statut effectif (optionnel)'],
                'expire_avant_jours' => ['type' => 'integer', 'description' => "Ne garder que les permis expirant dans les N prochains jours (optionnel)"],
            ],
            'required' => [],
        ],
    ],
    [
        'name' => 'lister_contrats',
        'description' => "Liste les contrats de location : client, engins associés, dates d'effet et de fin prévue, montant HT, statut. Filtrable par statut et par nom de client.",
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'statut' => ['type' => 'string', 'enum' => ['actif', 'termine', 'resilie', 'renouvele'], 'description' => 'Filtrer par statut (optionnel)'],
                'client' => ['type' => 'string', 'description' => 'Filtre texte sur le nom du client (optionnel)'],
            ],
            'required' => [],
        ],
    ],
    [
        'name' => 'lister_factures',
        'description' => "Liste les factures avec client, montant TTC, total payé, reste à payer et statut de paiement (paye/partiel/en_retard/impaye). Montants en FCFA.",
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'statut_paiement' => ['type' => 'string', 'enum' => ['paye', 'partiel', 'en_retard', 'impaye'], 'description' => 'Filtrer par statut de paiement (optionnel)'],
                'client' => ['type' => 'string', 'description' => 'Filtre texte sur le nom du client (optionnel)'],
            ],
            'required' => [],
        ],
    ],
    [
        'name' => 'lister_engins',
        'description' => "Liste les engins du parc : code, type, modèle, état (bon/moyen/defectueux) et disponibilité (disponible/loue/maintenance).",
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'disponibilite' => ['type' => 'string', 'enum' => ['disponible', 'loue', 'maintenance'], 'description' => 'Filtrer par disponibilité (optionnel)'],
                'etat' => ['type' => 'string', 'enum' => ['bon', 'moyen', 'defectueux'], 'description' => "Filtrer par état (optionnel)"],
            ],
            'required' => [],
        ],
    ],
    [
        'name' => 'lister_interventions',
        'description' => "Liste les interventions d'assistance technique : client, engin, motif, technicien, statut (en_attente/en_cours/resolu).",
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'statut' => ['type' => 'string', 'enum' => ['en_attente', 'en_cours', 'resolu'], 'description' => 'Filtrer par statut (optionnel)'],
            ],
            'required' => [],
        ],
    ],
    ];
}

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

// ---------------------------------------------------------------------------
// Appel HTTP à l'API Anthropic
// ---------------------------------------------------------------------------
function anthropicRequest(array $payload): array {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 150,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . anthropicApiKey(),
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        json_out(['error' => "Connexion à l'API Claude impossible : {$err}"], 502);
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);
    if ($status >= 400 || !is_array($data)) {
        $msg = $data['error']['message'] ?? "Erreur API Claude (HTTP {$status})";
        json_out(['error' => $msg], 502);
    }
    return $data;
}

// ---------------------------------------------------------------------------
// Boucle agentique : tant que Claude demande un outil, on l'exécute.
// ---------------------------------------------------------------------------
try {
    $body = json_decode(file_get_contents('php://input') ?: '[]', true);
    $clientMessages = $body['messages'] ?? [];
    if (!is_array($clientMessages) || $clientMessages === []) {
        json_out(['error' => 'Aucun message fourni'], 400);
    }

    // On ne garde que des tours user/assistant textuels venant du client.
    $messages = [];
    foreach ($clientMessages as $m) {
        $role = $m['role'] ?? '';
        $content = trim((string) ($m['content'] ?? ''));
        if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
            continue;
        }
        $messages[] = ['role' => $role, 'content' => $content];
    }
    if ($messages === [] || $messages[array_key_last($messages)]['role'] !== 'user') {
        json_out(['error' => 'Le dernier message doit venir de l\'utilisateur'], 400);
    }

    $system = "Tu es l'assistant de gestion interne d'AB ENGINS SARL, entreprise camerounaise de location "
        . "d'engins de chantier (exploitation forestière). Tu réponds en français, de façon claire et concise, "
        . "aux questions du personnel sur les clients, contrats, engins, permis d'exploitation, factures et "
        . "interventions d'assistance.\n\n"
        . "Règles :\n"
        . "- Appuie chaque réponse chiffrée sur les outils fournis (base de données réelle) ; n'invente jamais de données.\n"
        . "- Les montants sont en FCFA : formate-les avec séparateur de milliers (ex. 1 250 000 FCFA).\n"
        . "- Les dates au format JJ/MM/AAAA. Nous sommes le " . date('d/m/Y') . ".\n"
        . "- Si une question sort du périmètre de gestion (ex. conseil juridique), dis-le simplement.\n"
        . "- Réponses courtes : liste à puces pour les énumérations, une phrase de synthèse d'abord.";

    $payloadBase = [
        'model' => 'claude-opus-4-8',
        'max_tokens' => 16000,
        'thinking' => ['type' => 'adaptive'],
        'system' => $system,
        'tools' => agentTools(),
    ];

    $response = anthropicRequest($payloadBase + ['messages' => $messages]);

    $iterations = 0;
    while (($response['stop_reason'] ?? '') === 'tool_use' && $iterations < 8) {
        $iterations++;
        $toolResults = [];
        foreach ($response['content'] as $block) {
            if (($block['type'] ?? '') !== 'tool_use') {
                continue;
            }
            $result = runTool($block['name'], is_array($block['input'] ?? null) ? $block['input'] : []);
            $toolResults[] = [
                'type' => 'tool_result',
                'tool_use_id' => $block['id'],
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
        }
        // Tour assistant complet (y compris blocs thinking) puis résultats d'outils.
        $messages[] = ['role' => 'assistant', 'content' => $response['content']];
        $messages[] = ['role' => 'user', 'content' => $toolResults];
        $response = anthropicRequest($payloadBase + ['messages' => $messages]);
    }

    $text = '';
    foreach ($response['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }
    if ($text === '') {
        $text = "Je n'ai pas pu formuler de réponse. Réessayez en reformulant votre question.";
    }

    json_out(['reply' => $text]);
} catch (Throwable $e) {
    handle_error($e);
}
