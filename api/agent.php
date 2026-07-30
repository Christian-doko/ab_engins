<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
requireAuth();
require __DIR__ . '/moteur_ia.php';

/**
 * Assistant IA AB ENGINS — endpoint de chat.
 * Reçoit {messages: [{role, content}...]} et répond via le moteur NLU maison
 * (api/moteur_ia.php) : aucune API externe, tout le traitement est local.
 */

try {
    $body = json_decode(file_get_contents('php://input') ?: '[]', true);
    $clientMessages = $body['messages'] ?? [];
    if (!is_array($clientMessages) || $clientMessages === []) {
        json_out(['error' => 'Aucun message fourni'], 400);
    }

    // Le moteur traite chaque question indépendamment : on prend la dernière question utilisateur.
    $question = '';
    foreach ($clientMessages as $m) {
        if (($m['role'] ?? '') === 'user') {
            $question = trim((string) ($m['content'] ?? ''));
        }
    }
    if ($question === '') {
        json_out(['error' => 'Le dernier message doit venir de l\'utilisateur'], 400);
    }

    json_out(['reply' => moteurRepondre($question)]);
} catch (Throwable $e) {
    handle_error($e);
}
