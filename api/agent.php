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

    // Dernière question utilisateur + historique des questions précédentes
    // (transmis au moteur pour le suivi de contexte : « et ses contrats ? »).
    $questions = [];
    foreach ($clientMessages as $m) {
        if (($m['role'] ?? '') === 'user') {
            $q = trim((string) ($m['content'] ?? ''));
            if ($q !== '') {
                $questions[] = $q;
            }
        }
    }
    if ($questions === []) {
        json_out(['error' => 'Le dernier message doit venir de l\'utilisateur'], 400);
    }
    $question = array_pop($questions);

    json_out(['reply' => moteurRepondre($question, $questions)]);
} catch (Throwable $e) {
    handle_error($e);
}
